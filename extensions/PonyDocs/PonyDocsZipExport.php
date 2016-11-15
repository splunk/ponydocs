<?php
/**
 * PonyDocsZipExport Extension
 * - Export a Manual from documentation into a ZIP file
 * - Exports a cover page as well as one-file HTML file representing the manual
 * - Handled as a sep extension but part of PonyDocs package
 *
 * @package MediaWiki
 * @subpackage Extensions
 */
if (!defined('MEDIAWIKI')) die('Not an entry point.');

require_once(__DIR__ . '/RollingCurl/RollingCurl.php');
require_once(__DIR__ . '/RollingCurl/Request.php');


class PonyDocsZipExport extends PonyDocsBaseExport {

	/**
	 * Called when an unknown action occurs on url.  We are only interested in zipmanual action.
	 */
	function onUnknownAction($action, $article) {
		global $wgOut, $wgTitle, $wgUser;

		// Gate for zipmanual action
		if ( $action != 'zipmanual' ) {
			return TRUE;
		}

		$zipAllowed = FALSE;
		PonyDocsExtension::onUserCan( $wgTitle, $wgUser, 'zipmanual', &$zipAllowed );
		if ( !$zipAllowed ) {
			error_log( "WARNING [" . __METHOD__ . "] User attempted to perform a ZIP Export without permission." );
			$defaultRedirect = PonyDocsExtension::getDefaultUrl();
			header( "Location: " . $defaultRedirect );
			exit;
		}
	
		// Gate for Documentation namespace
		$title = $article->getTitle();
		if ( $title->getNamespace() != NS_PONYDOCS ) {
			return TRUE;
		}

		// Grab parser options for the logged in user.
		$opt = ParserOptions::newFromUser( $wgUser );

		// Any potential titles to exclude
		$exclude = array();

		// Grab Topics html
		$arrTopicHtml = array();

		// Grab Topic Images Array
		$arrAllTopicImages = array();


		// Determine articles to gather
		$articles = array();
		$pieces = explode(":", $wgTitle->__toString());

		// Try and get rid of the TOC portion of the title
		if (strpos($pieces[2], "TOC") && count($pieces) == 3) {
			$pieces[2] = substr($pieces[2], 0, strpos($pieces[2], "TOC"));
		} else if (count($pieces) != 5) {
			// something is wrong, let's get out of here
			$defaultRedirect = PonyDocsExtension::getDefaultUrl();
			if (PONYDOCS_DEBUG) {
				error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] redirecting to $defaultRedirect");
			}
			header( "Location: " . $defaultRedirect );
			exit;
		}

		// Determine Product
		if ( isset( $_GET['product'] ) && PonyDocsProduct::IsProduct( $_GET['product'] ) ) {
			$productName = $_GET['product'];
		} else {
			$productName = $pieces[1];
		}
		$pProduct = PonyDocsProduct::GetProductByShortName( $productName );
		// product wasn't valid
 		if ( $pProduct === NULL ) {
			wfProfileOut( __METHOD__ );
			$wgOut->setStatusCode( 404 );
			return FALSE;
		}
		$productLongName = $pProduct->getLongName();
		
		if (PonyDocsProductManual::isManual($productName, $pieces[2])) {
			$pManual = PonyDocsProductManual::GetManualByShortName($productName, $pieces[2]);
		}

		$versionText = PonyDocsProductVersion::GetSelectedVersion($productName);

