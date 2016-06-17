<?php
if( !defined( 'MEDIAWIKI' ) ) {
	die( 'PonyDocs MediaWiki Extension' );
}

/**
 * Needed since we subclass it;  it doesn't seem to be loaded elsewhere.
 */
require_once( "$IP/includes/specialpage/SpecialPage.php" );

/**
 * Register our 'Special' page so it is listed and accessible.
 */
$wgSpecialPages['RenameVersion'] = 'SpecialRenameVersion';

// Ajax Handlers
$wgAjaxExportList[] = 'SpecialRenameVersion::ajaxFetchJobID';
$wgAjaxExportList[] = 'SpecialRenameVersion::ajaxFetchJobProgress';
$wgAjaxExportList[] = 'SpecialRenameVersion::ajaxProcessManual';

/**
 * The Special page which handles the UI for renaming versions
 */
class SpecialRenameVersion extends SpecialPage
{
	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 *
	 * @returns SpecialRenameVersion
	 */
	public function __construct() {
		SpecialPage::__construct( 'RenameVersion' );
	}
	
	/**
	 * Returns a human readable description of this special page.
	 *
	 * @returns string
	 */
	public function getDescription() {
		return 'Rename Version Controller';
	}

	/**
	 * AJAX method to fetch/initialize uniqid to identify this session. Used to build progress report.
	 *
	 * @returns string The unique id for this job.
	 */
	public static function ajaxFetchJobID() {
		$uniqid = uniqid( 'ponydocsrenameversion', true );
		// Create the file.
		$path = PonyDocsExtension::getTempDir() . $uniqid;
		$fp = fopen( $path, 'w+' );
		fputs( $fp, 'Determining Progress...' );
		fclose( $fp );
		return $uniqid;
	}

	/**
	 * AJAX method to fetch job progress. Used to update progress report.
	 * 
	 * @param type $jobID
	 * @return string 
	 */
	public static function ajaxFetchJobProgress( $jobID ) {
		$path = PonyDocsExtension::getTempDir() . $jobID;
		$progress = file_get_contents( $path );
		if ( $progress === false ) {
			$progress = 'Unable to fetch Job Progress.';
		}
		return $progress;
	}

