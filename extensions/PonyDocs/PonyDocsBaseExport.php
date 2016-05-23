<?php
/**
 * Base Exporter which supports creating a cover page and manual html for a baseline for export operations (ZIP and PDF).
 */

abstract class PonyDocsBaseExport {

	/**
	 * Generates Cover Page HTML for specified product/manual/version
	 *
	 * @param $product PonyDocsProduct
	 * @param $manual  PonyDocsProductManual
	 * @param $version PonyDocsProductVersion
	 * @param $htmldoc boolean If HTMLDOC format (otherwise revised cover page HTML)
	 *
	 * @return string HTML String representation of cover page
	 */
	public function getCoverPageHTML($product, $manual, $version, $htmldoc = true, $title)
	{
		global $wgServer, $wgStylePath;
		$image_path	= $wgServer . $wgStylePath . PONYDOCS_PDF_TITLE_IMAGE_PATH;
		$titleText = <<<EOT
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:og="http://ogp.me/ns#" xmlns:fb="http://ogp.me/ns/fb#" charset="utf-8">
<head>
<meta charset="UTF-8">
<title></title>
<style>
html,body {
margin: 0px;
padding: 0px;
width: 210mm;
max-width: 210mm;
overflow-x: hidden;
}
</style>
</head>
<body>
EOT;
		$h1 = PonyDocsTopic::FindH1ForTitle($title->__toString());
		
		if ($htmldoc) {
			$titleText  .= '<table height="100%" width="100%"><tr><td valign="top" height="50%">'
				. '<center><img src="' . $image_path .  '" width="1024"></center>'
				. '<h1>' . $product->getLongName() . ' ' . $manual->getLongName() . ' ' . $version->getVersionName() . '</h1>'
				. '<h2>' . htmlspecialchars( $h1 ) . '</h2>'
				. 'Generated: ' . date('n/d/Y g:i a', time())
				. '</td></tr><tr><td height="50%" width="100%" align="left" valign="bottom"><font size="2">'
				. PONYDOCS_PDF_COPYRIGHT_MESSAGE
				. '</td></tr></table></body></html>';
		} else {
			// Render a none table format version.
			$titleText .= '<img src="' . $image_path . '" width="1024">'
				. '<h1 style="font-size: 32pt;">' . $product->getLongName() . ' ' . $manual->getLongName() . ' ' . $version->getVersionName() . '</h1>'
				. '<h2 style="font-size: 32pt;">' . htmlspecialchars( $h1 ) . '</h2>'
				. '<h3 style="font-size: 24pt; font-weight: normal;">Generated: ' . date('n/d/Y g:i a', time())
				. '</h3></body></html>';
		}
		return $titleText;
	}

