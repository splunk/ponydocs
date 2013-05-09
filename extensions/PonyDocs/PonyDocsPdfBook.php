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
	'name'		  => 'PonyDocsPdfBook',
	'author'	  => 'Taylor Dondich and [http://www.organicdesign.co.nz/nad User:Nad]',
	'description' => 'Composes a book from documentation and exports as a PDF book',
	'url'		  => 'http://www.splunk.com',
	'version'	 => PONYDOCS_PDFBOOK_VERSION
	);

// Catch the pdfbook action
$wgHooks['UnknownAction'][] = "PonyDocsPdfBook::onUnknownAction";

// Add a new pdf log type
$wgLogTypes[]			 = 'ponydocspdf';
$wgLogNames['ponydocspdf']	  = 'ponydocspdflogpage';
$wgLogHeaders['ponydocspdf']	  = 'ponydocspdflogpagetext';
$wgLogActions['ponydocspdf/book'] = 'ponydocspdflogentry';


class PonyDocsPdfBook {

	/**
	 * Called when an unknown action occurs on url.  We are only interested in 
	 * pdfbook action.
	 */
	function onUnknownAction($action, $article) {
		global $wgOut, $wgUser, $wgTitle, $wgParser, $wgRequest;
		global $wgServer, $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory, $wgScript, $wgStylePath;

		// We don't do any processing unless it's pdfbook
		if($action != 'pdfbook') {
			return true;
		}

		// Get the title and make sure we're in Documentation namespace
		$title = $article->getTitle();
		if($title->getNamespace() != PONYDOCS_DOCUMENTATION_NAMESPACE_ID) {
			return true;
		}


		// Grab parser options for the logged in user.
		$opt = ParserOptions::newFromUser($wgUser);

		# Log the export
		$msg = $wgUser->getUserPage()->getPrefixedText().' exported as a PonyDocs PDF Book';
		$log = new LogPage('ponydocspdfbook', false);
		$log->addEntry('book', $wgTitle, $msg);

		# Initialise PDF variables
		$layout      = '--firstpage p1';
		$x_margin = '1.25in';
		$y_margin = '1in';
		$font	= 'Arial';
		$size	= '12';
		$linkcol = '4d9bb3';
		$levels  = '2';
		$exclude = array();
		$width   = '1024';
		$width   = "--browserwidth 1024";

		// Determine articles to gather
		$articles = array();
		$pieces = explode(":", $wgTitle->__toString());

		// Try and get rid of the TOC portion of the title
		if(strpos($pieces[2], "TOC") && count($pieces) == 3) {
			$pieces[2] = substr($pieces[2], 0, strpos($pieces[2], "TOC"));
		} else if (count($pieces) != 5) {
			// something is wrong, let's get out of here
			$defaultRedirect = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME, $wgArticlePath );
			if (PONYDOCS_REDIRECT_DEBUG) {error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] redirecting to $defaultRedirect");}
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
		
		if(PonyDocsProductManual::isManual($productName, $pieces[2])) {
			$pManual = PonyDocsProductManual::GetManualByShortName($productName, $pieces[2]);
		}

		$versionText = PonyDocsProductVersion::GetSelectedVersion($productName);

		if(!empty($pManual)) {
			// We should always have a pManual, if we're printing 
			// from a TOC
			$v = PonyDocsProductVersion::GetVersionByName($productName, $versionText);

			// We have our version and our manual
			// Check to see if a file already exists for this combination
			$pdfFileName = "$wgUploadDirectory/ponydocspdf-" . $productName . "-" . $versionText . "-" . $pManual->getShortName() . "-book.pdf";
			// Check first to see if this PDF has already been created and 
			// is up to date.  If so, serve it to the user and stop 
			// execution.
			if(file_exists($pdfFileName)) {
				error_log("INFO [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": cache serve username=\"" . $wgUser->getName() . "\" product=\"" . $productName . "\" version=\"" . $versionText ."\" " . " manual=\"" . $pManual->getShortName() . "\"");
				PonyDocsPdfBook::servePdf($pdfFileName, $productName, $versionText, $pManual->getShortName());
				// No more processing
				return false;
			}
			// Oh well, let's go on our merry way and create our pdf.

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
		}
		else {
			error_log("ERROR [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": User attempted to print a pdfbook from a non TOC page with path:" . $wgTitle->__toString());
		}

		# Format the article(s) as a single HTML document with absolute URL's
		$book = $pManual->getLongName();
		$html = '';
		$wgArticlePath = $wgServer.$wgArticlePath;
		$wgScriptPath  = $wgServer.$wgScriptPath;
		$wgUploadPath  = $wgServer.$wgUploadPath;
		$wgScript	  = $wgServer.$wgScript;
		$currentSection = '';
		foreach ($articles as $section => $subarticles) {
			foreach($subarticles as $article) {
				$title = $article['title'];
				// $h2 = "<h2>" . $article['text'] . "</h2>";
				$ttext = $title->getPrefixedText();
				if (!in_array($ttext, $exclude)) {
					if($currentSection != $section) {
						$html .= '<h1>' . $section . '</h1>';
						$currentSection = $section;
					}		
					$article = new Article($title, 0);
					$text	= $article->fetchContent();
					$text	= preg_replace('/<!--([^@]+?)-->/s', '@@'.'@@$1@@'.'@@', $text); # preserve HTML comments
						$text   .= '__NOTOC__';
					$opt->setEditSection(false);	# remove section-edit links
					$wgOut->setHTMLTitle($ttext);   # use this so DISPLAYTITLE magic works
					
					$out	 = $wgParser->parse($text, $title, $opt, true, true);
					$ttext   = $wgOut->getHTMLTitle();
					$text	 = $out->getText();

					// parse article title string and add topic name anchor tag for intramanual linking
					$articleMeta = PonyDocsArticleFactory::getArticleMetadataFromTitle($title);
					$text = '<a name="' . $articleMeta['topic'] . '"></a>' . $text;

					// prepare for replacing pre tags with code tags WEB-5926
					// derived from http://stackoverflow.com/questions/1517102/replace-newlines-with-br-tags-but-only-inside-pre-tags
					// only inside pre tag:
					//   replace space with &nbsp; only when positive lookbehind is a whitespace character
					//   replace \n -> <br/>
					//   replace \t -> 8 * &nbsp;
					/* split on <pre ... /pre>, basically.  probably good enough */
					$str = " " . $text;  // guarantee split will be in even positions
					$parts = preg_split("/(< \s* pre .* \/ \s* pre \s* >)/Umsxu", $str, -1, PREG_SPLIT_DELIM_CAPTURE);
					foreach ($parts as $idx => $part) {
						if ($idx % 2) {
							$parts[$idx] = preg_replace(
								array("/(?<=\s) /", "/\n/", "/\t/"),
								array("&nbsp;", "<br/>", "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"),
								$part
							);
						}
					}
					$str = implode('', $parts);
					/* chop off the first space, that we had added */
					$text = substr($str, 1);

					// String search and replace
					$str_search  = array('<h5>', '</h5>', '<h4>', '</h4>', '<h3>', '</h3>', '<h2>', '</h2>', '<h1>', '</h1>', '<code>', '</code>', '<pre>', '</pre>');
					$str_replace = array('<h6>', '</h6>', '<h5>', '</h5>', '<h4><font size="3"><b><i>', '</i></b></font></h4>', '<h3>', '</h3>', '<h2>', '</h2>', '<code><font size="2">', '</font></code>', '<code><font size="2">', '</font></code>');
					$text    	 = str_replace($str_search, $str_replace, $text);

					/*
					 * HTML regex tweaking prior to sending to PDF library
					 *
					 * 1 - replace intramanual links with just the anchor hash of topic name (e.g. href="#topicname")
					 * 2 - remove all non-intramanual links - strip anchor tags with href attribute whose href value doesn't start with #
					 * 3 - wrap all span tags having id attribute with <a name="[topicname]_[span_id_attr_value]"> ... </a>
					 * 4 - all anchor links' href values that contain two # characters, replace the second with _
					 * 5 - make images have absolute URLs
					 * 6 - non-printable areas
					 * 7 - comment
					 * 8 - cell padding
					 * 9 - th bgcolor
					 * 10 - td valign, align and font size
					 */
					$regex_search = array
					(
						'|<a([^\>])+href="(' . str_replace('/', '\/', $wgServer) . ')+\/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '\/' . $productName . '\/' . $versionText . '\/' . $pManual->getShortName() . '\/([^"]*)"([^\<]*)>|',
						'|<a[^\>]+href="(?!#)[^"]*"[^>]*>(.*?)</a>|',
						'|<span[^\>]+id="([^"]*)"[^>]*>(.*?)</span>|',
						'|<a([^\>])+href="#([^"]*)#([^"]*)"([^>])*>(.*?)</a>|',
						'|(<img[^>]+?src=")(/.*>)|',
						'|<div\s*class=[\'"]?noprint["\']?>.+?</div>|s',
						'|@{4}([^@]+?)@{4}|s',
						'/(<table[^>]*)/',
						'/(<th[^>]*)/',
						'/(<td[^>]*)>([^<]*)/'
					);
					
					// Table vars
					$table_extra = ' cellpadding="6"';
					$th_extra	 = ' bgcolor="#C0C0C0;"';
					$td_extra	 = ' valign="center" align="left"';
					
					$regex_replace = array
					(
						'<a${1}href="#${3}"${4}>',
						'${1}',
						'<a name="' . $articleMeta['topic'] . '_${1}">${0}</a>',
						'<a${1}href="#${2}_${3}"${4}>${5}</a>',
						"$1$wgServer$2",
						'',
						'<!--$1-->',
						"$1$table_extra",
						"$1$th_extra",
						"$1$td_extra><font size=\"2.75\">$2</font>"
					);
					
					$text  = preg_replace($regex_search, $regex_replace, $text);
					$ttext = basename($ttext);
					$html .= utf8_decode("$text\n");
				}
			}
		}

		# Write the HTML to a tmp file
		$file = "$wgUploadDirectory/".uniqid('ponydocs-pdf-book');
		$fh = fopen($file, 'w+');
		fwrite($fh, $html);
		fclose($fh);

		// Okay, create the title page
		$titlepagefile = "$wgUploadDirectory/" .uniqid('ponydocs-pdf-book-title');
		$fh = fopen($titlepagefile, 'w+');
		
		$image_path	= $wgServer . $wgStylePath . PONYDOCS_PDF_TITLE_IMAGE_PATH;
		$titleText	= '<table height="100%" width="100%"><tr><td valign="top" height="50%">'
					. '<center><img src="' . $image_path .  '" width="1024"></center>'
					. '<h1>' . $productLongName . ' ' . $versionText . '</h1>'
					. '<h2>' . $book . '</h2>'
					. 'Generated: ' . date('n/d/Y g:i a', time())
					. '</td></tr><tr><td height="50%" width="100%" align="left" valign="bottom"><font size="2">'
					. PONYDOCS_PDF_COPYRIGHT_MESSAGE
					. '</td></tr></table>';

		fwrite($fh, $titleText);
		fclose($fh);

		$format = 'manual'; 	/* @todo Modify so single topics can be printed in pdf */
		$footer = $format == 'single' ? '...' : '.1.';
		$toc	= $format == 'single' ? '' : " --toclevels $levels";

		# Send the file to the client via htmldoc converter
		$wgOut->disable();
		$cmd  = "--left $x_margin --right $x_margin --top $y_margin --bottom $y_margin";
		$cmd .= " --header ... --footer $footer --tocfooter .i. --quiet --jpeg --color";
		$cmd .= " --bodyfont $font --fontsize $size --linkstyle plain --linkcolor $linkcol";
		$cmd .= "$toc --format pdf14 $layout $width --titlefile $titlepagefile --size letter";
		$cmd  = "htmldoc -t pdf --book --charset iso-8859-1 --no-numbered $cmd $file > $pdfFileName";
		putenv("HTMLDOC_NOCGI=1");
		$output = array();
		$returnVar = 0;
		exec($cmd, $output, $returnVar);
		if($returnVar != 5) {	// Why is htmldoc's success return code 5?  Try to be different htmldoc, go for it.
			error_log("INFO [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": Failed to run htmldoc (" . $returnVar . ") Output is as follows: " . implode("-", $output));
			print("Failed to create PDF.  Our team is looking into it.");
		}
		// Delete the htmlfile and title file from the filesystem.
		@unlink($file);
		if(file_exists($file)) {
			error_log("ERROR [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": Failed to delete temp file $file");
		}
		@unlink($titlepagefile);
		if(file_exists($titlepagefile)) {
			error_log("ERROR [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": Failed to delete temp file $titlepagefile");
		}
		// Okay, let's add an entry to the error log to dictate someone 
		// requested a pdf
		error_log("INFO [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": fresh serve username=\"" . $wgUser->getName() . "\" version=\"$versionText\" " . " manual=\"" . $book . "\"");
		PonyDocsPdfBook::servePdf($pdfFileName, $productName, $versionText, $book);
		// No more processing
		return false;
	}

	/**
	 * Serves out a PDF file to the browser
	 *
	 * @param $fileName string The full path to the PDF file.
	 */
	static public function servePdf($fileName, $product, $version, $manual) {
		if(file_exists($fileName)) {
			header("Content-Type: application/pdf");
			header("Content-Disposition: attachment; filename=\"$product-$version-$manual.pdf\"");
			readfile($fileName);
			die();				// End processing right away.
		}
		else {
			return false;
		}
	}

	/**
	 * Removes a cached PDF file.  Just attempts to unlink.  However, does a 
	 * quick check to see if the file exists after the unlink.  This is a bad 
	 * situation to be in because that means cached versions will never be 
	 * removed and will continue to be served.  So log that situation.
	 *
	 * @param $manual string The short name of the manual remove
	 * @param $version string The version of the manual to remove
	 */
	static public function removeCachedFile($product, $manual, $version) {
		global $wgUploadDirectory;
		$pdfFileName = "$wgUploadDirectory/ponydocspdf-" . $product . "-" . $version . "-" . $manual . "-book.pdf";
		@unlink($pdfFileName);
		if(file_exists($pdfFileName)) {
			error_log("ERROR [PonyDocsPdfBook::removeCachedFile] " . php_uname('n') . ": Failed to delete cached pdf file $pdfFileName");
			return false;
		}
		else {
			error_log("INFO [PonyDocsPdfBook::removeCachedFile] " . php_uname('n') . ": Cache file $pdfFileName removed.");
		}
		return true;
	}

	/**
	 * Needed in some versions to prevent Special:Version from breaking
	 */
	function __toString() { return 'PonyDocsPdfBook'; }
}

/**
 * Called from $wgExtensionFunctions array when initialising extensions
 */
function wfSetupPdfBook() {
	global $wgPonyDocsPdfBook;
	$wgPonyDocsPdfBook = new PonyDocsPdfBook();
}

?>
