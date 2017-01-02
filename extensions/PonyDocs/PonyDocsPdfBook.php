<?php
/**
 * PonyDocsPdfBook extension
 * - Composes a book from documentation and exports as a PDF book
 * - Derived from PdfBook Mediawiki Extension
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author Taylor Dondich tdondich@splunk.com 
 */
if (!defined('MEDIAWIKI')) die('Not an entry point.');

define('PONYDOCS_PDFBOOK_VERSION', '1.1, 2010-04-22');

$wgExtensionCredits['parserhook'][] = array(
	'name' => 'PonyDocsPdfBook',
	'author' => 'Taylor Dondich and [http://www.organicdesign.co.nz/nad User:Nad]',
	'description' => 'Composes a book from documentation and exports as a PDF book',
	'url' => 'http://www.splunk.com',
	'version' => PONYDOCS_PDFBOOK_VERSION
	);

// Catch the pdfbook action
$wgHooks['UnknownAction'][] = "PonyDocsPdfBook::onUnknownAction";

// Add a new pdf log type
$wgLogTypes[] = 'ponydocspdf';
$wgLogNames['ponydocspdf'] = 'ponydocspdflogpage';
$wgLogHeaders['ponydocspdf'] = 'ponydocspdflogpagetext';
$wgLogActions['ponydocspdf/book'] = 'ponydocspdflogentry';


class PonyDocsPdfBook extends PonyDocsBaseExport {

