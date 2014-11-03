<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'PonyDocs MediaWiki Extension' );
}

/**
 * Needed since we subclass it; it doesn't seem to be loaded elsewhere.
 */
require_once( "$IP/includes/SpecialPage.php" );

/**
 * Register our 'Special' page so it is listed and accessible.
 */
$wgSpecialPages['RenameProduct'] = 'SpecialRenameProduct';

// Ajax Handlers
$wgAjaxExportList[] = 'RenameProduct::ajaxFetchJobID';
$wgAjaxExportList[] = 'RenameProduct::ajaxFetchJobProgress';
$wgAjaxExportList[] = 'RenameProduct::ajaxStartProductRename';
$wgAjaxExportList[] = 'RenameProduct::ajaxProcessManual';
$wgAjaxExportList[] = 'RenameProduct::ajaxCompleteProductRename';

/**
 * The Special page which handles the UI for renaming versions
 */
class SpecialRenameProduct extends SpecialPage {
	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 *
	 * @returns SpecialRenameProduct
	 */
	public function __construct() {
		SpecialPage::__construct( 'RenameProduct', 'edit' );
	}
	
	/**
	 * Returns a human readable description of this special page.
	 *
	 * @returns string
	 */
	public function getDescription() {
		return 'PonyDocs Rename Product';
	}

