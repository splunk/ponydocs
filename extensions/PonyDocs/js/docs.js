$( function() {
	// Validate the edit form
	$( "#editform" ).submit( function( event ) {
		return PonyDocsEventHandlers.editFormSubmit( event );
	});

	// Check for branch inherit
	if ( $( "#docbranchinherit" ).length > 0 ) {
		SplunkBranchInherit.init();
	}
	
	// Check for Rename Version
	if ( $( "#renameversion" ).length > 0 ) {
		SplunkRenameVersion.init();
	}
});

PonyDocsEventHandlers = function() {
	return {
		/**
		 * Validate the edit form
		 * @param event event
		 * @return boolean
		 */
		editFormSubmit: function( event ) {
			var alertString = "";
			var returnValue = true;
			var content = $( "#wpTextbox1" ).val();

			badTopics = PonyDocsValidators.topicTitle( content );
			if ( badTopics.length > 0 ) {
				returnValue = false;
				alertString += "The following topics have forbidden characters: * / & ? < > ' \" are not allowed.\n";
				for ( var i = 0; i < badTopics.length; i++ ) {
					alertString += "* " + badTopics[i] + "\n";
				}
				alertString += "\n";
			}

			if ( !returnValue ) {
				event.preventDefault();
				alert(alertString);
			}

			return returnValue;
		}
	}
}();

