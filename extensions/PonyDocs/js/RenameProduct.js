$( function() {
	// Check for Rename Version
	if ( $( "#renameProduct" ).length > 0 ) {
		SplunkRenameProduct.init();
	}
});

SplunkRenameVersion = function() {
	var sourceProductName = '';
	var targetProductName = '';
	var manuals = [];
	var jobId = '';
	var progressTimer = null;
	var completed = false;

	return {
		// Set up event handlers for the Rename Version page
		init: function() {
			$( '#submitRenameProduct' ).click( function() {
				sourceProductName = $( '#sourceProduct' ).val();
				targetProductName = $( '#targetProduct' ).val();
				if( !confirm(
					'Are you sure you want to rename ' + sourceProductName + ' to ' + targetProductName + '?\n'
					+ 'Be sure your selection is correct because there is no stopping it once it begins.\n'
					+ 'Please note this will take some time, so please be patient.' ) ) {
					return false;
				}
				$( '#sourceProduct' ).attr( 'disabled', 'disabled' );
				$( '#targetProduct' ).attr( 'disabled', 'disabled' );

				// Okay, time to submit.
				// First get the list of manuals
				$( '#submitRenameProduct' ).attr( 'disabled', 'disabled' ).attr( 'value', 'Fetching Manuals...' );
				sajax_do_call( 'PonyDocsAPI::ajaxFetchManuals', [ sourceProductName ], function( res ) {
					manuals = eval( res.responseText );
				});
				sajax_do_call( 'PonyDocsAPI::ajaxFetchJobId', [ 'RenameProduct' ], function( res ) {
					jobId = res.responseText;

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
			if ( manuals.length > 0 ) {
				var manual = manuals.shift();
				sajax_do_call(
					'SpecialRenameProduct::ajaxProcessManual',
					[ jobId, manual['shortname'], sourceProductName, targetProductName ],
					function ( res ) {
						// TODO append instead of replace
						$( '#renameProduct .completed .logconsole' ).append( res.responseText );
						SplunkRenameProduct.processNextManual();
				});
			} else {
				// Finish the job
				SplunkRenameVersion.finishProductRename();
			}
		},
		finishProductRename: function() {
			sajax_do_call(
				'SpecialRenameProduct::ajaxFinishProductRename',
				[ jobId, sourceProductName, targetProductName ],
				function ( res ) {
					$( '#renameProduct .completed .logconsole' ).append( res.responseText );
					completed = true;
					// Update the progress console and cancel any scheduled call to fetchProgress
					clearTimeout( progressTimer );
					progressTimer = null;
					$( '#renameProduct .completed' ).fadeIn();
			});
		},
		// Read the contents of the temp file on the server and write them out to the progressconsole div
		// TODO: Multiple webheads break this - we have a 1/7 chance of getting the progress data.
		fetchProgress: function() {
			sajax_do_call('PonyDocsApi::ajaxFetchJobProgress', [ jobId ], function( res ) {
				$( '#progressconsole' ).html( res.responseText );
				if ( !completed ) {
					progressTimer = setTimeout( 'PonyDocsAPI.fetchProgress();', 3000 );
				}
			});
		}
	};
}();