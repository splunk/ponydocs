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

class PonyDocsZipExport extends PonyDocsBaseExport {

	/**
	 * Called when an unknown action occurs on url.  We are only interested in zipmanual action.
	 */
	function onUnknownAction($action, $article) {
		global $wgOut, $wgUser, $wgTitle, $wgParser, $wgRequest;
		global $wgServer, $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory, $wgScript, $wgStylePath;

		// We don't do any processing unless it's pdfbook
		if ($action != 'zipmanual') {
			return true;
		}

		$zipAllowed = PonyDocsExtension::onUserCan($wgTitle, $wgUser, 'zipmanual', $zipAllowed);
		if(!$zipAllowed) {
			throw new Exception("User attempted to perform a ZIP Export without permission.");
		}
	
		// Get the title and make sure we're in Documentation namespace
		$title = $article->getTitle();
		if($title->getNamespace() != PONYDOCS_DOCUMENTATION_NAMESPACE_ID) {
			return true;
		}

		// Grab parser options for the logged in user.
		$opt = ParserOptions::newFromUser($wgUser);

		// Any potential titles to exclude
		$exclude = array();

		// Determine articles to gather
		$articles = array();
		$pieces = explode(":", $wgTitle->__toString());

		// Try and get rid of the TOC portion of the title
		if (strpos($pieces[2], "TOC") && count($pieces) == 3) {
			$pieces[2] = substr($pieces[2], 0, strpos($pieces[2], "TOC"));
		} else if (count($pieces) != 5) {
			// something is wrong, let's get out of here
			$defaultRedirect = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME, $wgArticlePath );
			if (PONYDOCS_REDIRECT_DEBUG) {
				error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] redirecting to $defaultRedirect");
			}
			header( "Location: " . $defaultRedirect );
			exit;
		}

		$productName = $pieces[1];
		$ponydocs = PonyDocsWiki::getInstance($productName);
		$pProduct = PonyDocsProduct::GetProductByShortName($productName);
		if ($pProduct === NULL) { // product wasn't valid
			wfProfileOut( __METHOD__ );
			$wgOut->setStatusCode(404);
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
					$articles[$tocEntry['section']][] = array('title' => $title, 'text' => $tocEntry['text']);
				}
			}
		} else {
			error_log("ERROR [PonyDocsZipExport::onUnknownAction] " . php_uname('n')
				. ": User attempted to export ZIP from a non TOC page with path:" . $wgTitle->__toString());
		}

		$html = self::getManualHTML($pProduct, $pManual, $v);

		$coverPageHTML = self::getCoverPageHTML($pProduct, $pManual, $v);


		// Make a temporary directory to store our archive contents.
		$tempDirPath = sys_get_temp_dir() . '/ponydocs-zip-export-' . time();
		$success = @mkdir($tempDirPath);
		if (!$success) {
			error_log("ERROR [PonyDocsZipExport::onUnknownAction] Failed to create temporary directory " . $tempDirPath . " for Zip Export.");
			throw new Exception('Failed to create temporary directory for Zip Export.');
		}

		// Now, let's fetch all the img elements for both and grab them all in 
		// parallel.
		$imgData = array();
		$mh = curl_multi_init();

		$manualDoc = new DOMDocument();
		@$manualDoc->loadHTML($html);
		$coverPageDoc = new DOMDocument();
		@$coverPageDoc->loadHTML($coverPageHTML);

		self::prepareImageRequests($manualDoc, $mh, &$imgData);
		self::prepareImageRequests($coverPageDoc, $mh, &$imgData);

		// Perform the multi connect
		$active = null;
		do {
			$mrc = curl_multi_exec($mh, $active);
		} while($mrc == CURLM_CALL_MULTI_PERFORM);

		while($active && $mrc == CURLM_OK) {
			if(curl_multi_select($mh) != -1) {
				do {
					$mrc = curl_multi_exec($mh, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}

		// Now update all our image elements in our appropriate DOMDocs.
		foreach($imgData as $img) {
			// Modify element
			$img['element']->setAttribute('src', $img['new_path']);
			// Do curl cleanup
			curl_multi_remove_handle($mh, $img['ch']);
		}
		curl_multi_close($mh);

		$html = $manualDoc->saveHTML();
		$coverPageHTML = $coverPageDoc->saveHTML();

		// Write the HTML to a tmp file
		$file = tempnam($tempDirPath, "zipexport-");
		$fh = fopen($file, 'w+');
		fwrite($fh, $html);
		fclose($fh);

		// Okay, write the title page
		$titlepagefile = tempnam($tempDirPath, "zipexport-");
		$fh = fopen($titlepagefile, 'w+');
		fwrite($fh, $coverPageHTML);
		fclose($fh);



		// Disable output of our standard mediawiki output.  We will be outputting a zip file instead.
		$wgOut->disable();

		// Create ZIP Archive which contains a cover and manual html
		$zip = new ZipArchive();
		$tempZipFilePath = tempnam($tempDirPath, "zipexport-");
		$zipFileName = $productName . '-' . $versionText . '-' . $pManual->getShortName() . '.zip';
		$zip->open($tempZipFilePath, ZipArchive::OVERWRITE);
		$zip->addFile($titlepagefile, 'cover.html');
		$zip->addFile($file, 'manual.html');
		// Iterate through all the images
		foreach($imgData as $img) {
			$zip->addFile($img['local_path'], $img['new_path']);
		}
		$zip->close();
		header("Content-Type: application/zip"); 
		header("Content-Length: " . filesize($tempZipFilePath)); 
		header("Content-Disposition: attachment; filename=\"" . $zipFileName . "\""); 
		readfile($tempZipFilePath);

		// Now remove all temp files
		unlink($tempZipFilePath);
		unlink($file);
		unlink($titlepagefile);
		foreach($imgData as $img) {
			unlink($img['local_path']);
		}
		rmdir($tempDirPath);

		
		// Okay, let's add an entry to the error log to dictate someone requested a pdf
		error_log("INFO [PonyDocsZipExport::onUnknownAction] " . php_uname('n') . ": zip export serve username=\""
			. $wgUser->getName() . "\" version=\"$versionText\" " . " manual=\"" . $pManual->getShortName() . "\"");
		// No more processing
		return false;
	}

	/**
	 * Prepares a passed in data array with img elements that need to be fetched from remote server and changed to point to a local resource.
	 *
	 * @param DOMDocument $doc 	The DOMDocument to evaluate img elements for
	 * @param resource $mh 		The curl multi handler to append to
	 * @param array $imgData 	The data array to populate
	 */	
	private function prepareImageRequests($doc, $mh, $imgData) {
		$imgElements = $doc->getElementsByTagName('img');
		foreach($imgElements as $imgElement) {
			$src = $imgElement->getAttribute('src');
			$pathInfo = pathinfo($src);
			$tempFileName = tempnam($tempDirPath, "img-");
			$ch = curl_init($src);
			$fh = fopen($tempFileName, 'w+');
			curl_setopt($ch, CURLOPT_FILE, $fh);
			$imgData[] = array(
				'src' => $src,
				'element' => $imgElement,
				'extension' => $pathInfo['extension'],
				'local_path' => $tempFileName,
				'new_path' => 'images/' . basename($tempFileName) . '.' . $pathInfo['extension'],
				'fh' => $fh,
				'ch' => $ch,
			);	
			curl_multi_add_handle($mh, $ch);
		}
	}


	/**
	 * Needed in some versions to prevent Special:Version from breaking
	 */
	function __toString() {
		return 'PonyDocsZipFile';
	}
}
