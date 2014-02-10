<?php
if( !defined( 'MEDIAWIKI' ))
	die( 'PonyDocs MediaWiki Extension' );

/**
 * Needed since we subclass it;  it doesn't seem to be loaded elsewhere.
 */
require_once( "$IP/includes/SpecialPage.php" );

/**
 * Register our 'Special' page so it is listed and accessible.
 */
$wgSpecialPages['RenameVersion'] = 'SpecialRenameVersion';

// Ajax Handlers
$wgAjaxExportList[] = 'SpecialRenameVersion::ajaxProcessRequest';
$wgAjaxExportList[] = 'SpecialRenameVersion::ajaxFetchJobID';
$wgAjaxExportList[] = 'SpecialRenameVersion::ajaxFetchJobProgress';

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
		$uniqid = uniqid( 'ponydocsrenameversion', true );
		$path = PonyDocsExtension::getTempDir() . $jobID;
		$progress = file_get_contents( $path );
		if ( $progress === false ) {
			$progress = 'Unable to fetch Job Progress.';
		}
		return $progress;
	}

	/**
	 * Processes a rename version job request.
	 *
	 * @param $jobID The unique id for this job (see ajaxFetchJobID)
	 * @param $productName string product short name
	 * @param $sourceVersion string String representation of the source version
	 * @param $targetVersion string String representaiton of the target version
	 * @param $topics string JSON array representation of all topics
	 * @return string Full job log of the process by printing to stdout.
	 */
	public static function ajaxProcessRequest( $jobID, $productName, $sourceVersion, $targetVersion, $topics ) {
		global $wgScriptPath;
		ob_start();

		$topics = json_decode( $topics, true );
		list ( $msec, $sec ) = explode( ' ', microtime() ); 
		$startTime = (float)$msec + (float)$sec; 

		if ( $topics == FALSE ) {
			print 'Failed to read request.';
			return TRUE ;
		}

		print "Beginning process job for source version: $productName:$sourceVersion<br />";
		print "Target version is: $targetVersion<br />";

		// Enable speed processing to avoid any unnecessary processing on topics modified by this tool.
		// TODO: I'm not 100% sure this is necessary here
		PonyDocsExtension::setSpeedProcessing( TRUE );

		$product = PonyDocsProduct::GetProductByShortName( $productName );
		$sourceVersion = PonyDocsProductVersion::GetVersionByName( $productName, $sourceVersion );
		$targetVersion = PonyDocsProductVersion::GetVersionByName( $productName, $targetVersion );

		// Determine how many topics there are to process.
		$numOfTopics = 0;
		$numOfTopicsCompleted = 0;

		foreach ( $topics as $manualIndex => $manualData ) {
			foreach( $manualData['sections'] as $sectionName => $topicList ) {
				// The following is a goofy fix for some browsers.
				// Sometimes the JSON comes along with null values for the first element. 
				// It's just an additional element, so we can drop it.
				if ( empty( $topicList[0]['text'] )) {
					array_shift( $topics[$manualIndex]['sections'][$sectionName] );
				}
				$numOfTopics += count( $topics[$manualIndex]['sections'][$sectionName] );
			}
		}

		$lastTopicTarget = null;

		foreach ( $topics as $manualName => $manualData ) {
			$manual = PonyDocsProductManual::GetManualByShortName( $productName, $manualName );
			// Determine if TOC already exists for target version.
			if( !PonyDocsBranchInheritEngine::TOCExists( $product, $manual, $targetVersion )) {
				print '<div class="normal">TOC Does not exist for Manual ' . $manual->getShortName()
					. ' for version ' . $targetVersion->getVersionName() . '</div>';
			} else {
				try {
					print '<div class="normal">Attempting to update TOC for target version.</div>';
					PonyDocsRenameVersionEngine::updateTOC( $product, $manual, $targetVersion);
					print '<div class="normal">Complete</div>' ;
				} catch ( Exception $e ) {
					print '<div class="error">Exception: ' . $e->getMessage() . '</div>';
				}
			}

			// Okay, now let's go through each of the topics and update the version category
			print "Processing topics.\n" ;
			$path = PonyDocsExtension::getTempDir() . $jobID;
			foreach ( $manualData['sections'] as $sectionName => $topicList ) {
				print "<div class=\"normal\">Processing section $sectionName</div>" ;
				foreach ( $topicList as $topic ) {
					// Update log file
					$fp = fopen( $path, "w+" );
					fputs( $fp, "Completed $numOfTopicsCompleted of $numOfTopics Total: " 
						. ((int)($numOfTopicsCompleted / $numOfTopics * 100)) . '%' );
					fclose( $fp );
					try {
						print '<div class="normal">Attempting to update topic ' . $topic['title'] . '</div>';
						PonyDocsRenameVersionEngine::updateTopic($topic['title'], $targetVersion, $sectionName, $topic['text'], true, false, true);
					} catch( Exception $e ) {
						print '<div class="error">Exception: ' . $e->getMessage() . '</div>';
					}
					$numOfTopicsCompleted++;
				}
			}
		}
		list ( $msec, $sec ) = explode( ' ', microtime() ); 
		$endTime = (float)$msec + (float)$sec; 
		print( "All done!\n" );
		print( 'Execution Time: ' . round($endTime - $startTime, 3) . ' seconds' );

		// Okay, let's start the process!
		unlink($path);
		$buffer = ob_get_clean();
		return $buffer;
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.
	 */
	public function execute() 	{
		global $wgOut, $wgArticlePath, $wgScriptPath;
		global $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$this->setHeaders();
		$wgOut->setPagetitle( 'Documentation Rename Version' );

		$forceProduct = PonyDocsProduct::GetSelectedProduct();
		$ponydocs = PonyDocsWiki::getInstance( $forceProduct );
		$products = $ponydocs->getProductsForTemplate();

		// Security Check
		$authProductGroup = PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $forceProduct );
		$groups = $wgUser->getGroups();
		if ( !in_array( $authProductGroup, $groups )) {
			$wgOut->addHTML( '<p>Sorry, but you do not have permission to access this Special page.</p>' );
			return;
		}

		ob_start();

		// Grab all versions available for product
		// We need to get all versions from PonyDocsProductVersion
		$versions = PonyDocsProductVersion::GetVersions($forceProduct); ?>

		<input type="hidden" id="force_product" value="<?php echo $forceProduct; ?>" />
		<div id="docrenameversion">
		<a name="top"></a>
		<div class="versionselect">
			<h1>Rename Version Console</h1>

			Begin by selecting your product, source version material and a target version below.
			You will then be presented with additional screens to specify branch and inherit behavior.

			<h2>Choose a Product</h2>

			<?php
			if (!count($products)) {
				print "<p>No products defined.</p>";
			} else { ?>
				<div class="product">
					<select id="docsProductSelect1" name="selectedProduct" onChange="AjaxChangeProduct1();">
						<?php
						foreach ( $products as $idx => $data ) {
							echo '<option value="' . $data['name'] . '" ';
							if( !strcmp( $data['name'], $forceProduct )) {
								echo 'selected';
							}
							echo '>' . $data['label'] . '</option>';
						} ?>
					</select>
				</div>

				<script language="javascript">
					function AjaxChangeProduct1_callback( o ) {
						document.getElementById('docsProductSelect1').disabled = true;
						var s = new String( o.responseText );
						document.getElementById('docsProductSelect1').disabled = false;
						window.location.href = s;
					}

					function AjaxChangeProduct1( ) {
						var productIndex = document.getElementById('docsProductSelect1').selectedIndex;
						var product = document.getElementById('docsProductSelect1')[productIndex].value;
						var title = '<?= $_SERVER['REQUEST_URI'] ?>'; // TODO fix this title
						var force = true;
						sajax_do_call( 'efPonyDocsAjaxChangeProduct', [product,title,force], AjaxChangeProduct1_callback,true);
					}
				</script>

				<?php
			} ?>

			<h2>Choose a Source Version</h2>
			<select name="version" id="versionselect_sourceversion">
				<?php
				foreach ( $versions as $version ) { ?>
					<option value="<?php echo $version->getVersionName();?>">
						<?php echo $version->getVersionName() . " - " . $version->getVersionStatus();?></option>
					<?php
				} ?>
			</select>

			<h2>Choose a Target Version</h2>
			<select name="version" id="versionselect_targetversion">
				<?php
				foreach( $versions as $version ) { ?>
					<option value="<?php echo $version->getVersionName();?>">
						<?php echo $version->getVersionName() . " - " . $version->getVersionStatus();?></option>
					<?php
				} ?>
			</select>
			<div>
				<input type="button" id="versionselect_submit" value="Process Request" />
				<div id="progressconsole"></div>
			</div>
		</div>

		<div class="completed" style="display: none;">
			<p class="summary">
				<strong>Source Version:</strong> <span class="sourceversion"></span>
				<strong>Target Version:</strong> <span class="targetversion"></span>
			</p>

			<h2>Process Complete</h2>
			The following is the log of the processed job.  Look it over for any potential issues that may have 
			occurred during the Rename Version job.
			<div>
				<div class="logconsole" style="font-family: monospace; font-size: 10px;"></div>
			</div>
		</div>
		</div>
		<?php
		$buffer = ob_get_clean();
		$wgOut->addHTML($buffer);
		return true;
	}
}