	/**
	 * Called when an unknown action occurs on url.  We are only interested in pdfbook action.
	 */
	function onUnknownAction($action, $article) {
		global $wgOut, $wgUser, $wgTitle, $wgParser, $wgRequest;
		global $wgServer, $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory, $wgScript, $wgStylePath;
		
		$justThisTopic = (isset($_GET['topic']) && $_GET['topic'] == 1) ? TRUE : FALSE;
		// We don't do any processing unless it's pdfbook
		if ($action != 'pdfbook') {
			return true;
		}

		// Get the title and make sure we're in Documentation namespace
		$title = $article->getTitle();
		if($title->getNamespace() != NS_PONYDOCS) {
			return true;
		}

		// Grab parser options for the logged in user.
		$opt = ParserOptions::newFromUser($wgUser);

		// Log the export
		$msg = $wgUser->getUserPage()->getPrefixedText() . ' exported as a PonyDocs PDF Book';
		$log = new LogPage('ponydocspdfbook', false);
		$log->addEntry('book', $wgTitle, $msg);

		// Initialise PDF variables
		$layout      = '--firstpage p1';
		$x_margin = '1.25in';
		$y_margin = '1in';
		$font  = 'Arial';
		$size  = '12';
		$linkcol = '4d9bb3';
		$levels  = '2';
		$exclude = array();
		$width   = '1024';
		$width   = "--browserwidth 1024";

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
		$pProduct = PonyDocsProduct::GetProductByShortName($productName);
		// Gate for invalid product
		if ($pProduct === NULL) { 
			wfProfileOut( __METHOD__ );
			$wgOut->setStatusCode(404);
			return FALSE;
		}
		$productLongName = $pProduct->getLongName();

		// Determine Manual
		if ( PonyDocsProductManual::isManual( $productName, $pieces[2] ) ) {
			$pManual = PonyDocsProductManual::GetManualByShortName($productName, $pieces[2]);
		}

		// Determine Version
		if ( isset( $_GET['version'] ) && PonyDocsProductVersion::IsVersion( $productName, $_GET['version'] ) ) {
			$versionName = $_GET['version'];
		} else {
			$versionName = PonyDocsProductVersion::GetSelectedVersion($productName);
		}
		$version = PonyDocsProductVersion::GetVersionByName($productName, $versionName);

		// Determine Topic
		$topic = NULL;
		if ( !$justThisTopic ) {
			// We have our version and our manual Check to see if a file already exists for this combination
			$pdfFileName = "$wgUploadDirectory/ponydocspdf-" . $productName . "-" . $versionName . "-" . $pManual->getShortName()
					. "-book.pdf";
			// Check first to see if this PDF has already been created and is up to date.  If so, serve it to the user and stop 
			// execution.
		} else {
			$topic = new PonyDocsTopic($article);
			$pdfFileName = "$wgUploadDirectory/ponydocspdf-" . $productName . "-" . $versionName . "-" . $pManual->getShortName()
				. "-" . $topic->getTopicName() . "-book.pdf";
		}
		
		// Check first to see if this PDF has already been created and is up to date.
		// If so, serve it to the user and stop execution.
		if ( file_exists( $pdfFileName ) ) {
			$errorData = array(
				'username' => $wgUser->getName(),
				'product' => addcslashes( $productName, '"' ),
				'version' => $versionName,
				'manual' => addcslashes( $pManual->getShortName(), '"'),
				'topic' => $topic ? $topic->getTopicName() : '',
			);
			$logString = '';
			foreach ( $errorData as $key => $value ) {
				$logString .= '"' . $key . '"="' . $value . '" ';
			}
			error_log( "INFO [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": cache serve " . $logString );
			PonyDocsPdfBook::servePdf( $pdfFileName );
		} else {
			$pdfName = '';
			if ( $justThisTopic ) {
				$html = self::getTopicHTML($pProduct, $article, $version);
				$title = $article->getTitle();
				$pdfName = $title->getPrefixedText();
				$pdfName = str_replace(':', ' ', $pdfName);
			} else {
				$html = self::getManualHTML($pProduct, $pManual, $version);
				$pdfName = $pManual->getLongName();
			}

			// HTMLDOC does not care for utf8. 
			$html = utf8_decode("$html\n");

			// Write the HTML to a tmp file
			$file = "$wgUploadDirectory/".uniqid('ponydocs-pdf-book');
			$fh = fopen($file, 'w+');
			fwrite($fh, $html);
			fclose($fh);

			// Okay, create the title page
			$titlepagefile = "$wgUploadDirectory/" .uniqid('ponydocs-pdf-book-title');
			$fh = fopen($titlepagefile, 'w+');
			if ( $justThisTopic ) {
				$coverPageHTML = self::getCoverPageHTML($pProduct, $pManual, $version, true, $title);
			}else {
				$coverPageHTML = self::getCoverPageHTML($pProduct, $pManual, $version, true);
			}

			fwrite($fh, $coverPageHTML);
			fclose($fh);

			if ( $justThisTopic ) {
				$format = 'single'; 
			} else {
				$format = 'manual'; 	/* @todo Modify so single topics can be printed in pdf */
			}

			$footer = '.1.';
			$toc = $format == 'single' ? '' : " --toclevels $levels";

			// Send the file to the client via htmldoc converter
			$wgOut->disable();
			if ( $justThisTopic ) {
				$cmd  = " --left $x_margin --right $x_margin --top $y_margin --bottom $y_margin";
				$cmd .= " --header ... --footer $footer --quiet --jpeg --color";
				$cmd .= " --bodyfont $font --fontsize $size --linkstyle plain --linkcolor $linkcol";
				$cmd .= " --format pdf14 $layout $width --titlefile $titlepagefile --size letter";
				$cmd  = "htmldoc -t pdf --book --charset iso-8859-1 --webpage --no-numbered $cmd $file > " . escapeshellarg($pdfFileName);
			}else {
				$cmd  = " --left $x_margin --right $x_margin --top $y_margin --bottom $y_margin";
				$cmd .= " --header ... --footer $footer --tocfooter .i. --quiet --jpeg --color";
				$cmd .= " --bodyfont $font --fontsize $size --linkstyle plain --linkcolor $linkcol";
				$cmd .= "$toc --format pdf14 $layout $width --titlefile $titlepagefile --size letter";
				$cmd  = "htmldoc -t pdf --book --charset iso-8859-1 --no-numbered $cmd $file > " . escapeshellarg($pdfFileName);
			}

			putenv("HTMLDOC_NOCGI=1");

			$output = array();
			$returnVar = 1;
			exec($cmd, $output, $returnVar);
			if ($returnVar != 0) { // 0 is success
				error_log("INFO [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": Failed to run htmldoc (" . $returnVar . ") Output is as follows: " . implode("-", $output));
				print("Failed to create PDF.  Our team is looking into it.");
			}

			// Delete the htmlfile and title file from the filesystem.
			@unlink($file);
			if (file_exists($file)) {
				error_log("ERROR [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": Failed to delete temp file $file");
			}
			@unlink($titlepagefile);
			if (file_exists($titlepagefile)) {
				error_log("ERROR [PonyDocsPdfBook::onUnknownAction] " . php_uname('n')
					. ": Failed to delete temp file $titlepagefile");
			}

			// Okay, let's add an entry to the error log to dictate someone requested a pdf
			error_log("INFO [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": fresh serve username=\""
				. $wgUser->getName() . "\" version=\"$versionName\" " . " manual=\"" . addcslashes( $pdfName, '"' ) . "\"");
			PonyDocsPdfBook::servePdf( $pdfFileName, $productName, $versionName, $pdfName, $topic );
		}
		// No more processing
		return FALSE;
	}

	/**
	 * Serves out a PDF file to the browser
	 *
	 * @param $fileName string The full path to the PDF file.
	 * @return FALSE on failure
	 */
	static public function servePdf( $fileName ) {
		if ( file_exists( $fileName ) ) {
			$modFileName = $fileName;
			$fileNameDetails = explode('-', $fileName);
			$fileNameDetails = array_slice($fileNameDetails, 1, -1);
			if (!empty($fileNameDetails)) {
				$modFileName = implode('-', $fileNameDetails) . '.pdf';
			}
			header( "Content-Type: application/pdf" );
			header("Content-Disposition: attachment; filename=\"$modFileName\"");			
			readfile($fileName);
			// End processing right away.
 			die();
		} else {
			return FALSE;
		}
	}

	/**
	 * Removes a cached PDF file.  
	 * Just attempts to unlink.  
	 * However, does a quick check to see if the file exists after the unlink, and logs if so.
	 *
	 * @param $product string the short name of the product to remove
	 * @param $manual string The short name of the manual to remove
	 * @param $version string The version of the manual to remove
	 * @param $topicName string The name of the topic to remove
	 * @return boolean TRUE on success and FALSE on failure
	 */
	static public function removeCachedFile( $product, $manual, $version, $topicName = NULL ) {
		global $wgUploadDirectory;
		
		if ( !empty( $topicName ) ) {
			$pdfFileName = "$wgUploadDirectory/ponydocspdf-$product-$version-$manual-$topicName-book.pdf";			
		} else {
			$pdfFileName = "$wgUploadDirectory/ponydocspdf-$product-$version-$manual-book.pdf";			
		}

		if (file_exists( $pdfFileName ) ) {
			@unlink($pdfFileName);
			// If it still exists after unlinking, oops
			if ( file_exists( $pdfFileName ) ) {
				error_log( "ERROR [PonyDocsPdfBook::removeCachedFile] " . php_uname( 'n' )
					. ": Failed to delete cached pdf file $pdfFileName" );
				return FALSE;
			} else {
				error_log( "INFO [PonyDocsPdfBook::removeCachedFile] " . php_uname( 'n' ) . ": Cache file $pdfFileName removed.");
			}
		}

		return TRUE;
	}

	/**
	 * Needed in some versions to prevent Special:Version from breaking
	 */
	function __toString() {
		return 'PonyDocsPdfBook';
	}
}

/**
 * Called from $wgExtensionFunctions array when initialising extensions
 */
function wfSetupPdfBook() {
	global $wgPonyDocsPdfBook;
	$wgPonyDocsPdfBook = new PonyDocsPdfBook();
}


?>