	/**
	 * Generates an HTML string which represents the entire manual for a given product and version.
	 *
	 * @param $product PonyDocsProduct
	 * @param $manual  PonyDocsProductManual
	 * @param $version PonyDocsProductVersion
	 *
	 * @return string HTML String representation of manual contents
	 */
	public function getManualHTML($product, $manual, $version)
	{
		global $wgOut, $wgUser, $wgTitle, $wgParser, $wgRequest;
		global $wgServer, $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory, $wgScript, $wgStylePath;

		// Grab parser options for the logged in user.
		$opt = ParserOptions::newFromUser($wgUser);

		// Any potential titles to exclude
		$exclude = array();

		// Determine articles to gather
		$articles = array();

		$toc = new PonyDocsTOC($manual, $version, $product);
		list($manualtoc, $tocprev, $tocnext, $tocstart) = $toc->loadContent();

		// We successfully got our table of contents.  It's stored in $manualtoc
		foreach($manualtoc as $tocEntry) {
			if($tocEntry['level'] > 0 && strlen($tocEntry['title']) > 0) {
				$title = Title::newFromText($tocEntry['title']);
				$articles[$tocEntry['section']][] = array('title' => $title, 'text' => $tocEntry['text']);
			}
		}

		// Format the article(s) as a single HTML document with absolute URL's
		$html = <<<EOT
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:og="http://ogp.me/ns#" xmlns:fb="http://ogp.me/ns/fb#" charset="utf-8">
<head>
<meta charset="UTF-8">
<title></title>
<style>
html,body {
margin: 0px;
padding: 0px;
width: 210mm;
max-width: 210mm;
overflow-x: hidden;
}
pre {
	width: 100%;
	overflow-x: hidden;
}
</style>
</head>

<body>
EOT;
		$wgArticlePath = $wgServer.$wgArticlePath;
		$wgScriptPath = $wgServer.$wgScriptPath;
		$wgUploadPath = $wgServer.$wgUploadPath;
		$wgScript = $wgServer.$wgScript;
		$currentSection = '';
		foreach ($articles as $section => $subarticles) {
			foreach ($subarticles as $article) {
				$title = $article['title'];
				$ttext = $title->getPrefixedText();
				if (!in_array($ttext, $exclude)) {
					if($currentSection != $section) {
						$html .= '<h1>' . $section . '</h1>';
						$currentSection = $section;
					}		
					$article = new Article($title, 0);
					$text = $article->fetchContent();
					$text   .= '__NOTOC__';

					$opt->setEditSection(false);	// remove section-edit links
					$wgOut->setHTMLTitle($ttext);   // use this so DISPLAYTITLE magic works
					
					$out = $wgParser->parse($text, $title, $opt, true, true);
					$ttext = $wgOut->getHTMLTitle();
					$text = $out->getText();

					// parse article title string and add topic name anchor tag for intramanual linking
					$articleMeta = PonyDocsArticleFactory::getArticleMetadataFromTitle($title);
					$text = '<a name="' . $articleMeta['topic'] . '"></a>' . $text;

					// prepare for replacing pre tags with code tags WEB-5926 derived from
					// http://stackoverflow.com/questions/1517102/replace-newlines-with-br-tags-but-only-inside-pre-tags
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
					$text       = str_replace($str_search, $str_replace, $text);

					/*
					 * HTML regex tweaking prior to sending to PDF library
					 *
					 * 1 - replace intramanual links with just the anchor hash of topic name (e.g. href="#topicname")
					 * 2 - remove all non-intramanual links - strip anchor tags with href attribute whose href value doesn't start
					 *     with #
					 * 3 - wrap all span tags having id attribute with <a name="[topicname]_[span_id_attr_value]"> ... </a>
					 * 4 - all anchor links' href values that contain two # characters, replace the second with _
					 * 5 - make images have absolute URLs
					 * 6 - non-printable areas
					 * 7 - comment
					 * 8 - cell padding
					 * 9 - th bgcolor
					 * 10 - td valign, align and font size
					 * 
					 */
					$regex_search = array(
						'|<a([^\>]+)href="(' . str_replace('/', '\/', $wgServer) . ')+\/'
							. PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '\/' . $product->getShortName() . '\/' . $version->getVersionName() . '\/'
							. $manual->getShortName() . '\/([^"]*)"([^\<]*)>|',
						'|<a[^\>]+href="(?!#)[^"]*"[^>]*>(.*?)</a>|',
						'|<span[^\>]+id="([^"]*)"[^>]*>(.*?)</span>|',
						'|<a([^\>]+)href="#([^"]*)#([^"]*)"([^>]*)>(.*?)</a>|',
						'|(<img[^>]+?src=")(/.*>)|',
						'|<div\s*class=[\'"]?noprint["\']?>.+?</div>|s',
						'|@{4}([^@]+?)@{4}|s',
						'/(<table[^>]*)/',
						'/(<th[^>]*)/',
						'/(<td[^>]*)>([^<]*)/'
					);
					
					// Table vars
					$table_extra = ' border="1" cellpadding="6"';
					$th_extra = ' bgcolor="#C0C0C0"';
					$td_extra = ' valign="center" align="left"';
					
					$regex_replace = array(
						'<a${1}href="#${3}"${4}>',
						'${1}',
						'<a name="' . $articleMeta['topic'] . '_${1}">${0}</a>',
						'<a${1}href="#${2}_${3}"${4}>${5}</a>',
						"$1$wgServer$2",
						'',
						'<!--$1-->',
						"$1$table_extra",
						"$1$th_extra",
						"$1$td_extra>$2"
					);
					
					$text = preg_replace($regex_search, $regex_replace, $text);

					// Make all anchor tags uniformly lower case (wkhtmltopdf is case sensitive for internal links)
					$text = preg_replace_callback(
							'|<a([^\>])+href="([^"]*)"([^\<]*)>|',
							function ($matches) {
									return '<a' . $matches[1] . 'href="' . strtolower($matches[2]) . '"' . $matches[3] . '>';
							},
							$text
					);

					$text = preg_replace_callback(
							'|<a([^\>])+name="([^"]*)"([^>]*)>|',
							function ($matches) {
									return '<a' . $matches[1] . 'name="' . strtolower($matches[2]) . '"' . $matches[3] . '>';
							},
							$text
					);

					$ttext = basename($ttext);
					$html .= $text . "\n";
				}
			}
		}
		$html .= "</body></html>";
		return $html;
	}

