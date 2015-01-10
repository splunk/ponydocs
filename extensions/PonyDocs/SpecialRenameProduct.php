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
$wgAjaxExportList[] = 'RenameProduct::ajaxProcessManual';
$wgAjaxExportList[] = 'RenameProduct::ajaxFinishProductRename';

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
	 * Processes a rename product request for a single manual
	 *
	 * @param string $jobId The unique id for this job (see ajaxFetchJobId)
	 * @param string $manualName string manual short name
	 * @param string $sourceProductName string String representation of the source version
	 * @param string $targetProductName string String representaiton of the target version
	 * @return string Full job log of the process by printing to stdout.
	 */
	public static function ajaxProcessManual( $jobId, $manualName, $sourceProductName, $targetProductName ) {
		global $wgScriptPath, $wgTitle;
		$error = FALSE;
		ob_start();

		list ( $msec, $sec ) = explode( ' ', microtime() ); 
		$startTime = (float)$msec + (float)$sec; 

		$logFields = "action=start status=success manual=$manualName sourceProduct=$sourceProductName"
			. " targetProduct=$targetProductName";
		error_log( 'INFO [' . __METHOD__ . "] [RenameProduct] $logFields" );

		// Validate
		// That user is in the docteam group for both products
		$groups = $wgUser->getGroups();
		$sourceProductGroup =
			PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $sourceProductName );
		if ( !in_array( $sourceProductGroup, $groups ) ) {
			$errors[] = "You do not have permission to rename $sourceProductName.";
		}
		$targetProductGroup =
			PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $targetProductName );
		if ( !in_array( $sourceProductGroup, $groups ) ) {
			$errors[] = "You do not have permission to rename a Product to $targetProductName.";
		}
		// What can we do to make this more safe? Maybe we can check that the job Id is valid?
		$sourceProduct = PonyDocsProduct::GetProductByShortName( $sourceProductName );
		$targetProduct = PonyDocsProduct::GetProductByShortName( $targetProductName );
		$manual = PonyDocsProductManual::getManualByShortName( $souceProductName, $manualName );
		// Source Product and Manual should exist, Target Product should not
		if ( !$sourceProduct || !$manual || $targetProduct ) {
			$error = TRUE;
			$result = array( 'success', FALSE );
			$result = json_encode( $result );
		}
		
		if (!$error) {

			print "Beginning process job for manual: $manualName<br />";
			print "Source product is $sourceProductName<br />";
			print "Target product is $targetProductName<br />";

			// Get topics

			// Update log file
			$path = PonyDocsExtension::getTempDir() . $jobId;
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
					$title = Title::newFromText( $title );
					$wgTitle = $title;
					$article = new Article( $title );
					$topic = new PonyDocsTopic( $article );
					$topic->moveTopicToAnotherProduct($targetProductName);

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
			$result = ob_get_clean();
		}

		return $result;
	}

	/**
	 * Complete the rename process by deleteing the old Product and its Manual and Version management pages
	 * @param string $jobId
	 * @param string $sourceProductName
	 * @return string Full job log of the process by printing to stdout.
	 */
	public static function ajaxFinishProductRename( $jobId, $sourceProductName, $targetProductName ) {
		global $wgUser;

		$buffer = "";
		$errors = array();
		$path = PonyDocsExtension::getTempDir() . $jobID;
		
		// Opening logs
		
		$logFields = "action=start status=success sourceProduct=$sourceProductName targetProduct=$targetProductName";
		error_log( 'INFO [' . __METHOD__ . "] [RenameProduct] $logFields" );

		$buffer .= "Finishing Product Rename job<br />";
		$buffer .= "Source Product is $sourceProductName<br />";
		$buffer .= "Target Product is $targetProductName<br />";
		
		$fp = fopen( $path, "w+" );
		fputs( $fp, "Finishing Product Rename" );
		fclose( $fp );

		// Validate
		// That user is in the docteam group for both products
		$groups = $wgUser->getGroups();
		$sourceProductGroup =
			PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $sourceProductName );
		if ( !in_array( $sourceProductGroup, $groups ) ) {
			$errors[] = "You do not have permission to rename $sourceProductName.";
		}
		$targetProductGroup =
			PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $targetProductName );
		if ( !in_array( $sourceProductGroup, $groups ) ) {
			$errors[] = "You do not have permission to rename a Product to $targetProductName.";
		}

		// That source product exists and is not static
		$sourceProduct = PonyDocsProduct::GetProductByShortName($sourceProductName);
		if ( !$sourceProduct || $sourceProduct->isStatic() ) {
			$errors[] = "$sourceProductName does not exist or is static.";
		}
		// That source product has no TOCs
		$manuals = PonyDocsProductManual::getDefinedManuals( $sourceProductName );
		foreach ( $manuals as $manual ) {
			if ( $manual->getAllTocs() ) {
				$errors[] = "$sourceProduct still has TOCs. Please try the rename again.";
			}
		}
		// That target product does not exist
		if ( PonyDocsProduct::GetProductByShortName($targetProductName ) ) {
			$errors[] = "$targetProductName already exists.";
		}
		// TODO: That the jobId is valid
		//       We need to make this work across multiple webheads before we can validate it
		
		if ( !$errors ) {
			try {
				$fp = fopen( $path, "w+" );
				fputs( $fp, "Moving Versions..." );
				fclose( $fp );
				$buffer .= '<div class="normal">Moving Versions...';
				$sourceProduct->moveVersionsToAnotherProduct();
				$buffer .= 'Complete</div>';
				$logFields = 
					"action=moveVersions status=success sourceProduct=$sourceProductName targetProduct=$targetProductName";
				error_log( 'INFO [' . __METHOD__ . "] [RenameProduct] $logFields" );

				$fp = fopen( $path, "w+" );
				fputs( $fp, "Moving Manuals..." );
				fclose( $fp );
				$buffer .= '<div class="normal">Moving Manuals...';
				$sourceProduct->moveManualsToAnotherProduct();
				$buffer .= 'Complete</div>';
				$logFields =
					"action=moveManuals status=success sourceProduct=$sourceProductName targetProduct=$targetProductName";
				error_log( 'INFO [' . __METHOD__ . "] [RenameProduct] $logFields" );

				$fp = fopen( $path, "w+" );
				fputs( $fp, "Renaming Product..." );
				fclose( $fp );
				$buffer .= '<div class="normal">Renaming Product...';
				$sourceProduct->rename();
				$buffer .= 'Complete</div>';
				$logFields =
					"action=renameProduct status=success sourceProduct=$sourceProductName targetProduct=$targetProductName";
				error_log( 'INFO [' . __METHOD__ . "] [RenameProduct] $logFields" );
			} catch ( Exception $e ) {
				$logFields = "action=error status=error sourceProduct=$sourceProductName targetProduct=$targetProductName"
					. " error={$e->getMessage()}";
				error_log( 'WARNING [' . __METHOD__ . "] [RenameProduct] $logFields" );
				$buffer .= '</div><div class="error">Exception: ' . $e->getMessage() . '</div>';
			}
		} else {
			$fp = fopen( $path, "w+" );
			fputs( $fp, "Validation Errors Found." );
			fclose( $fp );
			$buffer .= '<div class="error">Validation Error: ' . implode('<br>', $errors) . '</div>';
			$logFields = "action=validation status=error sourceProduct=$sourceProductName targetProduct=$targetProductName"
				. 'errors="' . implode( ",", $errors ) . '"';
			error_log( 'WARNING [' . __METHOD__ . "] [RenameProduct] $logFields" );
		}
		
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
		$wgOut->addScriptFile($wgScriptPath . "/extensions/PonyDocs/js/RenameProduct	.js");
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
					<input type="button" id="submitRenameProduct" value="Rename Product" />
				</div>
			</div>

			<div id="progressconsole"></div>
			
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