	/**
	 * AJAX method to fetch/initialize uniqid to identify this session. Used to build progress report.
	 *
	 * @returns string The unique id for this job.
	 */
	public static function ajaxFetchJobID() {
		$uniqid = uniqid( 'ponydocsRenameProduct', true );
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
	 * Complete the rename process by deleteing the old Product and its Manual and Version management pages
	 * @param string $jobId
	 * @param string $sourceProductName
	 * @return string Full job log of the process by printing to stdout.
	 */
	public static function ajaxCompleteProductRename( $jobId, $sourceProductName, $targetProductName ) {
		// TODO: Validate
		// That user has access
		// That source product exists and is not static
		// That source product has no TOCs or Topics
		// That target product does not exist
		// What can we do to make this more safe? Maybe we can check that the job Id is valid?

		$sourceProduct = PonyDocsProduct::getProductByShortName($sourceProductName);
		$sourceProduct->moveVersionsToAnotherProduct();
		$sourceProduct->moveManualsToAnotherProduct();
		$sourceProduct->rename();

		// TODO: Log
		// TODO: Return
	}
	
	/**
	 * Processes a rename product request for a single manual
	 *
	 * @param string $jobID The unique id for this job (see ajaxFetchJobID)
	 * @param string $manualName string manual short name
	 * @param string $sourceProductName string String representation of the source version
	 * @param string $targetProductName string String representaiton of the target version
	 * @return string Full job log of the process by printing to stdout.
	 */
	public static function ajaxProcessManual( $jobID, $manualName, $sourceProductName, $targetProductName ) {
		global $wgScriptPath;
		// TODO: Don't use ob_start and print statements, write to a variable
		ob_start();

		list ( $msec, $sec ) = explode( ' ', microtime() ); 
		$startTime = (float)$msec + (float)$sec; 

		$logFields = "action=start status=success manual=$manualName sourceProduct=$sourceProductName"
			. " targetProduct=$targetProductName";
		error_log( 'INFO [' . __METHOD__ . "] [RenameProduct] $logFields" );

		// Validate
		// TODO: that the user has access
		// What can we do to make this more safe? Maybe we can check that the job Id is valid?
		$sourceProduct = PonyDocsProduct::GetProductByShortName( $sourceProductName );
		$targetProduct = PonyDocsProduct::GetProductByShortName( $targetProductName );
		$manual = PonyDocsProductManual::getManualByShortName( $souceProductName, $manualName );
		// Source Product and Manual should exist, Target Product should not
		if ( !$sourceProduct || !$manual || $targetProduct ) {
			$result = array( 'success', FALSE );
			$result = json_encode( $result );
			// TODO: Don't return in the middle
			return $result;
		}

		print "Beginning process job for manual: $manualName<br />";
		print "Source product is $sourceProductName<br />";
		print "Target product is $targetProductName<br />";
		
		// Get topics
	
		// Update log file
		// TODO: Move this to a job handling class
		$path = PonyDocsExtension::getTempDir() . $jobID;
		$fp = fopen( $path, "w+" );
		fputs( $fp, "Getting Topics for $manualName" );
		fclose( $fp );

		$allTocTitles = $manual->getAllTocs();
		$allTocs = array();
		$manualTopics = array();
		

		foreach ( $allTocs as $tocName ) {
			$TOC = new PonyDocsTOC( $manual, $sourceVersion, $product );
			list( $toc, $prev, $next, $start ) = $TOC->loadContent();
			// Time to iterate through all the items.
			$section = '';
			foreach ( $toc as $tocItem ) {
				// actual topic
				if ( $tocItem['level'] == 1 ) {
					$manualTopics[$tocItem['title']] = 1;
				}
			}
			$allTocs = $TOC;
		}

		$logFields = "action=topics status=success product=$productName manual=$manualName "
			. "sourceVersion=$sourceVersionName targetVersion=$targetVersionName";
		error_log( 'INFO [' . __METHOD__ . "] [RenameProduct] $logFields" );

		// Enable speed processing to avoid any unnecessary processing on topics modified by this tool.
		// TODO: I'm not 100% sure this is necessary or proper here -RU
		PonyDocsExtension::setSpeedProcessing( TRUE );

		// Determine how many topics there are to process so that we can keep track of progress
		$numOfTopics = size( $manualTopics );
		$numOfTopicsCompleted = 0;

		foreach ( array_keys( $manualTopics ) as $topicTitle ) {
			// First update all the topics
			print '<div class="normal">Processing topics</div>';
			
			// Update log file
			$fp = fopen( $path, "w+" );
			fputs( $fp, "Renaming topics in manual $manualName<br />"
				. "Completed $numOfTopicsCompleted of $numOfTopics Total: " 
				. ( (int)($numOfTopicsCompleted / $numOfTopics * 100) ) . '%' );
			fclose( $fp );
			
			try {
				print '<div class="normal">Attempting to update topic ' . $topicTitle . '...';
				// TODO: Instantiate the topic somehow - how do we get an article? Who makes a new Topic?
				// $topic = new PonyDocsProductTopic();
				// $topic->moveTopicToAnotherProduct($targetProductName);

				$logFields = "action=topic status=success product=$productName manual=$manualName "
					. "title={$topic['title']} sourceVersion=$sourceVersionName targetVersion=$targetVersionName";
				error_log( 'INFO [' . __METHOD__ . "] [RenameProduct] $logFields" );

				print 'Complete</div>';
			} catch( Exception $e ) {
				$logFields = "action=topic status=failure error={$e->getMessage()} product=$productName manual=$manualName "
					. "title={$topic['title']} sourceVersion=$sourceVersionName targetVersion=$targetVersionName";
				error_log( 'WARNING [' . __METHOD__ ."] [RenameProduct] $logFields" );
				print '</div><div class="error">Exception: ' . $e->getMessage() . '</div>';
			}
			$numOfTopicsCompleted++;
		}

		// Now we can update the TOCs
		foreach ( $allTocs as $toc ) {
			try {
				print '<div class="normal">Attempting to update TOC...';
				$toc->moveTocToAnotherProduct( $targetProductName );

				$logFields = "action=TOC status=success product=$productName manual=$manualName "
					. "sourceVersion=$sourceVersionName targetVersion=$targetVersionName";
				error_log( 'INFO [' . __METHOD__ ."] [RenameProduct] $logFields" );

				print 'Complete</div>' ;
			} catch ( Exception $e ) {
				$logFields = "action=TOC status=failure error={$e->getMessage()} product=$productName manual=$manualName "
					. "sourceVersion=$sourceVersionName targetVersion=$targetVersionName";
				error_log( 'WARNING [' . __METHOD__ ."] [RenameProduct] $logFields" );

				print '</div><div class="error">Exception: ' . $e->getMessage() . '</div>';
			}
		}

		list ( $msec, $sec ) = explode( ' ', microtime() ); 
		$endTime = (float)$msec + (float)$sec; 
		print "Done with $manualName! Execution Time: " . round($endTime - $startTime, 3) . ' seconds<br />';

		unlink($path);
		$buffer = ob_get_clean();
		return $buffer;
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.
	 */
	public function execute() {
		global $wgArticlePath, $wgPonyDocsEmployeeGroup, $wgScriptPath, $wgOut, $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$this->setHeaders();
		$wgOut->setPagetitle( 'PonyDocs Rename Product' );
		$ponydocs = PonyDocsWiki::getInstance( PonyDocsProduct::GetSelectedProduct() );
		$products = $ponydocs->getProductsForTemplate();

		// Validate
		// Check they are an employee user (we'll check specific docteam groups later)
		$groups = $wgUser->getGroups();
		if ( !in_array( $wgPonyDocsEmployeeGroup, $groups ) ) {
			$wgOut->addHTML( '<p>Sorry, but you do not have permission to access this Special page.</p>' );
			return;
		}

		ob_start(); ?>

		<div id="renameProduct">
			<a name="top"></a>
			<div class="selectProduct">
				<h1>PonyDocs Rename Product</h1>
				Select product, provide new name.

				<h2>Choose Product to Rename</h2>

				<?php
				if ( !count($products) ) {
					print "<p>No products defined.</p>";
				} else { ?>
					<div>
						<select id="sourceProduct" name="sourceProduct">
							<?php
								$productNames = array_keys( $products );
								sort( $productNames );
								foreach ( $productNames as $productName ) {
								echo '<option value="' . $productName . '" ';
								echo '>' . $productName . '</option>';
							} ?>
						</select>
					</div>
				
					<div>
						<label for="targetProduct">Rename to:</label>	
						<input type="text" id="targetProduct" name="targetProduct">
					</div>
					
					<?php
				} ?>

				<div>
					<input type="button" id="submitProduct" value="Rename Product" />
				</div>
			</div>

			<div class="completed" style="display: none;">
				<p class="summary">
					<strong>Source Product:</strong> <span class="sourceproduct"></span>
					<strong>Target Product:</strong> <span class="targetproduct"></span>
				</p>

				<h2>Process Complete</h2>
				The following is the log of the processed job.
				Look it over for any potential issues that may have occurred during the Rename Product job.
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