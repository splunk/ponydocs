$(function(){
	// Editing check to make sure there are matching <search> and </search> elements.
	$("#editform").submit(function(event) {
		// Let's evaluate the content
		var content = $("#wpTextbox1").val();
		var opened = content.match(/<search>/gi);
		var closed = content.match(/<\/search>/gi);
		if((opened == null && closed == null) || (opened != null && closed != null && opened.length == closed.length)) {
			return true;
		}
		else {
			event.preventDefault();
			alert("You have a mismatched number of opened/closed search elements in the edit text.  Please note, we only accept '<search>' and not '<search   >' (note spaces).  Correct and re-submit.");
			return false;
		}
	});

	// Check for branch inherit
	if ( $( "#docbranchinherit" ).length > 0 ) {
		SplunkBranchInherit.init();
	}
	if ( $( "#renameversion" ).length > 0 ) {
		SplunkRenameVersion.init();
	}
	if( typeof(ponydocsOnLoad) !== 'undefined' ) {
		ponydocsOnLoad();
	}
});

SplunkBranchInherit = function() {
	var sourceProduct = '';
	var sourceVersion = '';
	var targetVersion = '';
	var manuals = [];
	var defaultAction = 'ignore';
	var topicActions = {};
	var jobID = '';
	var progressTimer = null;
	var completed = false;
	var forceTitle = null;
	var forceManual = null;

	return {
		init: function() {
				$('#versionselect_submit').click(function() {
					sourceProduct = $('#force_product').val();
					if($('#force_sourceVersion').length != 0) {
						sourceVersion = $('#force_sourceVersion').val();
						forceTitle = $('#force_titleName').val();
						forceManual = $('#force_manual').val();
					}
					else {
						sourceVersion = $('#versionselect_sourceversion').val();
					}
					targetVersion = $('#versionselect_targetversion').val();
					if(sourceVersion == targetVersion) {
						alert('Target version can not be the same as source version.');
					}
					else {
						$('#docbranchinherit .sourceversion').html(sourceVersion);
						$('#docbranchinherit .targetversion').html(targetVersion);
						$('#versionselect_submit').attr("disabled", "disabled").attr("value", "Fetching Data...");
						if(forceTitle == null) {
							sajax_do_call('SpecialBranchInherit::ajaxFetchManuals', [sourceProduct, sourceVersion], function(res) {
								var manuals = eval(res.responseText);
								var container = $('#manualselect_manuals');
								container.html('');
								for(index in manuals) {
									var html = "<input id=\"manual_" + manuals[index]['shortname'] + "\" type=\"checkbox\" name=\"manual\" value=\"" + manuals[index]['shortname'] + "\" /><label for=\"manual_" + manuals[index]['shortname'] + "\">" + manuals[index]['longname'] + "</label><br />";
									container.prepend(html);
								}
								$('#docbranchinherit .versionselect').fadeOut(function () {
									$('#versionselect_submit').attr("value", "Continue to Manuals").removeAttr("disabled");
									$('#docbranchinherit .manualselect').fadeIn();
								});
							});
						}
						else {
							// Force handling a title.
							sajax_do_call('SpecialBranchInherit::ajaxFetchTopics', [sourceProduct, sourceVersion, targetVersion, forceManual, forceTitle], SplunkBranchInherit.setupTopicActions);
						}
					}
				});
				$('.sectiondefault').live("change", function(event) {
					var val = $(this).val();
					$(this).siblings("table").find("option[value='" + val + "']").attr("selected", "selected");
					if(val == "inherit") {
						$(this).siblings("table").find("option[value='inheritpurge']").attr("selected", "selected");
					}
					if(val == "branch") {
						$(this).siblings("table").find("option[value='branchsplit']").attr("selected", "selected");
					}
				});
				$('#manualselect_submit').click(function() {
					manuals = [];
					if($('#manualselect_manuals input:checked').length == 0) {
						alert("You must select at least one manual.");
						return;
					}
					if($('input[name=\'manualselect_action\']:checked').length == 0) {
						alert("You must select a default action.");
						return;
					}
					defaultAction = $('input[name=\'manualselect_action\']:checked').val();
					$('#manualselect_manuals input:checked').each(function() {
						manuals[manuals.length] = $(this).val();
					});
					$("#manualselect_submit").attr("disabled", "disabled").attr("value", "Fetching Data...");
					// Okay, let's fetch our tocs.
					sajax_do_call('SpecialBranchInherit::ajaxFetchTopics', [sourceProduct, sourceVersion, targetVersion, manuals.join(',')], SplunkBranchInherit.setupTopicActions);
				});
				$('#topicactions_submit').click(function() {
						if(!confirm("Are you sure you want to process this job?  Be sure to review all topics because there is no stopping it once it begins.  Please note this will take some time, so please be patient.")) {
							return false;
						}
						$('#topicactions_submit').attr("value", "Processing...").attr("disabled", "disabled");
						// Time to build topic actions
						$('#docbranchinherit .topicactions .container .manual').each(function() {
							var manualName = $(this).find('.manual_shortname').val();
							var tocAction = $(this).find('.manualtocaction').val();
							topicActions[manualName] = {};
							// Determine if we need to create new toc or branch.
							if($(this).find('option[value=\'ignore\']:selected').length > 0) {
								topicActions[manualName].tocInherit = false;
							}
							else {
								topicActions[manualName].tocInherit = true;
							}
							topicActions[manualName].tocAction = tocAction;
							topicActions[manualName].sections = {};
							$(this).find('.section').each(function() {
								var sectionName = $(this).find('h3').html();
								topicActions[manualName].sections[sectionName] = [];
								$(this).find('tr').each(function() {
									var topic = {};
									topic.title = $(this).find('.topicname em').html();
									topic.text = $(this).find('.topicname strong').html();
									topic.toctitle = $(this).find('.action input').val();
									topic.action = $(this).find('.action select').val();
									topicActions[manualName].sections[sectionName][topicActions[manualName].sections[sectionName].length] = topic;
								});
							});
						});
						// Okay, time to submit.
						// First grab the job ID.
						sajax_do_call('SpecialBranchInherit::ajaxFetchJobID', [], function(res) {
							SplunkBranchInherit.jobID = res.responseText;
							sajax_request_type = 'POST';
							SplunkBranchInherit.fetchProgress();
							sajax_do_call('SpecialBranchInherit::ajaxProcessRequest', [SplunkBranchInherit.jobID, sourceProduct, sourceVersion, targetVersion, $.toJSON(topicActions)], function(res) {
								completed = true;
								clearTimeout(progressTimer);
								progressTimer = null;
								$("#docbranchinherit .completed .logconsole").html(res.responseText);
								$("#docbranchinherit .topicactions").fadeOut(function() {
									$("#docbranchinherit .completed").fadeIn();
								});
							});
						});
				});
			},
			setupTopicActions: function(res) {
				var container = $('.topicactions .container');
				var topicData = eval('(' + res.responseText + ')');
				var html = '';
				for(manual in topicData) {
					html += '<div class="manual"><h2>' + topicData[manual].meta.text + '</h2>';
					html += '<input type="hidden" class="manual_shortname" value="' + manual + '" />';
					if(topicData[manual].meta.toc_exists != false && topicData[manual].meta.toc_exists != '') {
						html += '<p>A Table Of Contents already exists for this manual.  Topics processed below will be added only if they do not exist in the TOC.</p><input class="manualtocaction" type="hidden" value="default"/>';

					} else {
						html += '<p>A Table Of Contents does not exist for this manual.  Choose creation behavior: <select class="manualtocaction">';

						if(defaultAction == 'inherit') {
							html += '<option value="forceinherit" selected="selected">Force Inherit</option>';
						} else {
							html += '<option value="forceinherit">Force Inherit</option>';
						}
						if(defaultAction == 'branch') {
							html += '<option value="forcebranch" selected="selected">Force Branch</option>';
						} else {
							html += '<option value="forcebranch">Force Branch</option>';
						}

						html += '</select></p>';
					}
					for(section in topicData[manual].sections) {
						html += '<div class="section"><h3>' + section + '</h3>Set Action For All Topics In This Section: <select class="sectiondefault">';
								if(defaultAction == 'ignore') {
									html += '<option value="ignore" selected="selected">Ignore</option>';
								}
								else {
									html += '<option value="ignore">Ignore</option>';
								}
								if(defaultAction == 'branch') {
									html += '<option value="branch" selected="selected">Branch</option>';
								}
								else {
									html += '<option value="branch">Branch</option>';
								}
								if(defaultAction == 'inherit') {
									html += '<option value="inherit" selected="selected">Inherit</option>';
								}
								else {
									html += '<option value="inherit">Inherit</option>';
								}

						html += '</select><table class="topiclist"><thead><td class="title"><strong>Title</strong></td><td class="conflicts"><strong>Conflicts</strong></td><td class="actions"><strong>Action</strong></td></thead>';
						for(topic in topicData[manual].sections[section].topics) {
							var el = topicData[manual].sections[section].topics[topic];
							html += '<tr><td class="topicname"><strong>' + el['text'] + '</strong><br /><em>' + el['title'] + '</em></td><td class="conflicts">' + el['conflicts'] + '</td><td class="action"><select name="action">';
							if(el['conflicts'] == '') {
								if(defaultAction == 'ignore') {
									html += '<option value="ignore" selected="selected">Ignore</option>';
								}
								else {
									html += '<option value="ignore">Ignore</option>';
								}
								if(defaultAction == 'branch') {
									html += '<option value="branch" selected="selected">Branch</option>';
								}
								else {
									html += '<option value="branch">Branch</option>';
								}
								if(defaultAction == 'inherit') {
									html += '<option value="inherit" selected="selected">Inherit</option>';
								}
								else {
									html += '<option value="inherit">Inherit</option>';
								}
							}
							else {
								if(defaultAction == 'ignore') {
									html += '<option value="ignore" selected="selected">Ignore</option>';
								}
								else {
									html += '<option value="ignore">Ignore</option>';
								}
								if(defaultAction == 'branch') {
									html += '<option value="branchpurge" selected="selected">Branch - Purge Existing</option>';
									html += '<option value="branchsplit">Branch - Split</option>';
								}
								else {
									html += '<option value="branchpurge">Branch - Purge Existing</option>';
									html += '<option value="branchsplit">Branch - Split</option>';
								}
								if(defaultAction == 'inherit') {
									html += '<option value="inheritpurge" selected="selected">Inherit - Purge Existing</option>';
								}
								else {
									html += '<option value="inheritpurge">Inherit - Purge Existing</option>';
								}
							}
							html += '</select><input type="hidden" name="toctitle" value="' + el['toctitle'] + '" /></td></tr>';
						}
						html += '</table></div>';
					}
					html += '</div>';
				}
				container.html(html);
				$('#docbranchinherit .manualselect, #docbranchinherit .versionselect').fadeOut(function() {
					$('#manualselect_submit').attr("value", "Continue to Topics").removeAttr("disabled");
					$('#docbranchinherit .topicactions').fadeIn();
				});
			},
			fetchProgress: function() {
						sajax_do_call('SpecialBranchInherit::ajaxFetchJobProgress', [SplunkBranchInherit.jobID], function(res) {
						$('#progressconsole').html(res.responseText);
						if(!completed) {
								progressTimer = setTimeout("SplunkBranchInherit.fetchProgress();", 3000);
						}
				});
			}
	};
}();

