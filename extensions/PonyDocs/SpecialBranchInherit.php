<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

/**
 * Needed since we subclass it;  it doesn't seem to be loaded elsewhere.
 */
require_once( "$IP/includes/specialpage/SpecialPage.php" );

/**
 * Register our 'Special' page so it is listed and accessible.
 */
$wgSpecialPages['BranchInherit'] = 'SpecialBranchInherit';

// Ajax Handlers
$wgAjaxExportList[] = "SpecialBranchInherit::ajaxFetchManuals";
$wgAjaxExportList[] = "SpecialBranchInherit::ajaxFetchTopics";
$wgAjaxExportList[] = "SpecialBranchInherit::ajaxProcessRequest";
$wgAjaxExportList[] = "SpecialBranchInherit::ajaxFetchJobID";
$wgAjaxExportList[] = "SpecialBranchInherit::ajaxFetchJobProgress";

/**
 * The Special page which handles the UI for branch/inheritance functionality
 */
class SpecialBranchInherit extends SpecialPage
{
	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 *
	 * @returns SpecialBranchInherit
	 */
	public function __construct( )
	{
		SpecialPage::__construct( "BranchInherit" );
	}

	/**
	 * @Overridden
	 * Check for Permission
	 * @param String $productName Ponydocs Product name
	 * 
	 * @return boolean
	 */
	public function userCanExecute( $productName = "" ) {
		global $wgUser;	
		if ( empty( $productName ) ) {
			$productName = PonyDocsProduct::GetSelectedProduct();
		}
		// Security Check
		$authProductGroup = PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $productName );
		$groups = $wgUser->getGroups( );
		if ( !in_array( $authProductGroup, $groups ) ) {			
			return FALSE;
		}
		return TRUE;
	}
	
	/**
	 * Returns a human readable description of this special page.
	 *
	 * @returns string
	 */
	public function getDescription( )
	{
		return 'Documentation Branch And Inherit Controller';
	}

	/**
	 * AJAX method to fetch manuals for a specified product and version
	 *
	 * @param $productName string
	 * @param $versionName string
 	 * @returns string JSON representation of the manuals
	 */
	public static function ajaxFetchManuals( $productName, $versionName = NULL ) {

		$perms = SpecialBranchInherit::userCanExecute( $productName );
		if( !$perms ) {
			$result = array("success", false);
			$result = json_encode($result);
			return $result;
		}	
		PonyDocsProductVersion::LoadVersionsForProduct( $productName );

		if ( !is_null( $versionName ) ) {
			PonyDocsProductVersion::SetSelectedVersion( $productName, $versionName);
			$manuals = PonyDocsProductManual::LoadManualsForProduct( $productName, TRUE);
		} else {
			$manuals = PonyDocsProductManual::GetDefinedManuals( $productName );
		}

		$response = array();
		foreach ( $manuals as $manual ) {
			if ( !$manual->isStatic() ) {
				$response[$manual->getShortName()] = array( "shortname" => $manual->getShortName(), "longname" => $manual->getLongName() );
			}
		}

		// JS eval only works with json representing objects at the top level (i.e. associative arrays) if they parenthesized
		return "(" . json_encode( $response ) . ")";
	}

	/**
	 * AJAX method to fetch topics for a specified version and manuals
	 *
	 * @param $sourceProductName string product short name
	 * @param $sourceVersionName string String representation of the source version
	 * @param $targetProductName string product short name
	 * @param $targetVersionName string String representation of the target version
	 * @param $manuals string Comma seperated list of manuals to retrieve from
	 * @param $forcedTitle string A specific title to pull from and nothing else (for individual branch/inherit)
	 * @returns string JSON representation of all titles requested
	 */
	public static function ajaxFetchTopics(
		$sourceProductName, $sourceVersionName, $targetProductName, $targetVersionName, $manuals, $forcedTitle = NULL ) {
		$perms =( SpecialBranchInherit::userCanExecute( $sourceProductName ) && SpecialBranchInherit::userCanExecute( $targetProductName ) );
		if( !$perms ) {
			$result = array("success", false);
			$result = json_encode($result);
			return $result;
		}
		PonyDocsProduct::LoadProducts( TRUE );
		$product = PonyDocsProduct::GetProductByShortName( $sourceProductName );
		PonyDocsProductVersion::LoadVersionsForProduct( TRUE, TRUE );
		$sourceVersion = PonyDocsProductVersion::GetVersionByName( $sourceProductName, $sourceVersionName );
		$targetVersion = PonyDocsProductVersion::GetVersionByName( $targetProductName, $targetVersionName );
		if (!$sourceVersion || !$targetVersion) {
			$result = array("success", false);
			$result = json_encode($result);
			return $result;
		}
		$result = array();
		// Okay, get manual by name.
		$manuals = explode(",", $manuals);
		foreach($manuals as $manualName) {
			$manual = PonyDocsProductManual::GetManualByShortName( $sourceProductName, $manualName );
			$result[$manualName] = array();
			$result[$manualName]['meta'] = array();
			// Load up meta.
			$result[$manualName]['meta']['text'] = $manual->getLongName();
			// See if TOC exists for target version.
			$result[$manualName]['meta']['toc_exists'] = PonyDocsBranchInheritEngine::TOCExists($product, $manual, $targetVersion);
			$result[$manualName]['sections'] = array();
			// Got the version and manual, load the TOC.
			$ponyTOC = new PonyDocsTOC($manual, $sourceVersion, $product);
			list($toc, $prev, $next, $start) = $ponyTOC->loadContent();
			// Time to iterate through all the items.
			$section = '';
			foreach($toc as $tocItem) {
				if($tocItem['level'] == 0) {
					$section = $tocItem['text'];
					$result[$manualName]['sections'][$section] = array();
					$result[$manualName]['sections'][$section]['meta'] = array();
					$result[$manualName]['sections'][$section]['topics'] = array();
				}
				if($tocItem['level'] == 1) { // actual topic
					if($forcedTitle == null || str_replace("_", " ", $tocItem['title']) == $forcedTitle) {
						$tempEntry = array('title' => $tocItem['title'],
									'text' => $tocItem['text'],
									'toctitle' => $tocItem['toctitle'],
									'conflicts' => PonyDocsBranchInheritEngine::getConflicts($product, $tocItem['title'], $targetVersion) );
						/**
						 * We want to set to empty, so the UI javascript doesn't
						 * bork out on this.
						 */
						if($tempEntry['conflicts'] == false)
							$tempEntry['conflicts'] = '';

						$result[$manualName]['sections'][$section]['topics'][] = $tempEntry;
					}
				}
			}
			foreach($result as $manualName => $manual) {
				foreach($manual['sections'] as $sectionIndex => $section) {
					if(count($section['topics']) == 0) {
						unset($result[$manualName]['sections'][$sectionIndex]);
					}
				}
			}
		}
		$result = json_encode($result);
		return $result;
	}

	/**
	 * AJAX method to fetch/initialize uniqid to identify this session.  Used to
	 * build progress report.
	 *
	 * @returns string The unique id for this job.
	 */
	public static function ajaxFetchJobID() {
		
		$perms = SpecialBranchInherit::userCanExecute();
		if ( !$perms ) {			
			return 'Access Denied';
		}

		$uniqid = uniqid("ponydocsbranchinherit", true);
		// Create the file.
		$path = PonyDocsExtension::getTempDir() . $uniqid;
		$fp = fopen($path, "w+");
		fputs($fp, "Determining Progress...");
		fclose($fp);
		return $uniqid;
	}

	public static function ajaxFetchJobProgress($jobID) {
		$perms = SpecialBranchInherit::userCanExecute();
		if( !$perms ) {			
			return "Access denied.";
		}		
		$uniqid = uniqid("ponydocsbranchinherit", true);
		$logParameters = "jobID=\"" . $jobID . "\"";
		$logFields = "action=\"jobidcreated\" status=\"success\" $logParameters";
		error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
		$path = PonyDocsExtension::getTempDir() . $jobID;
		$progress = file_get_contents($path);
		if($progress === false) {
			$progress = "Unable to fetch Job Progress.";
		}
		return $progress;
	}

	/**
	 * Processes a branch/inherit job request.
	 *
	 * @param $jobID The unique id for this job (see ajaxFetchJobID)
	 * @param $sourceProductName string product short name
	 * @param $sourceVersion string String representation of the source version
	 * @param $targetProductName string product short name
	 * @param $targetVersion string String representaiton of the target version
	 * @param $topicActions string JSON array representation of all topics and
	 * 								their requested actions.
	 * @return string Full job log of the process by printing to stdout.
	 */
	public static function ajaxProcessRequest(
		$jobID, $sourceProductName, $sourceVersion, $targetProductName, $targetVersion, $topicActions ) {
		global $wgScriptPath;
		$perms = ( SpecialBranchInherit::userCanExecute( $sourceProductName ) && SpecialBranchInherit::userCanExecute( $targetProductName ) );
		if ( !$perms ) {	
			print( "Access Denied." );
			$logFields = "action=\"start\" status=\"failure\" product=\"" . addslashes( $sourceProductName ) . "\" " 
 						. "sourceVersion=\"" . addslashes( $sourceVersion ) . "\" error=\"Access Denied \" " 
 						. "targetVersion=\"" . addslashes( $targetVersion ) . "\"";
 			error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );			
			return FALSE;
		}
		ob_start();

		$targetVersionShortName = $targetVersion;
		$sourceVersionName = $sourceVersion;

		$topicActions = json_decode($topicActions, true);

		list ($msec, $sec) = explode(' ', microtime());
		$startTime = (float)$msec + (float)$sec;

		$logParameters = "sourceProduct=\"" . addslashes( $sourceProductName )
			. "\" sourceVersion=\"" . htmlentities( $sourceVersionName )
			. "\" targetProduct=\"" . addslashes( $targetProductName )
			. "\" targetVersion=\"" . htmlentities( $targetVersionShortName ) . "\"";

		$logFields = "action=\"start\" status=\"success\" $logParameters";
		error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );

		if($topicActions == false) {
			print("Failed to read request.");
			return true;
		}

		print( "Beginning process job for source version: $sourceProductName:$sourceVersion<br />" );
		print("Target version is: $targetProductName:$targetVersion<br />");

		// Enable speed processing to avoid any unnecessary processing on
		// new topics created by this tool.
		PonyDocsExtension::setSpeedProcessing(true);

		$product = PonyDocsProduct::GetProductByShortName( $sourceProductName );
		$sourceVersion = PonyDocsProductVersion::GetVersionByName( $sourceProductName, $sourceVersion );
		$targetVersion = PonyDocsProductVersion::GetVersionByName( $targetProductName, $targetVersion );

		// Determine how many topics there are to process.
		$numOfTopics = 0;
		$numOfTopicsCompleted = 0;

		foreach($topicActions as $manualIndex => $manualData) {
			foreach($manualData['sections'] as $sectionName => $topics) {
				// The following is a goofy fix for some browsers.  Sometimes
				// the JSON comes along with null values for the first element.
				// IT's just an additional element, so we can drop it.
				if(empty($topics[0]['text'])) {
					array_shift($topicActions[$manualIndex]['sections'][$sectionName]);
				}
				$numOfTopics += count($topicActions[$manualIndex]['sections'][$sectionName]);
			}
		}
		$logFields = "action=\"topic\" status=\"success\" $logParameters";
		error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );

		$lastTopicTarget = null;

		foreach($topicActions as $manualName => $manualData) {
			$manual = PonyDocsProductManual::GetManualByShortName( $sourceProductName, $manualName );
			// Determine if TOC already exists for target version.
			if(!PonyDocsBranchInheritEngine::TOCExists($product, $manual, $targetVersion)) {
				print("<div class=\"normal\">TOC Does not exist for Manual " . $manual->getShortName() . " for version "
					. $targetVersion->getProductName() . ":" . $targetVersion->getVersionShortName() . "</div>");
				// Crl eate the toc or inherit.
				if($manualData['tocAction'] != 'default') {
					// Then they want to force.
					if($manualData['tocAction'] == 'forceinherit') {
						print("<div class=\"normal\">Forcing inheritance of source TOC.</div>");
						PonyDocsBranchInheritEngine::addVersionToTOC($product, $manual, $sourceVersion, $targetVersion);
						$logFields = "action=\"TOC-forceinherit\" status=\"success\" manual=\"" . htmlentities( $manualName ) . "\""
								. " $logParameters";
						error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
						print("<div class=\"normal\">Complete</div>");

					}
					else if($manualData['tocAction'] == 'forcebranch') {
						print("<div class=\"normal\">Forcing branch of source TOC.</div>");
						PonyDocsBranchInheritEngine::branchTOC($product, $manual, $sourceVersion, $targetVersion);
						$logFields = "action=\"TOC-forcebranch\" status=\"success\" manual=\"" . htmlentities( $manualName ) . "\""
								. " $logParameters";
						error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
						print("<div class=\"normal\">Complete</div>");
					}
				}
				/// WARNING FIXME action "default" has been removed from UI; this else block will never get run
				else {
					if($manualData['tocInherit']) {
						// We need to get the TOC for source version/manual and add
						// target version to the category tags.
						try {
							print("<div class=\"normal\">Attempting to add target version to existing source version TOC.</div>");
							PonyDocsBranchInheritEngine::addVersionToTOC($product, $manual, $sourceVersion, $targetVersion);
							$logFields = "action=\"TOC\" status=\"success\" manual=\"" . htmlentities( $manualName ) . "\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							$logFields = "action=\"TOC-tocInherit\" status=\"success\" manual=\"" . htmlentities( $manualName ) . "\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"normal\">Complete</div>");
						} catch(Exception $e) {
							$logFields = "action=\"TOC\" status=\"failure\" manual=\"" . htmlentities( $manualName ) ."\" "
								. "error=\"" . addslashes( $e->getMessage() ) . "\" $logParameters";
							error_log( 'WARNING [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"error\">Exception: " . $e->getMessage() . "</div>");
						}
					}
					else {
						try {
							print("<div class=\"normal\">Attempting to create TOC for target version.</div>");
							$addData = array();
							foreach($manualData['sections'] as $sectionName => $topics) {
								$addData[$sectionName] = array();
								foreach($topics as $topic) {
									$addData[$sectionName][] = $topic['toctitle'];
								}
							}
							PonyDocsBranchInheritEngine::createTOC($product, $manual, $targetVersion, $addData);
							$logFields = "action=\"TOC\" status=\"success\" manual=\"" . htmlentities( $manualName ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							$logFields = "action=\"TOC-Create\" status=\"success\" manual=\"" . htmlentities( $manualName ) . "\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"normal\">Complete</div>");
						} catch(Exception $e) {
							$logFields = "action=\"TOC\" status=\"failure\" manual=\"" . htmlentities( $manualName ) ."\""
								. " $logParameters";
							error_log( 'WARNING [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"error\">Exception: " . $e->getMessage() . "</div>");
						}
					}
				}
			}
			else {
					try {
						print("<div class=\"normal\">Attempting to update TOC for target version.</div>");
						$addData = array();
						foreach($manualData['sections'] as $sectionName => $topics) {
							$addData[$sectionName] = array();
							foreach($topics as $topic) {
								if(!isset($topic['action']) || (isset($topic['action']) && $topic['action'] != 'ignore')) {
									$addData[$sectionName][] = $topic['toctitle'];
								}
							}
						}
						PonyDocsBranchInheritEngine::addCollectionToTOC($product, $manual, $targetVersion, $addData);
						$logFields = "action=\"TOC\" status=\"success\" manual=\"" . htmlentities( $manualName ) ."\" "
							. "$logParameters";
						error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
						$logFields = "action=\"TOC-Update\" status=\"success\" manual=\"" . htmlentities( $manualName ) ."\" "
							. "$logParameters";
						error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
						print("<div class=\"normal\">Complete</div>");
					} catch(Exception $e) {
						$logFields = "action=\"TOC\" status=\"failure\" manual=\"" . htmlentities( $manualName ) ."\""
							. " error=\"" . htmlentities( $e->getMessage() ) . "\" $logParameters";
						error_log( 'WARNING [' . __METHOD__ . "] [BranchInherit] $logFields" );
						print("<div class=\"error\">Exception: " . $e->getMessage() . "</div>");
					}
			}

			// Okay, now let's go through each of the topics and
			// branch/inherit.
			print("Processing topics.\n");
			$path = PonyDocsExtension::getTempDir() . $jobID;
			foreach($manualData['sections'] as $sectionName => $topics) {
				print("<div class=\"normal\">Processing section $sectionName</div>");
				foreach($topics as $topic) {
					// Update log file
					$fp = fopen($path, "w+");
					fputs($fp, "Completed " . $numOfTopicsCompleted . " of " . $numOfTopics . " Total: " . ((int)($numOfTopicsCompleted / $numOfTopics * 100)) . "%");
					fclose($fp);
					if(isset($topic['action']) && $topic['action'] == "ignore") {
						$logFields = "action=\"topic-ignore\" status=\"success\" topicTitle=\"" . htmlentities( $topic['title'] ) ."\""
								. " $logParameters";
						error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
						print("<div class=\"normal\">Ignoring topic: " . $topic['title'] . "</div>");
						$numOfTopicsCompleted++;
						continue;
					}
					else if(isset($topic['action']) && $topic['action'] == "branchpurge") {
						try {
							print("<div class=\"normal\">Attempting to branch topic " . $topic['title'] . " and remove existing topic.</div>");
							$lastTopicTarget = PonyDocsBranchInheritEngine::branchTopic(
								$topic['title'], $targetVersion, $sectionName, $topic['text'], TRUE, FALSE );
							$logFields = "action=\"topic\" status=\"success\" manual=\"" . htmlentities( $manualName ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							$logFields = "action=\"topic-branchpurge\" status=\"success\" topicTitle=\"" . htmlentities( $topic['title'] ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"normal\">Complete</div>");
						} catch(Exception $e) {
							$logFields = "action=\"topic\" status=\"failure\" manual=\"" . htmlentities( $manualName ) ."\""
								. " error=\"" . htmlentities( $e->getMessage() ) . "\" $logParameters";
							error_log( 'WARNING [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"error\">Exception: " . $e->getMessage() . "</div>");
						}
					}
					else if(isset($topic['action']) && $topic['action'] == "branch") {
						try {
							print("<div class=\"normal\">Attempting to branch topic " . $topic['title'] . "</div>");
							$lastTopicTarget = PonyDocsBranchInheritEngine::branchTopic(
								$topic['title'], $targetVersion, $sectionName, $topic['text'], FALSE, TRUE );
							$logFields = "action=\"topic\" status=\"success\" manual=\"" . htmlentities( $manualName ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							$logFields = "action=\"topic-branch\" status=\"success\" topicTitle=\"" . htmlentities( $topic['title'] ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"normal\">Complete</div>");
						} catch(Exception $e) {
							$logFields = "action=\"topic\" status=\"failure\" manual=\"" . htmlentities( $manualName ) ."\""
								. " error=\"" . htmlentities($e->getMessage()) . "\" $logParameters";
							error_log( 'WARNING [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"error\">Exception: " . $e->getMessage() . "</div>");
						}
					}
					else if(isset($topic['action']) && $topic['action'] == "branchsplit") {
						try {
							print("<div class=\"normal\">Attempting to branch topic " . $topic['title'] . " and split from existing topic.</div>");
							$lastTopicTarget = PonyDocsBranchInheritEngine::branchTopic(
								$topic['title'], $targetVersion, $sectionName, $topic['text'], FALSE, TRUE );
							$logFields = "action=\"topic\" status=\"success\" manual=\"" . htmlentities( $manualName ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							$logFields = "action=\"topic-branchsplit\" status=\"success\" topicTitle=\"" . htmlentities( $topic['title'] ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"normal\">Complete</div>");
						} catch(Exception $e) {
							$logFields = "action=\"topic\" status=\"failure\" manual=\"" . htmlentities( $manualName ) ."\""
								. " error=\"" . htmlentities( $e->getMessage() ) . "\" $logParameters";
							error_log( 'WARNING [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"error\">Exception: " . $e->getMessage() . "</div>");
						}
					}
					else if(isset($topic['action']) && $topic['action'] == "inherit") {
						try {
							print("<div class=\"normal\">Attempting to inherit topic " . $topic['title'] . "</div>");
							$lastTopicTarget = PonyDocsBranchInheritEngine::inheritTopic(
								$topic['title'], $targetVersion, $sectionName, $topic['text'], FALSE );
							$logFields = "action=\"topic\" status=\"success\" manual=\"" . htmlentities( $manualName ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							$logFields = "action=\"topic-inherit\" status=\"success\" topicTitle=\"" . htmlentities( $topic['title'] ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"normal\">Complete</div>");
						} catch(Exception $e) {
							$logFields = "action=\"topic\" status=\"failure\" manual=\"" . htmlentities( $manualName ) ."\""
								. " error=\"" . htmlentities( $e->getMessage() ) . "\" $logParameters";
							error_log( 'WARNING [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"error\">Exception: " . $e->getMessage() . "</div>");
						}
					}
					else if(isset($topic['action']) && $topic['action'] == "inheritpurge") {
						try {
							print("<div class=\"normal\">Attempting to inherit topic " . $topic['title'] . " and remove existing topic.</div>");
							$lastTopicTarget = PonyDocsBranchInheritEngine::inheritTopic(
								$topic['title'], $targetVersion, $sectionName, $topic['text'], TRUE );
							$logFields = "action=\"topic\" status=\"success\" manual=\"" . htmlentities( $manualName ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							$logFields = "action=\"topic-inheritpurge\" status=\"success\" topicTitle=\"" . htmlentities( $topic['title'] ) ."\""
								. " $logParameters";
							error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"normal\">Complete</div>");
						} catch(Exception $e) {
							$logFields = "action=\"topic\" status=\"failure\" manual=\"" . htmlentities( $manualName ) ."\""
								. " error=\"" . htmlentities( $e->getMessage() ) . "\" $logParameters";
							error_log( 'WARNING [' . __METHOD__ . "] [BranchInherit] $logFields" );
							print("<div class=\"error\">Exception: " . $e->getMessage() . "</div>");
						}
					}
					$numOfTopicsCompleted++;
				}
			}
			// Clear TOCCACHE for the target version only, each Manual at a time
			PonyDocsTOC::clearTOCCache($manual, $targetVersion, $product);
			// Also clear the NAVCache for the target version
			PonyDocsProductVersion::clearNAVCache($targetVersion);
		}
		list ($msec, $sec) = explode(' ', microtime());
		$endTime = (float)$msec + (float)$sec;
		print("All done!\n");
		$logFields = "action=\"finish\" status=\"success\" manual=\"" . htmlentities( $manualName ) ."\""
								. " $logParameters";
		error_log( 'INFO [' . __METHOD__ . "] [BranchInherit] Completed $logFields" );
		print('Execution Time: ' . round($endTime - $startTime, 3) . ' seconds');
		if($numOfTopics == 1 && $lastTopicTarget != null) {
			// We can safely show a link to the topic.
			print("<br />");
			print("Link to new topic: <a href=\"" . $wgScriptPath . "/" .  $lastTopicTarget . "\">" . $lastTopicTarget . "</a>");
			print("<br />");
		}

		// Okay, let's start the process!
		unlink($path);
		$buffer = ob_get_clean();
		return $buffer;
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.
	 */
	public function execute() {
		global $wgOut, $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$this->setHeaders( );
		$wgOut->setPagetitle( 'Documentation Branch And Inheritance' );

		// if title is set we have our product and manual, else take selected product
		if(isset($_GET['titleName'])) {
			if(!preg_match('/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*):(.*)/',
				$_GET['titleName'], $match)) {
				throw new Exception("Invalid Title to Branch From");
			}
			$forceProduct = $match[1];
			$forceManual = $match[2];
		} else {
			$forceProduct = PonyDocsProduct::GetSelectedProduct();
		}
		$ponydocs = PonyDocsWiki::getInstance();
		$products = $ponydocs->getProductsForTemplate();

		$perms = SpecialBranchInherit::userCanExecute( $forceProduct );		
		// Security Check
		if ( !$perms ) {
			$wgOut->addHTML("<p>Sorry, but you do not have permission to access this Special page.</p>");
			return;
		}

		// Static product check
		if ( PonyDocsProduct::GetProductByShortName($forceProduct)->isStatic()) {
			$wgOut->addHTML("<p>Sorry, but you cannot branch/inherit a static product.</p>");
			return;
		}

		ob_start();

		// Grab all versions available for product
		// We need to get all versions from PonyDocsProductVersion
		$versions = PonyDocsProductVersion::GetVersions($forceProduct);

		if(isset($_GET['titleName'])) {
			?>
			<input type="hidden" id="force_titleName" value="<?php echo $_GET['titleName'];?>" />
			<input type="hidden" id="force_sourceVersion" value="<?php echo PonyDocsProductVersion::GetVersionByName($forceProduct, PonyDocsProductVersion::GetSelectedVersion($forceProduct))->getVersionShortName();?>" />
			<input type="hidden" id="force_manual" value="<?php echo $forceManual; ?>" />
			<?php
		}
		?>

		<input type="hidden" id="force_product" value="<?php echo $forceProduct; ?>" />
		<div id="docbranchinherit">
		<a name="top"></a>
		<div class="versionselect">
			<h1>Branch and Inheritance Console</h1>

			Begin by selecting your source product and version material, and target product and version below.
			You will then be presented with additional screens to specify branch and inherit behavior.
			<?php
			if(isset($_GET['titleName'])) {
				?>
				<p>
				Requested Operation on Single Topic: <strong><?php echo $_GET['titleName'];?></strong>
				</p>
				<?php
			}
			?>

			<h2>Choose a source Product</h2>

			<?php
			if ( isset( $_GET['titleName'] ) ) {
				?>
				You have selected a product: <?php echo $forceProduct; ?>
			<?php
			} else {
				if ( !count( $products ) ) {
					print "<p>No products defined.</p>";
				} else { ?>
					<div class="product">
						<select id="docsSourceProductSelect" name="selectedSourceProduct">
						<?php
							foreach( $products as $idx => $data ) {
								echo '<option value="' . $data['name'] . '" ';
								if ( !strcmp( $data['name'], $forceProduct ) )
									echo 'selected';
								echo '>' . $data['label'] . '</option>';
							}
						?>
						</select>
					</div>
				<?php
				}
			} ?>

			<h2>Choose a source Version</h2>
			<?php
				// Determine if topic was set, if so, we should fetch version from currently selected version.
				if(isset($_GET['titleName'])) {
					$version = PonyDocsProductVersion::GetVersionByName($forceProduct, PonyDocsProductVersion::GetSelectedVersion($forceProduct));
					?>
					You have selected a topic.  We are using the version you are currently browsing: <?php echo $version->getVersionShortName();?>
					<?php
				}
				else {
					?>
					<select name="sourceversion" id="versionselect_sourceversion">
						<?php
						foreach($versions as $version) {
							?>
							<option value="<?php echo $version->getVersionShortName();?>"><?php echo $version->getVersionShortName() . " - " . $version->getVersionStatus();?></option>
							<?php
						}
						?>
					</select>
					<?php
				}
			?>
					
			<h2>Choose a target Product</h2>

			<?php
			if ( !count( $products ) ) {
				print "<p>No products defined.</p>";
			} else { ?>
				<div class="product">
					<select id="docsTargetProductSelect" name="selectedTargetProduct">
					<?php
						foreach( $products as $idx => $data ) {
							echo '<option value="' . $data['name'] . '" ';
							if ( !strcmp( $data['name'], $forceProduct ) )
								echo 'selected';
							echo '>' . $data['label'] . '</option>';
						}
					?>
					</select>
				</div>
				<?php
			} ?>

			<h2>Choose a target Version</h2>
			<select name="targetversion" id="versionselect_targetversion">
				<?php
				foreach( $versions as $version ) { ?>
					<option value="<?php echo $version->getVersionShortName();?>"><?php echo $version->getVersionShortName()
						. " - " . $version->getVersionStatus();?></option>
					<?php
				} ?>
			</select>

			<p>
				<input type="button" id="versionselect_submit" value="Continue to Manuals" />
			</p>
		</div>

		<div class="manualselect" style="display: none;">
			<?php
			if(isset($_GET['titleName'])) {
				?>
				<p>
				Requested Operation on Single Topic: <strong><?php echo $_GET['titleName'];?></strong>
				</p>
				<?php
			}
			?>
			<p class="summary">
				<strong>Source Version:</strong> <span class="sourceversion"></span> <strong>Target Version:</strong> <span class="targetversion"></span>
			</p>
			<h1>Choose Manuals To Branch/Inherit From</h1>
			<div id="manualselect_manuals">

			</div>
			<h1>Choose Default Action For Topics</h1>
			<input type="radio" selected="selected" name="manualselect_action" value="ignore" id="manualselect_action_ignore"><label for="manualselect_action_ignore">Ignore - Do Nothing</label><br />
			<input type="radio" name="manualselect_action" value="inherit" id="manualselect_action_inherit"><label for="manualselect_action_inherit">Inherit - Add Target Version to Existing Topic</label><br />
			<input type="radio" name="manualselect_action" value="branch" id="manualselect_action_branch"><label for="manualselect_action_branch">Branch - Create a copy of existing topic with Target Version</label><br />
			<br />
			<input type="button" id="manualselect_submit" value="Continue to Topics" />
		</div>
		<div class="topicactions" style="display: none;">
			<?php
			if(isset($_GET['titleName'])) {
				?>
				<p>
				Requested Operation on Single Topic: <strong><?php echo $_GET['titleName'];?></strong>
				</p>
				<?php
			}
			?>
			<p class="summary">
			<strong>Source Version:</strong> <span class="sourceversion"></span> <strong>Target Version:</strong> <span class="targetversion"></span>
			</p>

			<h1>Specify Topic Actions</h1>
			<div class="container">
			</div>
			<br />
			<br />
			<input type="button" id="topicactions_submit" value="Process Request" />
			<div id="progressconsole"></div>
		</div>
		<div class="completed" style="display: none;">
			<p class="summary">
				<strong>Source Version:</strong> <span class="sourceversion"></span> <strong>Target Version:</strong> <span class="targetversion"></span>
			</p>

			<h2>Process Complete</h2>
			The following is the log of the processed job.  Look it over for any potential issues that may have
			occurred during the branch/inherit job.
			<div>
				<div class="logconsole" style="font-family: monospace; font-size: 10px;">

				</div>
			</div>
		</div>
		</div>
		<?php
		$buffer = ob_get_clean();
		$wgOut->addHTML($buffer);
		return true;
	}
}