	/**
	 * Generates an HTML string which represents the topic for a given product and version.
	 *
	 * @param $product PonyDocsProduct
	 * @param $topic  Article
	 * @param $version PonyDocsProductVersion
	 *
	 * @return string HTML String representation of manual contents
	 */
	public function getTopicHTML($product, $topic, $version)
	{
		global $wgOut, $wgUser, $wgTitle, $wgParser, $wgRequest;
		global $wgServer, $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory, $wgScript, $wgStylePath;

		// Grab parser options for the logged in user.
		$opt = ParserOptions::newFromUser($wgUser);

		// Any potential titles to exclude
		$exclude = array();

		// Determine articles to gather
		$articles = array();

		
		// Format the article(s) as a single HTML document with absolute URL's
		$html = <<<EOT
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:og="http://ogp.me/ns#" xmlns:fb="http://ogp.me/ns/fb#" charset="utf-8">
<head>
<meta charset="UTF-8">
<title></title>
<style>
html,body {
margin: 0px;
padding: 0px;
width: 210mm;
max-width: 210mm;
overflow-x: hidden;
}
pre {
	width: 100%;
	overflow-x: hidden;
}
</style>
</head>

<body>
EOT;
		$wgArticlePath = $wgServer.$wgArticlePath;
		$wgScriptPath = $wgServer.$wgScriptPath;
		$wgUploadPath = $wgServer.$wgUploadPath;
		$wgScript = $wgServer.$wgScript;
		$currentSection = '';				
		$title = $topic->getTitle();
		$topicName = new PonyDocsTopic($topic);			
		$ttext = $title->getPrefixedText();
		if (!in_array($ttext, $exclude)) {
			if ($currentSection != $section) {
				$html .= '<h1>' . $section . '</h1>';
				$currentSection = $section;
			}
			$article = new Article($title, 0);
			$text = $article->fetchContent();
			$text   .= '__NOTOC__';

			$opt->setEditSection(false);	// remove section-edit links
			$wgOut->setHTMLTitle($ttext);   // use this so DISPLAYTITLE magic works

			$out = $wgParser->parse($text, $title, $opt, true, true);
			$ttext = $wgOut->getHTMLTitle();
			$text = $out->getText();

			// parse article title string and add topic name anchor tag for intramanual linking
			$articleMeta = PonyDocsArticleFactory::getArticleMetadataFromTitle($title);
			$text = '<a name="' . $articleMeta['topic'] . '"></a>' . $text;

			// prepare for replacing pre tags with code tags WEB-5926 derived from
			// http://stackoverflow.com/questions/1517102/replace-newlines-with-br-tags-but-only-inside-pre-tags
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
			$text       = str_replace($str_search, $str_replace, $text);

			/*
			 * HTML regex tweaking prior to sending to PDF library
			 *
			 * 1 - replace intramanual links with just the anchor hash of topic name (e.g. href="#topicname")
			 * 2 - remove all non-intramanual links - strip anchor tags with href attribute whose href value doesn't start
			 *     with #
			 * 3 - wrap all span tags having id attribute with <a name="[topicname]_[span_id_attr_value]"> ... </a>
			 * 4 - all anchor links' href values that contain two # characters, replace the second with _
			 * 5 - make images have absolute URLs
			 * 6 - non-printable areas
			 * 7 - comment
			 * 8 - cell padding
			 * 9 - th bgcolor
			 * 10 - td valign, align and font size
			 *
			 */
			$regex_search = array(
				'|<a([^\>]+)href="(' . str_replace('/', '\/', $wgServer) . ')+\/'
					. PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '\/' . $product->getShortName() . '\/' . $version->getVersionName() . '\/'
					. $topicName->getBaseTopicName() . '\/([^"]*)"([^\<]*)>|',
				'|<a[^\>]+href="(?!#)[^"]*"[^>]*>(.*?)</a>|',
				'|<span[^\>]+id="([^"]*)"[^>]*>(.*?)</span>|',
				'|<a([^\>]+)href="#([^"]*)#([^"]*)"([^>]*)>(.*?)</a>|',
				'|(<img[^>]+?src=")(/.*>)|',
				'|<div\s*class=[\'"]?noprint["\']?>.+?</div>|s',
				'|@{4}([^@]+?)@{4}|s',
				'/(<table[^>]*)/',
				'/(<th[^>]*)/',
				'/(<td[^>]*)>([^<]*)/'
			);

			// Table vars
			$table_extra = ' cellpadding="6"';
			$th_extra = ' bgcolor="#C0C0C0"';
			$td_extra = ' valign="center" align="left"';

			$regex_replace = array(
				'<a${1}href="#${3}"${4}>',
				'${1}',
				'<a name="' . $articleMeta['topic'] . '_${1}">${0}</a>',
				'<a${1}href="#${2}_${3}"${4}>${5}</a>',
				"$1$wgServer$2",
				'',
				'<!--$1-->',
				"$1$table_extra",
				"$1$th_extra",
				"$1$td_extra>$2"
			);

			$text = preg_replace($regex_search, $regex_replace, $text);

			// Make all anchor tags uniformly lower case (wkhtmltopdf is case sensitive for internal links)
			$text = preg_replace_callback(
					'|<a([^\>])+href="([^"]*)"([^\<]*)>|',
					function ($matches) {
							return '<a' . $matches[1] . 'href="' . strtolower($matches[2]) . '"' . $matches[3] . '>';
					},
					$text
			);

			$text = preg_replace_callback(
					'|<a([^\>])+name="([^"]*)"([^>]*)>|',
					function ($matches) {
							return '<a' . $matches[1] . 'name="' . strtolower($matches[2]) . '"' . $matches[3] . '>';
					},
					$text
			);

			$ttext = basename($ttext);
			$html .= $text . "\n";
		}

		$html .= "</body></html>";

		return $html;
	}
}