SplunkRenameVersion = function() {
	var sourceProduct = '';
	var sourceVersion = '';
	var targetVersion = '';
	var manuals = [];
	var jobID = '';
	var progressTimer = null;
	var completed = false;

	return {
		// Set up event handlers for the Rename Version page
		init: function() {
			$( '#versionselect_submit' ).click( function() {
				sourceProduct = $( '#force_product' ).val();
				sourceVersion = $( '#versionselect_sourceversion' ).val();
				targetVersion = $( '#versionselect_targetversion' ).val();
				if ( sourceVersion == targetVersion ) {
					alert( 'Target version can not be the same as source version.' );
				}
				else {
					$( '#renameversion .sourceversion' ).html( sourceVersion );
					$( '#renameversion .targetversion' ).html( targetVersion );
					$( '#versionselect_sourceversion' ).attr( 'disabled', 'disabled' );
					$( '#versionselect_targetversion' ).attr( 'disabled', 'disabled' );

					// Okay, time to submit.
					// First get the list of manuals
					$( '#versionselect_submit' ).attr( 'disabled', 'disabled' ).attr( 'value', 'Fetching Manuals...' );
					sajax_do_call( 'SpecialBranchInherit::ajaxFetchManuals', [ sourceProduct, sourceVersion ], function( res ) {
						SplunkRenameVersion.manuals = eval( res.responseText );
						var manualHTML = '<h2>Manuals to be processed</h2><ul>';
						for ( index in SplunkRenameVersion.manuals ) {
							manualHTML += '<li>' + SplunkRenameVersion.manuals[index]['shortname'] + '</li>';
						}
						manualHTML += '</ul>';
						$( '#manuallist' ).html( manualHTML );
						$( '#renameversion .submitrequest' ).fadeIn();
					});
				}
			});
			$( '#renameversion_submit' ).click( function() {
				if( !confirm(
					'Are you sure you want to rename ' + sourceVersion + ' to ' + targetVersion
					+ ' in ' + sourceProduct + '?\n'
					+ 'Be sure your selection is correct because there is no stopping it once it begins.\n'
					+ 'Please note this will take some time, so please be patient.' ) ) {
					return false;
				}
				$( '#renameversion_submit' ).attr( 'disabled', 'disabled' ).attr( 'value', 'Renaming Version...' );
				// Grab the job ID.
				sajax_do_call( 'SpecialRenameVersion::ajaxFetchJobID', [], function( res ) {
					SplunkRenameVersion.jobID = res.responseText;

					// Set up the progress meter
					sajax_request_type = 'POST';
					SplunkRenameVersion.fetchProgress();

					// Iterate over the manuals
					SplunkRenameVersion.processNextManual();
				});
			});
		},
		// Make an ajax call to process a single manual
		// In order to fake a sychronous call (since sajax doesn't support such a thing), let's be recursively nested.
		processNextManual: function() {
			if ( SplunkRenameVersion.manuals.length > 0 ) {
				var manual = SplunkRenameVersion.manuals.shift();
				sajax_do_call(
					'SpecialRenameVersion::ajaxProcessManual',
					[ SplunkRenameVersion.jobID, sourceProduct, manual['shortname'], sourceVersion, targetVersion ],
					function ( res ) {
						// TODO append instead of replace
						$( '#renameversion .completed .logconsole' ).append( res.responseText );
						SplunkRenameVersion.processNextManual();
				});
			} else {
				completed = true;
				clearTimeout( progressTimer );
				progressTimer = null;
				$( '#renameversion .completed' ).fadeIn();
			}
		},
		// Read the contents of the temp file on the server and write them out to the progressconsole div
		// TODO: Multiple webheads break this - we have a 1/7 chance of getting the progress data.
		fetchProgress: function() {
			sajax_do_call('SpecialRenameVersion::ajaxFetchJobProgress', [ SplunkRenameVersion.jobID ], function( res ) {
				$( '#progressconsole' ).html( res.responseText );
				if ( !completed ) {
					progressTimer = setTimeout( 'SplunkRenameVersion.fetchProgress();', 3000 );
				}
			});
		}
	};
}();

// Function defs