	/**
	 * Processes a rename version request for a single manual
	 *
	 * @param $jobID The unique id for this job (see ajaxFetchJobID)
	 * @param $productName string product short name
	 * @param $manualName string manual short name
	 * @param $sourceVersionName string String representation of the source version
	 * @param $targetVersionShortName string String representaiton of the target version
	 * @return string Full job log of the process by printing to stdout.
	 */
	public static function ajaxProcessManual( $jobID, $productName, $manualName, $sourceVersionName, $targetVersionShortName ) {
		global $wgScriptPath;
		ob_start();

		list ( $msec, $sec ) = explode( ' ', microtime() ); 
		$startTime = (float)$msec + (float)$sec; 

		$logFields = "action=start status=success product=$productName manual=$manualName "
			. "sourceVersion=$sourceVersionName targetVersion=$targetVersionShortName";
		error_log( 'INFO [' . __METHOD__ . "] [RenameVersion] $logFields" );

		// Validate product and versions
		$product = PonyDocsProduct::GetProductByShortName( $productName );
		$manual = PonyDocsProductManual::getManualByShortName( $productName, $manualName );
		$sourceVersion = PonyDocsProductVersion::GetVersionByName( $productName, $sourceVersionName );
		$targetVersion = PonyDocsProductVersion::GetVersionByName( $productName, $targetVersionShortName );
		if ( !($product && $manual && $sourceVersion && $targetVersion ) ) {
			$result = array( 'success', false );
			$result = json_encode( $result );
			return $result;
		}

		// TODO: Is this necessary? Haven't we done this already?
		PonyDocsProductVersion::SetSelectedVersion( $productName, $sourceVersionName );
		
		print "Beginning process job for manual: $manualName<br />";
		print "Source version is $productName:$sourceVersionName<br />";
		print "Target version is $targetVersionShortName<br />";
		
		// Get topics
		// Update log file
		// TODO: Pull this out into a separate method somewhere
		$path = PonyDocsExtension::getTempDir() . $jobID;
		$fp = fopen( $path, "w+" );
		fputs( $fp, "Getting Topics for $manualName" );
		fclose( $fp );

		// TODO: This is copied form SpecialBranchInherit::ajaxFetchTopics() and should get DRYed out
		$manualTopics = array();

		$TOC = new PonyDocsTOC( $manual, $sourceVersion, $product );
		list( $toc, $prev, $next, $start ) = $TOC->loadContent();
		// Time to iterate through all the items.
		$section = '';
		foreach ( $toc as $tocItem ) {
			if ( $tocItem['level'] == 0 ) {
				$section = $tocItem['text'];
				$manualTopics[$manualName]['sections'][$section] = array();
				$manualTopics[$manualName]['sections'][$section]['meta'] = array();
				$manualTopics[$manualName]['sections'][$section]['topics'] = array();
			}
			// actual topic
			if ( $tocItem['level'] == 1 ) {
				$tempEntry = array(
					'title' => $tocItem['title'],
					'text' => $tocItem['text'],
					'toctitle' => $tocItem['toctitle'],
					'conflicts' => PonyDocsBranchInheritEngine::getConflicts(
						$product, $tocItem['title'], $targetVersion )
				);
				$manualTopics[$manualName]['sections'][$section]['topics'][] = $tempEntry;
			}
		}
		foreach ( $manualTopics as $manualName => $manual ) {
			foreach ( $manual['sections'] as $sectionIndex => $section ) {
				if ( count($section['topics']) == 0 ) {
					unset( $manualTopics[$manualName]['sections'][$sectionIndex] );
				}
			}
		}

		$logFields = "action=topics status=success product=$productName manual=$manualName "
			. "sourceVersion=$sourceVersionName targetVersion=$targetVersionShortName";
		error_log( 'INFO [' . __METHOD__ . "] [RenameVersion] $logFields" );

		// Enable speed processing to avoid any unnecessary processing on topics modified by this tool.
		// TODO: I'm not 100% sure this is necessary or proper here -RU
		PonyDocsExtension::setSpeedProcessing( TRUE );

		// Determine how many topics there are to process so that we can keep track of progress
		$numOfTopics = 0;
		$numOfTopicsCompleted = 0;

		foreach ( $manualTopics as $manualName => $manualData ) {
			foreach ( $manualData['sections'] as $sectionName => $section ) {
				// The following is a goofy fix for some browsers.
				// Sometimes the JSON comes along with null values for the first element. 
				// It's just an additional element, so we can drop it.
				// TODO: Since we're no longer getting this from JSON, this is probably removeable
				if ( empty( $section['topics'][0]['text'] ) ) {
					array_shift( $manualTopics[$manualName]['sections'][$sectionName]['topics'] );
				}
				$numOfTopics += count( $manualTopics[$manualName]['sections'][$sectionName]['topics'] );
			}
		}

		foreach ( $manualTopics as $manualName => $manualData ) {
			// TODO: We already got the manual above, why make another? We could add the manual to $manualTopics somewhere..
			$manual = PonyDocsProductManual::GetManualByShortName( $productName, $manualName );

			// First update all the topics
			print '<div class="normal">Processing topics</div>';
			foreach ( $manualData['sections'] as $sectionName => $section ) {
				print "<div class=\"normal\">Processing section $sectionName</div>" ;
				foreach ( $section['topics'] as $topic ) {
					// Update log file
					$fp = fopen( $path, "w+" );
					fputs( $fp, "Renaming topics in manual $manualName<br />"
						. "Completed $numOfTopicsCompleted of $numOfTopics Total: " 
						. ( (int)($numOfTopicsCompleted / $numOfTopics * 100) ) . '%' );
					fclose( $fp );
					try {
						print '<div class="normal">Attempting to update topic ' . $topic['title'] . '...';
						PonyDocsRenameVersionEngine::changeVersionOnTopic(
							$topic['title'], $sourceVersion, $targetVersion );
							$logFields = "action=topic status=success product=$productName manual=$manualName "
								. "title={$topic['title']} sourceVersion=$sourceVersionName targetVersion=$targetVersionShortName";
						error_log( 'INFO [' . __METHOD__ . "] [RenameVersion] $logFields" );
						print 'Complete</div>';
					} catch( Exception $e ) {
						$logFields = "action=topic status=failure error={$e->getMessage()} product=$productName manual=$manualName "
							. "title={$topic['title']} sourceVersion=$sourceVersionName targetVersion=$targetVersionShortName";
						error_log( 'WARNING [' . __METHOD__ ."] [RenameVersion] $logFields" );
						print '</div><div class="error">Exception: ' . $e->getMessage() . '</div>';
					}
					$numOfTopicsCompleted++;
				}
			}

			// Now we can update the TOC
			// Determine if TOC already exists for target version.
			if ( !PonyDocsBranchInheritEngine::TOCExists( $product, $manual, $sourceVersion ) ) {
				print '<div class="normal">TOC Does not exist for Manual ' . $manual->getShortName()
					. ' with version ' . $targetVersion->getVersionShortName() . '</div>';
			} else {
				try {
					print '<div class="normal">Attempting to update TOC...';
					PonyDocsRenameVersionEngine::changeVersionOnTOC( $product, $manual, $sourceVersion, $targetVersion);
					$logFields = "action=TOC status=success product=$productName manual=$manualName "
						. "sourceVersion=$sourceVersionName targetVersion=$targetVersionShortName";
					error_log( 'INFO [' . __METHOD__ ."] [RenameVersion] $logFields" );
					print 'Complete</div>' ;
				} catch ( Exception $e ) {
					$logFields = "action=TOC status=failure error={$e->getMessage()} product=$productName manual=$manualName "
						. "sourceVersion=$sourceVersionName targetVersion=$targetVersionShortName";
					error_log( 'WARNING [' . __METHOD__ ."] [RenameVersion] $logFields" );
					print '</div><div class="error">Exception: ' . $e->getMessage() . '</div>';
				}
			}
		
		}

		list ( $msec, $sec ) = explode( ' ', microtime() ); 
		$endTime = (float)$msec + (float)$sec; 
		print "Done with $manualName! Execution Time: " . round($endTime - $startTime, 3) . ' seconds<br />';
		//WEB-10792, Clear TOCCACHE for the target version only, each Manual at a time
		PonyDocsTOC::clearTOCCache($manual, $targetVersion, $product);		
		//Also clear the NAVCache for the target version
		PonyDocsProductVersion::clearNAVCache($targetVersion);		
		unlink($path);
		$buffer = ob_get_clean();
		return $buffer;
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.
	 */
	public function execute() {
		error_log(__METHOD__);
		global $wgOut, $wgArticlePath, $wgScriptPath;
		global $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$this->setHeaders();
		$wgOut->setPagetitle( 'Documentation Rename Version' );

		$forceProduct = PonyDocsProduct::GetSelectedProduct();
		$ponydocs = PonyDocsWiki::getInstance();
		$products = $ponydocs->getProductsForTemplate();

		// Security Check
		$authProductGroup = PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $forceProduct );
		$groups = $wgUser->getGroups();
		if ( !in_array( $authProductGroup, $groups ) ) {
			$wgOut->addHTML( '<p>Sorry, but you do not have permission to access this Special page.</p>' );
			return;
		}

		ob_start();

		// Grab all versions available for product
		// We need to get all versions from PonyDocsProductVersion
		$versions = PonyDocsProductVersion::GetVersions($forceProduct); ?>

		<input type="hidden" id="force_product" value="<?php echo $forceProduct; ?>" />
		<div id="renameversion">
		<a name="top"></a>
		<div class="versionselect">
			<h1>Rename Version Console</h1>
			Select product, source version, and target version.
			You will be able to approve the list of manuals to rename before you launch the process.
			<h2>Choose a Product</h2>

			<?php
			if ( !count($products) ) {
				print "<p>No products defined.</p>";
			} else { ?>
				<div class="product">
					<select id="docsProductSelect1" name="selectedProduct" onChange="AjaxChangeProduct1();">
						<?php
						foreach ( $products as $idx => $data ) {
							echo '<option value="' . $data['name'] . '" ';
							if( !strcmp( $data['name'], $forceProduct ) ) {
								echo 'selected';
							}
							echo '>' . $data['label'] . '</option>';
						} ?>
					</select>
				</div>

				<script language="javascript">
					function AjaxChangeProduct1_callback( o ) {
						document.getElementById( 'docsProductSelect1' ).disabled = true;
						var s = new String( o.responseText );
						document.getElementById( 'docsProductSelect1' ).disabled = false;
						window.location.href = s;
					}

					function AjaxChangeProduct1() {
						var productIndex = document.getElementById( 'docsProductSelect1' ).selectedIndex;
						var product = document.getElementById( 'docsProductSelect1' )[productIndex].value;
						var title = '<?= $_SERVER['REQUEST_URI'] ?>'; // TODO fix this title
						var force = true;
						sajax_do_call( 'efPonyDocsAjaxChangeProduct', [product, title, force], AjaxChangeProduct1_callback, true);
					}
				</script>

				<?php
			} ?>

			<h2>Choose a Source Version</h2>
			<select name="version" id="versionselect_sourceversion">
				<?php
				foreach ( $versions as $version ) { ?>
					<option value="<?php echo $version->getVersionShortName();?>">
						<?php echo $version->getVersionShortName() . " - " . $version->getVersionStatus();?></option>
					<?php
				} ?>
			</select>

			<h2>Choose a Target Version</h2>
			<select name="version" id="versionselect_targetversion">
				<?php
				foreach( $versions as $version ) { ?>
					<option value="<?php echo $version->getVersionShortName();?>">
						<?php echo $version->getVersionShortName() . " - " . $version->getVersionStatus();?></option>
					<?php
				} ?>
			</select>
			<div>
				<input type="button" id="versionselect_submit" value="Fetch Manuals" />
			</div>
		</div>

		<div class="submitrequest" style="display: none;">
			<div id="manuallist"></div>
			<input type="button" id="renameversion_submit" value="Rename Version" />
			<div id="progressconsole"></div>
		</div>
		
		<div class="completed" style="display: none;">
			<p class="summary">
				<strong>Source Version:</strong> <span class="sourceversion"></span>
				<strong>Target Version:</strong> <span class="targetversion"></span>
			</p>

			<h2>Process Complete</h2>
			The following is the log of the processed job.
			Look it over for any potential issues that may have occurred during the Rename Version job.
			<div>
				<div class="logconsole" style="font-family: monospace; font-size: 10px;"></div>
			</div>
		</div>
		</div>
		<?php
		$buffer = ob_get_clean();
		$wgOut->addHTML($buffer);
		return TRUE;
	}
}