		if (!empty($pManual)) {
			// We should always have a pManual, if we're printing 
			// from a TOC
			$v = PonyDocsProductVersion::GetVersionByName($productName, $versionText);

			$toc = new PonyDocsTOC($pManual, $v, $pProduct);
			list($manualtoc, $tocprev, $tocnext, $tocstart) = $toc->loadContent();

			// We successfully got our table of contents.  It's 
			// stored in $manualtoc
			foreach($manualtoc as $tocEntry) {
				if($tocEntry['level'] > 0 && strlen($tocEntry['title']) > 0) {
					$title = Title::newFromText($tocEntry['title']);

					//Taking title for directory name
					$topicTitle =  $tocEntry['title'];
					$arrtopicTitle = explode(':', $tocEntry['title']);
					if(!empty($arrtopicTitle[3])) {
						$topicTitle = $arrtopicTitle[3];
					}

					$articles[$tocEntry['section']][] = array('title' => $title, 'text' => $tocEntry['text'], 'topicTitle' => $topicTitle);
				}
			}
		} else {
			error_log("WARNING [" . __METHOD__ . "] " . php_uname('n')
				. ": User attempted to export ZIP from a non TOC page with path:" . $wgTitle->__toString());
		}

		$arrTopicHtml = self::getManualTopicsHTML($pProduct, $pManual, $v, $articles);

		$coverPageHTML = self::getCoverPageHTML($pProduct, $pManual, $v, false);


		// Make a temporary directory to store our archive contents.
		$tempDirPath = sys_get_temp_dir() . '/ponydocs-zip-export-' . time();
		$success = @mkdir($tempDirPath);
		if (!$success) {
			error_log("FATAL [" . __METHOD__ . "] Failed to create temporary directory " . $tempDirPath . " for Zip Export.");
			throw new Exception('Failed to create temporary directory for Zip Export.');
		}

		// Now, let's fetch all the img elements for both and grab them all in 
		// parallel.
		$imgData = array();

		// Initialize our RollingCurl instance
		$rollingCurl = new \RollingCurl\RollingCurl();

		$mh = curl_multi_init();

		foreach ($arrTopicHtml as $topicName => $topicHtml) {

			$arrTopicImages = array(); 
			$manualDoc = new DOMDocument();
			@$manualDoc->loadHTML($topicHtml);
			
			self::prepareImageRequests($manualDoc, $rollingCurl, $tempDirPath,  &$arrTopicImages);
			
			// Execute the RollingCurl requests
			$rollingCurl->execute();
			// Now update all our image elements in our appropriate DOMDocs.
			foreach($arrTopicImages as $img) {
				// Put the data into it.
				file_put_contents($img['local_path'], $img['request']->getResponseText());
				// Modify element
				$img['element']->setAttribute('src', $img['new_path']);
				// Do curl cleanup

				$arrAllTopicImages[$topicName][] = array('local_path' => $img['local_path'], 'new_path' => $img['new_path']);
			}
			
			//Write the html to temp files
			$topicHtml = $manualDoc->saveHTML();
			$file = tempnam($tempDirPath, "zipexport-");
			$arrTopicHtmlFiles[$topicName] = $file;
			$fh = fopen($file, 'w+');
			fwrite($fh, $topicHtml);
			fclose($fh);			
		}

		//Cover Page Manual starts here
		$coverPageDoc = new DOMDocument();
		@$coverPageDoc->loadHTML($coverPageHTML);

		
		self::prepareImageRequests($coverPageDoc, $rollingCurl, $tempDirPath, &$imgData);

		// Execute the RollingCurl requests
		$rollingCurl->execute();

		// Now update all our cover page image elements in our appropriate DOMDocs.
		foreach($imgData as $img) {
			// Put the data into it.
			file_put_contents($img['local_path'], $img['request']->getResponseText());
			// Modify element
			$img['element']->setAttribute('src', $img['new_path']);
			// Do curl cleanup
		}

		$coverPageHTML = $coverPageDoc->saveHTML();
		
		// Okay, write the title page
		$titlepagefile = tempnam($tempDirPath, "zipexport-");
		$fh = fopen($titlepagefile, 'w+');
		fwrite($fh, $coverPageHTML);
		fclose($fh);
		//Cover Page Manual Ends here


		// Disable output of our standard mediawiki output.  We will be outputting a zip file instead.
		$wgOut->disable();