PonyDocsValidators = function() {
	return {
		/**
		 * Ensure no invalid characters in topic titles
		 * @param string content
		 * @return array of topic names with invalid characters
		 */
		topicTitle: function( content ) {
			var badTitles = [];
			var matchedTopics = content.match( /{{#topic:(.*)/gi );
			var forbiddenCharsInTopics = /[*\/&?<>'"]/;
			if (matchedTopics !== null) {
				for ( var i = 0; i < matchedTopics.length; i++ ) {
					topic = matchedTopics[i].replace( '{{#topic:', '' ).replace( '}}', '' );
					if ( forbiddenCharsInTopics.test( topic ) ) {
						badTitles.push( topic );
					}
				}
			}
			return badTitles;
		}
	}
}();

SplunkBranchInherit = function() {
	var sourceProduct = '';
	var sourceVersion = '';
	var targetProduct = '';
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
			// Change the current version and reload the page when source product changes
			$( '#docsSourceProductSelect' ).change( function() {
				var productIndex = document.getElementById( 'docsSourceProductSelect' ).selectedIndex;
				var productName = document.getElementById( 'docsSourceProductSelect' )[productIndex].value;
				// @todo fix this title
				var title = window.location.pathname;
				var force = true;
				sajax_do_call(
					'efPonyDocsAjaxChangeProduct', [productName, title, force], function( o ) {
						document.getElementById( 'docsSourceProductSelect' ).disabled = true;
						var s = new String( o.responseText );
						document.getElementById( 'docsSourceProductSelect' ).disabled = false;
						window.location.href = s;
					}), true;
			});
			
			// Update the target version select when target product changes
			$( '#docsTargetProductSelect' ).change( function() {
				var productIndex = document.getElementById( 'docsTargetProductSelect' ).selectedIndex;
				var productName = document.getElementById( 'docsTargetProductSelect' )[productIndex].value;
				sajax_do_call( 'efPonyDocsAjaxGetVersions', [productName], function( o ) {
					document.getElementById( 'docsTargetProductSelect' ).disabled = true;
					document.getElementById( 'versionselect_targetversion' ).disabled = true;
					var versions = eval( o.responseText );
					var targetVersionSelect = document.getElementById( 'versionselect_targetversion');
					// Remove options
					var selectLength = targetVersionSelect.length;
					for ( var i = 0; i < selectLength; i++ ) {
						targetVersionSelect.remove(0);
					}
					// Add options
					for ( var i = 0; i < versions.length; i++ ) {
						option = document.createElement( 'option' );
						option.setAttribute( 'value', versions[i].short_name );
						option.appendChild(document.createTextNode( versions[i].short_name + ' - ' + versions[i].status ) );
						targetVersionSelect.appendChild( option );
					}
					document.getElementById( 'docsTargetProductSelect' ).disabled = false;
					document.getElementById( 'versionselect_targetversion' ).disabled = false;
				}, true);
			});
			
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
				targetProduct = $( '#docsTargetProductSelect' ).val();
				targetVersion = $('#versionselect_targetversion').val();
				if ( sourceProduct == targetProduct && sourceVersion == targetVersion ) {
					alert('Target version cannot be the same as source version.');
				} else {
					$('#docbranchinherit .sourceversion').html(sourceProduct + ':' + sourceVersion);
					$('#docbranchinherit .targetversion').html(targetProduct + ':' + targetVersion);
					$('#versionselect_submit').attr("disabled", "disabled").attr("value", "Fetching Data...");
					if ( forceTitle == null ) {
						SplunkBranchInherit.setupManuals();
					} else {
						// Force handling a title.
						sajax_do_call('SpecialBranchInherit::ajaxFetchTopics', 
							[sourceProduct, sourceVersion, targetProduct, targetVersion, forceManual, forceTitle],
							SplunkBranchInherit.setupTopicActions);
					}
				}
			});

			$(document).on( 'change', '.sectiondefault', null, function() {
				var val = $(this).val();
				$(this).siblings("table").find("option[value='" + val + "']").attr("selected", "selected");
				if (val == "inherit") {
					$(this).siblings("table").find("option[value='inheritpurge']").attr("selected", "selected");
				} else if (val == "branch") {
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
				sajax_do_call('SpecialBranchInherit::ajaxFetchTopics', 
					[sourceProduct, sourceVersion, targetProduct, targetVersion, manuals.join(',')],
					SplunkBranchInherit.setupTopicActions);
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
						sajax_do_call(
							'SpecialBranchInherit::ajaxProcessRequest',
							[SplunkBranchInherit.jobID, sourceProduct, sourceVersion, targetProduct, targetVersion,
								$.toJSON(topicActions)],
							function(res) {
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
		
		setupManuals: function() {
			var sourceManuals = '';
			var targetManuals = '';
			var manuals = {};
			sajax_do_call('SpecialBranchInherit::ajaxFetchManuals', [sourceProduct, sourceVersion], function( res ) {
				sourceManuals = eval( res.responseText );
				// This second call is only necessary when sourceProduct != targetProduct
				// but because sajax_do_call() can't do synchronous, I'm always make the second call to keep thing simpler
				// @todo replace sajax_do_call with jQuery.ajax *everywhere*
				sajax_do_call('SpecialBranchInherit::ajaxFetchManuals', [targetProduct], function( res ) {
					targetManuals = eval( res.responseText );
					if ( sourceProduct == targetProduct ) {
						manuals = sourceManuals;
					} else {
						for ( manual in sourceManuals ) {
							if ( manual in targetManuals ) {
								manuals[manual] = sourceManuals[manual];
							}
						}
					}

					var container = $( '#manualselect_manuals' );
					container.html( '' );
					for ( manual in manuals ) {
						var html = "<input id=\"manual_" + manuals[manual]['shortname']
							+ "\" type=\"checkbox\" name=\"manual\" value=\"" + manuals[manual]['shortname']
							+ "\" /><label for=\"manual_" + manuals[manual]['shortname'] + "\">" + manuals[manual]['longname']
							+ "</label><br />";
						container.prepend( html );
					}
					
					$( '#docbranchinherit .versionselect' ).fadeOut( function () {
						$( '#versionselect_submit' ).attr( "value", "Continue to Manuals" ).removeAttr( "disabled" );
						$( '#docbranchinherit .manualselect' ).fadeIn();
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
	var manuals = {};
	var manualsArray = [];
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
					alert( 'Target version cannot be the same as source version.' );
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
						manuals = eval( res.responseText );
						var manualHTML = '<h2>Manuals to be processed</h2><ul>';
						for ( index in manuals ) {
							manualHTML += '<li>' + manuals[index]['shortname'] + '</li>';
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
					jobID = res.responseText;

					// Set up the progress meter
					sajax_request_type = 'POST';
					SplunkRenameVersion.fetchProgress();
					
					// Make an array of manuals that we can iterate over
					var i = 0;
					for ( index in manuals ) {
						manualsArray[i] = manuals[index];
						i++;
					}
					// Iterate over the manuals
					SplunkRenameVersion.processNextManual();
				});
			});
		},
		// Make an ajax call to process a single manual
		// In order to fake a sychronous call (since sajax doesn't support such a thing), let's be recursively nested.
		processNextManual: function() {

			if ( manualsArray.length > 0 ) {
				var manual = manualsArray.shift();
				sajax_do_call(
					'SpecialRenameVersion::ajaxProcessManual',
					[ jobID, sourceProduct, manual['shortname'], sourceVersion, targetVersion ],
					function ( res ) {
						// TODO append instead of replace
						$( '#renameversion .completed .logconsole' ).append( res.responseText );
						SplunkRenameVersion.processNextManual();
				});
			} else {
				completed = true;
				// Update the progress console and cancel any scheduled call to fetchProgress
				clearTimeout( progressTimer );
				progressTimer = null;
				$( '#renameversion .versionselect, #renameversion .submitrequest' ).fadeOut(function () {
					$( '#renameversion .completed' ).fadeIn();
				});
			}
		},
		// Read the contents of the temp file on the server and write them out to the progressconsole div
		// TODO: Multiple webheads break this - we have a 1/7 chance of getting the progress data.
		fetchProgress: function() {
			sajax_do_call('SpecialRenameVersion::ajaxFetchJobProgress', [ jobID ], function( res ) {
				$( '#progressconsole' ).html( res.responseText );
				if ( !completed ) {
					progressTimer = setTimeout( 'SplunkRenameVersion.fetchProgress();', 3000 );
				}
			});
		}
	};
}();

// Function defs