		// Create ZIP Archive which contains a cover and manual html
		$zip = new ZipArchive();
		$tempZipFilePath = tempnam($tempDirPath, "zipexport-");
		$zipFileName = $productName . '-' . $versionText . '-' . $pManual->getShortName() . '.zip';
		$zip->open($tempZipFilePath, ZipArchive::OVERWRITE);
		
		//Adding Cover Page
		$zip->addFile($titlepagefile, 'cover.html');

		// Iterate through all the Cover Page images
		foreach($imgData as $img) {
			$zip->addFile($img['local_path'], $img['new_path']);
		}


		//Added Topic Html and Images
		$topicNo = 0;
		foreach($arrTopicHtmlFiles as $topicName => $fileData) {

			$topicNo++;
			$dirName = $topicNo . '_' . $topicName;			
			$zip->addFile($fileData, $dirName . '/' . 'topic.html');

			if(!empty($arrAllTopicImages[$topicName]))
			{	
				$arrTopicImages = $arrAllTopicImages[$topicName];
				foreach ($arrTopicImages as $img) {
					$zip->addFile($img['local_path'], $dirName . '/' . $img['new_path']);
				}
			}
		}
		
		$zip->close();
		header("Content-Type: application/zip"); 
		header("Content-Length: " . filesize($tempZipFilePath)); 
		header("Content-Disposition: attachment; filename=\"" . $zipFileName . "\""); 
		readfile($tempZipFilePath);

		// Now remove all temp files
		self::rrmdir($tempDirPath);

		
		// Okay, let's add an entry to the error log to dictate someone requested a pdf
		error_log("INFO [" . __METHOD__ . "] " . php_uname('n') . ": zip export serve username=\""
			. $wgUser->getName() . "\" version=\"$versionText\" " . " manual=\"" . $pManual->getShortName() . "\"");
		// No more processing
		return false;
	}

	/**
	 * Prepares a passed in data array with img elements that need to be fetched from remote server and changed to point to a local resource.
	 *
	 * @param DOMDocument $doc 	The DOMDocument to evaluate img elements for
	 * @param \RollingCurl\RollingCurl $rollingCurl 		The RollingCurl instance to add our requests to
	 * @param string $tempDirPath   The directory to store images
	 * @param array $imgData 	The data array to populate
	 */	
	private function prepareImageRequests($doc, $rollingCurl, $tempDirPath, $imgData) {
		global $wgServer;
		// Ensure there's a trailing slash after our $wgServer name
		if (substr($wgServer, -1) !== '/') {
			$search = $wgServer .= '/';
		} else {
			$search = $wgServer;
		}
		$imgElements = $doc->getElementsByTagName('img');
		foreach($imgElements as $imgElement) {
			$src = $imgElement->getAttribute('src');
			// Strip the server and slash from our image src
			$localPath = str_replace($search, '', $src);
			$pathInfo = pathinfo($localPath);
			// Create the directory locally to mimic the directory structure of the server
			$localPath = $tempDirPath . '/' . $pathInfo['dirname'];
			if(!is_dir($localPath)) {
				$result = mkdir($localPath, 0777, true);
				if(!$result) {
					throw new Exception("Failed to create temp directory: $localPath");
				}
			}
			$localPath = $localPath .= '/' . $pathInfo['basename'];
			$zipPath = $pathInfo['dirname'] . '/' . $pathInfo['basename'];
			$request = new \RollingCurl\Request($src);
			$imgData[] = array(
				'src' => $src,
				'element' => $imgElement,
				'extension' => $pathInfo['extension'],
				'local_path' => $localPath,
				'new_path' => $zipPath,
				'request' => $request,
			);	
			$rollingCurl->add($request);
		}
	}

	/**	
	 * Utility method to recursively delete a directory.
	 *
	 * @param $dir string The directory to delete
	 */
	private function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dir."/".$object) == "dir") self::rrmdir($dir."/".$object); else unlink($dir."/".$object);
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}

	/**
	 * Needed in some versions to prevent Special:Version from breaking
	 */
	function __toString() {
		return 'PonyDocsZipFile';
	}
}
