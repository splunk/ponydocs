<?php
/**
 * Include this file in your LocalSettings.php to activate. You can find the actual body/code in the file:
 * 		PonyDocsExtension.body.php
 * There are also a set of classes used in our extension to manage things like manuals, versions, TOC pages, and so forth,
 * all of which are included here. 
 */

/**
 * Disallow direct loading of this page (which should not be possible anyway).
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "PonyDocs MediaWiki Extension" );
}

require_once( "$IP/extensions/PonyDocs/PonyDocs.config.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsCache.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsExtension.body.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsWiki.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsTopic.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsProduct.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsProductVersion.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsProductManual.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsTOC.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsAjax.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsAliasArticle.php" );
require_once( "$IP/extensions/PonyDocs/SpecialTOCList.php" );
require_once( "$IP/extensions/PonyDocs/SpecialTopicList.php" );
require_once( "$IP/extensions/PonyDocs/SpecialLatestDoc.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsCategoryLinks.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsCategoryPageHandler.php");
require_once( "$IP/extensions/PonyDocs/SpecialDocumentLinks.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsBaseExport.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsPdfBook.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsZipExport.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsBranchInheritEngine.php");
require_once( "$IP/extensions/PonyDocs/SpecialBranchInherit.php");
require_once( "$IP/extensions/PonyDocs/SpecialDocListing.php");
require_once( "$IP/extensions/PonyDocs/SpecialRecentProductChanges.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsRenameVersionEngine.php");
require_once( "$IP/extensions/PonyDocs/SpecialRenameVersion.php");
require_once( "$IP/extensions/PonyDocs/SpecialStaticDocImport.php");

// check for empty product list
if ( !isset ( $ponyDocsProductsList ) || sizeof( $ponyDocsProductsList ) == 0) {
	$ponyDocsProductsList[] = PONYDOCS_DEFAULT_PRODUCT;
}

// append empty group for backwards compabability with "docteam" and "preview" groups
$ponyDocsProductsList[] = '';

$wgGroupPermissions[$wgPonyDocsEmployeeGroup]['read'] = true;
$wgGroupPermissions[$wgPonyDocsEmployeeGroup]['edit'] = true;
$wgGroupPermissions[$wgPonyDocsEmployeeGroup]['upload']			= true;
$wgGroupPermissions[$wgPonyDocsEmployeeGroup]['reupload']		= true;
$wgGroupPermissions[$wgPonyDocsEmployeeGroup]['reupload-shared']	= true;
$wgGroupPermissions[$wgPonyDocsEmployeeGroup]['minoredit']		= true;

// these will be tweaked in PonyDocsExtension::onUserCan()
$editorPerms = array(
	'move' => true,
	'edit' => true,
	'read' => true,
	'createpage' => true,
	'block' => true,
	'createaccount' => true,
	'delete' => true,
	'editinterface' => true,
	'import' => true,
	'importupload' => true,
	'move' => true,
	'patrol' => true,
	'autopatrol' => true,
	'protect' => true,
	'proxyunbannable' => true,
	'rollback' => true,
	'trackback' => true,
	'upload' => true,
	'reupload' => true,
	'reupload-shared' => true,
	'unwatchedpages' => true,
	'autoconfirmed' => true,
	'upload_by_url' => true,
	'ipblock-exempt' => true,
	'blockemail' => true,
	'deletedhistory' => true, // Can view deleted history entries, but not see or restore the text
	'branchtopic' => true, // Custom permission to branch a single topic.
	'branchmanual' => true, // Custom permission to branch an entire manual.
	'inherit' => true, // Custom permission to inherit a topic.
	'viewall' => true, // Custom permission to handle View All link for topics.
);
	
foreach ( $ponyDocsProductsList as $product ) {
	
	// check for empty product
	if ( $product == '' ) {
		// allow for existing product-less base groups
		$convertedNameProduct = $wgPonyDocsBaseAuthorGroup;
		$convertedNamePreview = $wgPonyDocsBasePreviewGroup;
	} else {
		// TODO: this should be a function that is shared instead
		// of being local, redundant logic
		$legalProduct = preg_replace( '/([^' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)/', '', $product );

		$convertedNameProduct = $legalProduct.'-'.$wgPonyDocsBaseAuthorGroup;
		$convertedNamePreview = $legalProduct.'-'.$wgPonyDocsBasePreviewGroup;
	}

	// push the above perms array into each product
	$wgGroupPermissions[$convertedNameProduct] = $editorPerms;
	
	// define one preview group as well
	$wgGroupPermissions[$convertedNamePreview]['read'] = true;
}

// make sure we have the name space
if ( !isset ( $wgExtraNamespaces[PONYDOCS_DOCUMENTATION_NAMESPACE_ID] ) ) {
	$wgExtraNamespaces[PONYDOCS_DOCUMENTATION_NAMESPACE_ID] = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME;
}

/**
 * Setup credits for this extension to appear in the credits page of wiki.
 */
$wgExtensionCredits['other'][] = array(
	'name' => 'PonyDocs Customized MediaWiki', 
	'author' => 'Splunk',
	'svn-date' => '$LastChangedDate$',
	'svn-revision' => '$LastChangedRevision: 207 $',
	'url' => 'http://www.splunk.com',
	'description' => 'Provides custom support for product documentation' );

/**
 * SVN revision #. This requires we enable this property in svn for this file:
 * svn propset ?:? "Revision" <file>
 */
$wgRevision = '$Revision: 207 $';

/**
 * Register the setup function for the extension.
 * This should setup all hooks to be used, any pre-setup, and so forth done upon initialization of a request.
 * I am sticking to the naming convention of 'ef' prefixing for 'extension function'. Also setup parser setup functions here.
 * 
 * I'd like to move all of this into PonyDocsExtension;
 * i.e. have a registerHooks method which does all of this and passes array( $this, 'methodName' ) for each
 * instead of using static methods?
 */
$wgExtensionFunctions[] = 'efPonyDocsSetup';
$wgExtensionFunctions[] = 'efManualParserFunction_Setup';
$wgExtensionFunctions[] = 'efVersionParserFunction_Setup';
$wgExtensionFunctions[] = 'efProductParserFunction_Setup';
$wgExtensionFunctions[] = 'efTopicParserFunction_Setup';
$wgExtensionFunctions[] = 'efManualDescriptionParserFunction_Setup';

/**
 * Our magic words for our custom parser functions.
 */
$wgHooks['LanguageGetMagic'][] = 'efManualParserFunction_Magic';
$wgHooks['LanguageGetMagic'][] = 'efVersionParserFunction_Magic';
$wgHooks['LanguageGetMagic'][] = 'efProductParserFunction_Magic';
$wgHooks['LanguageGetMagic'][] = 'efTopicParserFunction_Magic';
$wgHooks['LanguageGetMagic'][] = 'efManualDescriptionParserFunction_Magic';

/**
 * Create a single global instance of our extension.
 */
$wgPonyDocs = new PonyDocsExtension();

/**
 * Our primary setup function simply handles URL rewrites for aliasing (per spec) and calls our PonyDocsWiki singleton instance
 * to ensure it runs the data retrieval functions for versions and manuals and the like. 
 */
function efPonyDocsSetup() {
	global $wgPonyDocs, $wgScriptPath, $wgArticlePath;
	// force mediawiki to start session for anonymous traffic
	if ( session_id() == '' ) {
		wfSetupSession();
		if ( PONYDOCS_SESSION_DEBUG ) {
			error_log( "DEBUG [" . __METHOD__ . "] started session" );
		}
	}
	// Set selected product from URL
	if ( preg_match(
		'/^' . str_replace("/", "\/", $wgScriptPath ) . '\/((index.php\?title=)|)'
			. PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '[\/|:]{1}([' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)[\/|:]?/i',
		$_SERVER['PATH_INFO'],
		$match ) ) {
		PonyDocsProduct::SetSelectedProduct( $match[3] );
	}
	
	// Set selected version from URL
	// - every time from /-separated title URLs
	// - only when no selected version already set from :-separated title
	$currentVersion = PonyDocsProductVersion::GetSelectedVersion( PonyDocsProduct::GetSelectedProduct(), FALSE );
	if ( preg_match(
		'/^' . str_replace("/", "\/", $wgScriptPath) . '\/((index.php\?title=)|)' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME
			. '\/(['.PONYDOCS_PRODUCT_LEGALCHARS.']+)\/(['.PONYDOCS_PRODUCTVERSION_LEGALCHARS.']+)/i', $_SERVER['PATH_INFO'],
		$match)
		|| preg_match(
			'/^' . str_replace("/", "\/", $wgScriptPath) . '\/((index.php\?title=)|)' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME
				. '\/([' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)\/[' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']+TOC(['
				. PONYDOCS_PRODUCTVERSION_LEGALCHARS.']+)/i',
			$_SERVER['PATH_INFO'],
			$match )
		|| ( !isset( $currentVersion )
			&& preg_match(
				'/^' . str_replace("/", "\/", $wgScriptPath) . '\/((index.php\?title=)|)' . PONYDOCS_DOCUMENTATION_PREFIX . '(['
					. PONYDOCS_PRODUCT_LEGALCHARS . ']+):[' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']+TOC(['
					. PONYDOCS_PRODUCTVERSION_LEGALCHARS . ']+)/i',
				$_SERVER['PATH_INFO'],
				$match ) )
		|| ( !isset($currentVersion )
			&& preg_match(
				'/^' . str_replace("/", "\/", $wgScriptPath) . '\/((index.php\?title=)|)' . PONYDOCS_DOCUMENTATION_PREFIX . '(['
					. PONYDOCS_PRODUCT_LEGALCHARS . ']+):[' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']+:[^:]+:(['
					. PONYDOCS_PRODUCTVERSION_LEGALCHARS . ']+)/i',
				$_SERVER['PATH_INFO'],
				$match ) ) ) {
		$result = PonyDocsProductVersion::SetSelectedVersion( $match[3], $match[4] );
		if ( is_null( $result ) ) {
			// this version isn't available to this user; go away
			$defaultRedirect = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME, $wgArticlePath );
			if ( PONYDOCS_REDIRECT_DEBUG ) {
				error_log( "DEBUG [" . __METHOD__ . ":" . __LINE__ . "] redirecting to $defaultRedirect" );
			}
			header( "Location: " . $defaultRedirect );
			exit;
		}
	}
	PonyDocsWiki::getInstance( PonyDocsProduct::GetSelectedProduct() );
}

/**
 * This section handles the parser function {{#manual:<shortName>|<longName>}} which defines a manual.
 */
function efManualParserFunction_Setup() {
	global $wgParser;
	$wgParser->setFunctionHook( 'manual', 'efManualParserFunction_Render' );
}

function efManualParserFunction_Magic( &$magicWords, $langCode ) {
	$magicWords['manual'] = array( 0, 'manual' );
	return true;
}

/**
 * This is called when {{#manual:short|long}} is found in an article content. It should produce an output
 * set of HTML which provides the name (long) as a link to the most recent (based on version tags) TOC
 * management page for that manual.
 *
 * @param Parser $parser
 * @param string $param1 Short name of the manual used in links.
 * @param string $param2 Long/display name of manual.
 * @return array
 */
function efManualParserFunction_Render( &$parser, $param1 = '', $param2 = '' ) {
	global $wgArticlePath, $wgUser, $wgScriptPath;

	$valid = TRUE;
	if ( !preg_match( PONYDOCS_PRODUCTMANUAL_REGEX, $param1 ) || !strlen( $param1 ) || !strlen( $param2 ) ) {
		return $parser->insertStripItem( '', $parser->mStripState );
	}

	$manualName = preg_replace( '/([^' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']+)/', '', $param1 );
	$productName = PonyDocsProduct::GetSelectedProduct();
	$version = PonyDocsProductVersion::GetSelectedVersion( $productName );

	// don't cache Documentation:[product]:Manuals pages because when we switch selected version the content will come from cache
	$parser->disableCache();

	$dbr = wfGetDB( DB_SLAVE );
	$res = $dbr->select(
		'categorylinks',
		array( 'cl_sortkey', 'cl_to' ),
		array(
			"LOWER( cast( cl_sortkey AS CHAR ) ) LIKE 'documentation:" . $dbr->strencode( strtolower( $productName ) ) . ':'
				. $dbr->strencode( strtolower( $manualName ) ) . "toc%'",
			"cl_to = 'V:" . $productName . ':' . $version . "'" ),
		__METHOD__ );
	if ( !$res->numRows() )	{
		/**
		 * Link to create new TOC page -- should link to current version TOC and then add message to explain.
		 */
		$output = '<p><a href="'
			. str_replace(
				'$1', PONYDOCS_DOCUMENTATION_PREFIX . $productName . ':' . $manualName . 'TOC' . $version,
				$wgArticlePath )
			. '" style="font-size: 1.3em;">' . $param2 . "</a></p>\n"
			. "<span style=\"padding-left: 20px;\">Click manual to create TOC for current version (" . $version . ").</span>\n";
	} else {
		$row = $dbr->fetchObject( $res );
		$output = '<p><a href="' . str_replace( '$1', $row->cl_sortkey, $wgArticlePath ) . '" style="font-size: 1.3em;">'
			. $param2 . "</a></p>\n";
	}

	return $parser->insertStripItem( $output, $parser->mStripState );
}

/**
 * This section handles the parser function {{#version:<name>|<status>}} which defines a version.
 */
function efVersionParserFunction_Setup() {
	global $wgParser;
	$wgParser->setFunctionHook( 'version', 'efVersionParserFunction_Render' );
	$wgParser->setFunctionHook( 'versiongroup', 'efVersionGroupParserFunction_Render' );
}

function efVersionParserFunction_Magic( &$magicWords, $langCode ) {
	$magicWords['version'] = array( 0, 'version' );
	$magicWords['versiongroup'] = array( 0, 'versiongroup' );
	return TRUE;
}

/**
 * The version parser function is of the form:
 * 	{{#version:name|status}}
 * Which defines a version and its state. When output it currently does nothing but should perhaps be a list to Category:<version>.
 *
 * @param Parser $parser
 * @param string $param1 The version name itself.
 * @param string $param2 The status of the version (released, unreleased, or preview).
 * @return array
 */
function efVersionParserFunction_Render( &$parser, $param1 = '', $param2 = '' ) {
	global $wgUser, $wgScriptPath;
	
	$valid = TRUE;

	if ( !preg_match( PONYDOCS_PRODUCTVERSION_REGEX, $param1 ) ) {
		$valid = FALSE;
	}
	if ( ( strcasecmp( $param2, 'released' ) != 0 )
		&& ( strcasecmp( $param2, 'unreleased' ) != 0 )
		&& ( strcasecmp( $param2, 'preview' ) != 0 ) ) {
		$valid = FALSE;
	}
		
	$output = 'Version ' . $param1 . ' (' . $param2 . ') ' ;
	
	if ( !$valid ) {
		$output .= ' - Invalid Version Name or Status, Please Fix';
	}
	$output .= "\n";

	return $parser->insertStripItem( $output, $parser->mStripState );
}

/**
 * The version group parser function is of the form:
 * 	{{#versiongroup:name|message}}
 * Which defines a version group and its message.
 *
 * @param Parser $parser
 * @param string $param1 The version group name itself.
 * @param string $param2 The message of the version group.
 * @return array
 */
function efVersionGroupParserFunction_Render( &$parser, $param1 = '', $param2 = '' ) {
	global $wgUser, $wgScriptPath;

	if ( $param1 != '' ) {
		$output = 'Version Group: ' . $param1 . ' (' . $param2 . ') ' ;
		$output .= '<hr/>';
	} else {
		$output = '<hr/>';
	}

	$output .= "\n";

	return $parser->insertStripItem( $output, $parser->mStripState );
}

/**
 * This section handles the parser function {{#product:<name>|<long_name>|<parent>}} which defines a product.
 */
function efProductParserFunction_Setup() {
	global $wgParser;
	$wgParser->setFunctionHook( 'product', 'efProductParserFunction_Render' );
}

function efProductParserFunction_Magic( &$magicWords, $langCode ) {
	$magicWords['product'] = array( 0, 'product' );
	return TRUE;
}

/**
 * The product parser function is of the form {{#product:name|long_name|description|parent}}
 * Which defines a product and its state.
 * When output it currently does nothing but should perhaps be a list to Category:<product>.
 *
 * @param Parser $parser
 * @param string $shortName The product name itself.
 * @param string $longName The long product name.
 * @param string $description The product description
 * @param string $parent The short name of the parent product
 *
 * @return array
 */
function efProductParserFunction_Render( &$parser, $shortName = '', $longName = '', $description = '', $parent = '' ) {
	global $wgUser, $wgScriptPath;
	
	$output = "$shortName ($longName)";

	// Invalid $shortName
	if ( !preg_match(PONYDOCS_PRODUCT_REGEX, $shortName ) ) {
		$output .= ' - Invalid Product Name, Please Fix';
	}
	
	if ( $description != '' ) {
		$output .= "<br>$description";
	}
	
	if ( $parent != '' ) {
		$output .= "<br>Parent: $parent";
	}
	
	$output .= "\n";

	return $parser->insertStripItem( $output, $parser->mStripState );
}

/**
 * Our topic parser functions used in TOC management to define a topic to be listed within a section.
 * This is simply the form {{#topic:Name of Topic}}
 */

function efTopicParserFunction_Setup() {
	global $wgParser;
	$wgParser->setFunctionHook( 'topic', 'efTopicParserFunction_Render' );
}

function efManualDescriptionParserFunction_Setup() {
	global $wgParser;
	$wgParser->setFunctionHook( 'manualDescription', 'efManualDescriptionParserFunction_Render' );
}

function efTopicParserFunction_Magic( &$magicWords, $langCode ) {
	$magicWords['topic'] = array( 0, 'topic' );
	return TRUE;
}

function efManualDescriptionParserFunction_Magic( &$magicWords, $langCode ) {
	$magicWords['manualDescription'] = array( 0, 'manualDescription' );
	return TRUE;
}

function efGetTitleFromMarkup( $markup = '' ) {
	global $wgArticlePath, $wgTitle, $action;

	/**
	 * We ignore this parser function if not in a TOC management page.
	 */
	if ( !preg_match( '/' . PONYDOCS_DOCUMENTATION_PREFIX . '(.*):(.*)TOC(.*)/i', $wgTitle->__toString(), $matches ) ) {
		return FALSE;
	}

	$manualShortName = $matches[2];
	$productShortName = $matches[1];

	PonyDocsWiki::getInstance( $productShortName );

	/**
	 * Get the earliest tagged version of this TOC page and append it to the wiki page?
	 * Ensure the manual is valid then use PonyDocsManual::getManualByShortName().
	 * Next attempt to get the version tags for this page -- which may be NONE --
	 * and from this determine the "earliest" version to which this page applies.
	 */
	if ( !PonyDocsProductManual::IsManual( $productShortName, $manualShortName ) ) {
		return FALSE;
	}

	$pManual = PonyProductDocsManual::GetManualByShortName( $productShortName, $manualShortName );
	$pTopic = new PonyDocsTopic( new Article( $wgTitle ) );

	/**
	 * @FIXME: If TOC page is NOT tagged with any versions we cannot create the pages/links to the topics, right?
	 */
	$manVersionList = $pTopic->getProductVersions();
	if ( !sizeof( $manVersionList ) ) {
		return $parser->insertStripItem( $param1, $parser->mStripState );
	}
	$earliestVersion = PonyDocsProductVersion::findEarliest( $productShortName, $manVersionList );

	/**
	 * Clean up the full text name into a wiki-form. This means remove spaces, #, ?, and a few other
	 * characters which are not valid or wanted. It's not important HOW this is done as long as it is
	 * consistent.
	 */
	$wikiTopic = preg_replace( '/([^' . str_replace( ' ', '', Title::legalChars()) . '])/', '', $param1 );
	$wikiPath = PONYDOCS_DOCUMENTATION_PREFIX . $productShortName . ':' . $manualShortName . ':' . $wikiTopic;

	$dbr = wfGetDB( DB_SLAVE );

	/**
	 * Now look in the database for any instance of this topic name PLUS :<version>.
	 * We need to look in  categorylinks for it to find a record with a cl_to (version tag)
	 * which is equal to the set of the versions for this TOC page.
	 * For instance, if the TOC page was for versions 1.0 and 1.1 and our topic was 'How To Foo'
	 * we need to find any cl_sortkey which is 'HowToFoo:%' and has a cl_to equal to 1.0 or 1.1.
	 * There should only be 0 or 1, so we ignore anything beyond 1.
	 * If found, we use THAT cl_sortkey as the link;
	 * if NOT found we create a new topic, the name being the compressed topic name plus the earliest TOC version
	 * ($earliestVersion->getName()).
	 * We then need to ACTUALLY create it in the database, tag it with all the versions the TOC mgmt page is tagged with,
	 * and set the H1 to the text inside the parser function.
	 * 
	 * @fixme: Can we test if $action=save here so we don't do this on every page view? 
	 */

	$versionIn = array();
	foreach( $manVersionList as $pV ) {
		$versionIn[] = $pV->getVersionName();
	}

	$res = $dbr->select(
		'categorylinks',
		'cl_sortkey',
		array(
			"LOWER(cast(cl_sortkey AS CHAR)) LIKE 'documentation:"
				. $dbr->strencode( strtolower( $manualShortName . ':' . $wikiTopic ) ) . ":%'",
			"cl_to IN ('V:$productShortName:" . implode( "','V:$productShortName:", $versionIn ) . "')" ),
			__METHOD__ );

	$topicName = '';
	if ( !$res->numRows() ) {
		/**
		 * No match -- so this is a "new" topic. Set name.
		 */
		$topicName = PONYDOCS_DOCUMENTATION_PREFIX . $productShortName . ':' . $manualShortName . ':' . $wikiTopic . ':'
			. $earliestVersion->getVersionName();
	} else {
		$row = $dbr->fetchObject( $res );
		$topicName = $row->cl_sortkey;
	}

	return $topicName;
}

/**
 * This expects to find:
 * 	{{#topic:Text Name}}
 *
 * @param Parser $parser
 * @param string $param1 Full text name of topic, must be converted to wiki topic name.
 * @return array
 * 
 * TODO: Much of this function duplicates code above in efGetTitleFromMarkup(), can we DRY?
 *       There really shouldn't be any real code in this file, just calls to class methods...
 */
function efTopicParserFunction_Render( &$parser, $param1 = '' ) {
	global $wgArticlePath, $wgTitle, $action;

	if ( PonyDocsExtension::isSpeedProcessingEnabled() ) {
		return TRUE;
	}

	/**
	 * We ignore this parser function if not in a TOC management page.
	 */
	if ( !preg_match( '/' . PONYDOCS_DOCUMENTATION_PREFIX . '(.*):(.*)TOC(.*)/i', $wgTitle->__toString(), $matches ) ) {
		return FALSE;
	}

	$manualShortName = $matches[2];
	$productShortName = $matches[1];

	PonyDocsWiki::getInstance( $productShortName );

	/**
	 * Get the earliest tagged version of this TOC page and append it to the wiki page?
	 * Ensure the manual is valid then use PonyDocsManual::getManualByShortName().
	 * Next attempt to get the version tags for this page -- which may be NONE -- 
	 * and from this determine the "earliest" version to which this page applies.
	 * 
	 * TODO: This comment is duplicated above in efGetTitleFromMarkup, can we DRY?
	 */	
	if ( !PonyDocsProductManual::IsManual( $productShortName, $manualShortName ) ) {
		return FALSE;
	}

	$pManual = PonyDocsProductManual::GetManualByShortName( $productShortName, $manualShortName );
	$pTopic = new PonyDocsTopic( new Article( $wgTitle ) );
	
	/**
	 * @FIXME: If TOC page is NOT tagged with any versions we cannot create the pages/links to the 
	 * topics, right?
	 */
	$manVersionList = $pTopic->getProductVersions();

	if ( !sizeof( $manVersionList ) ) {
		return $parser->insertStripItem($param1, $parser->mStripState);
	}
	$earliestVersion = PonyDocsProductVersion::findEarliest( $productShortName, $manVersionList );

	/**
	 * Clean up the full text name into a wiki-form. This means remove spaces, #, ?, and a few other
	 * characters which are not valid or wanted. It's not important HOW this is done as long as it is
	 * consistent.
	 */
	$wikiTopic = preg_replace( '/([^' . str_replace( ' ', '', Title::legalChars() ) . '])/', '', $param1 );
	$wikiPath = PONYDOCS_DOCUMENTATION_PREFIX . $productShortName . ':' . $manualShortName . ':' . $wikiTopic;

	$dbr = wfGetDB( DB_SLAVE );

	/**
	 * Now look in the database for any instance of this topic name PLUS :<version>.
	 * We need to look in categorylinks for it to find a record with a cl_to (version tag)
	 * which is equal to the set of the versions for this TOC page.
	 * For instance, if the TOC page was for versions 1.0 and 1.1 and our topic was 'How To Foo'
	 * we need to find any cl_sortkey which is 'HowToFoo:%' and has a cl_to equal to 1.0 or 1.1.
	 * There should only be 0 or 1, so we ignore anything beyond 1.
	 * If found, we use THAT cl_sortkey as the link;
	 * if NOT found we create a new topic, the name being the compressed topic name plus the earliest TOC version
	 * ($earliestVersion->getName()).
	 * We then need to ACTUALLY create it in the database, tag it with all the versions the TOC mgmt page is tagged with,
	 * and set the H1 to the text inside the parser function.
	 * 
	 * @fixme: Can we test if $action=save here so we don't do this on every page view? 
	 */

	$versionIn = array();
	foreach( $manVersionList as $pV ) {
		$versionIn[] = $productShortName . ':' . $pV->getVersionName();
	}

	$res = $dbr->select(
		'categorylinks',
		'cl_sortkey',		
		array(
			"LOWER(cl_sortkey) LIKE 'documentation:"
				. $dbr->strencode( strtolower( $productShortName . ':' . $manualShortName . ':' . $wikiTopic ) ) . ":%'",
			"cl_to IN ('V:" . implode( "','V:", $versionIn ) . "')" ),
		__METHOD__ );

	$topicName = '';
	if ( !$res->numRows() ) {
		/**
		 * No match -- so this is a "new" topic. Set name.
		 */
		$topicName = PONYDOCS_DOCUMENTATION_PREFIX . $productShortName . ':' . $manualShortName . ':' . $wikiTopic . ':'
			. $earliestVersion->getVersionName();
	} else {
		$row = $dbr->fetchObject( $res );
		$topicName = $row->cl_sortkey;
	}

	$output = '<a href="' . wfUrlencode( str_replace( '$1', $topicName, $wgArticlePath ) ) . '">' . $param1 . '</a>'; 
	return $parser->insertStripItem( $output, $parser->mStripState );
}

/**
 * This expects to find:
 * 	{{#manualDescription:Text Description}}
 *
 * @param Parser $parser
 * @param string $param1 Full text of manual description, must be converted to rendered format.
 * @return mixed This returns TRUE if PonyDocsExtension::isSpeedProcessingEnabled() is TRUE, FALSE if we are not on a TOC page and returns a formated string if we are.
 */
function efManualDescriptionParserFunction_Render( &$parser, $param1 = '' ) {
	global $wgTitle;

	if ( PonyDocsExtension::isSpeedProcessingEnabled() ) {
		return TRUE;
	}

	/**
	 * We ignore this parser function if not in a TOC management page.
	 */
	if ( !preg_match(
		'/' . PONYDOCS_DOCUMENTATION_PREFIX . '([' . PONYDOCS_PRODUCT_LEGALCHARS.']*):([' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS
			. ']*)TOC([' . PONYDOCS_PRODUCTVERSION_LEGALCHARS.']*)/i',
		$wgTitle->__toString(),
		$matches) )	{
		return FALSE;
	}
	
	return '<h3>Manual Description: </h3><h4>' . $param1 . '</h4>'; // Return formated output
}

/**
 * Setup our 'hooks' here. We do this by assigning function names to the $wgHooks array.
 * The key of this array is the NAME of the hook, of which there are a predefined set of hooks within MediaWiki's core.
 * At a given point in the execution it will activate all hook functions and execute them IN THE ORDER DEFINED. For instance:
 * 
 * 	$wgHooks['someeventname'][] = 'functionName';
 * 
 * The value assigned to it can vary depending on the circumstances,
 * such as whether the hook is an object method, a function, and whether or not it is to be passed any arguments
 * (this is determined by wfRunHooks though and the event TYPE/NAME, not by the function being called).
 * You can also supply it with some optional data to be passed to the function when it is called.
 * 
 * A quick summary of the options:
 * - $wgHooks['event'][] = 'function'; Function with only default params.
 * - $wgHooks['event'][] = array( 'function', $someArg );
 *   Function with additional data to be passed to function($someArg, $param1, $param2, etc.)
 * - $wgHooks['event'][] = array( $object, 'method' ); Call 'method' within an object.
 * - $wgHooks['event'][] = array( $Object, 'method', $arg);
 * 
 * Returns true if successful, string on error (with msg),
 * false means success but halts processing of the hook (no further hooks for event are run).
 * 
 * More details and list of hooks @ http://www.mediawiki.org/wiki/Manual:Hooks
 */

$wgHooks['BeforePageDisplay'][] = 'PonyDocsExtension::onBeforePageDisplay';
$wgHooks['ArticleSave'][] = 'PonyDocsExtension::onArticleSave';
$wgHooks['ArticleSaveComplete'][] = 'PonyDocsExtension::onArticleSave_CheckTOC';
$wgHooks['ArticleSave'][] = 'PonyDocsExtension::onArticleSave_AutoLinks';
$wgHooks['AlternateEdit'][] = 'PonyDocsExtension::onEdit_TOCPage';
$wgHooks['UnknownAction'][] = 'PonyDocsZipExport::onUnknownAction';
$wgHooks['UnknownAction'][] = 'PonyDocsExtension::onUnknownAction';
$wgHooks['ParserBeforeStrip'][] = 'PonyDocsExtension::onParserBeforeStrip';
$wgHooks['AlternateEdit'][] = 'PonyDocsExtension::onEdit';
$wgHooks['userCan'][] = 'PonyDocsExtension::onUserCan';
$wgHooks['GetFullURL'][] = 'PonyDocsExtension::onGetFullURL';
$wgHooks['ArticleFromTitle'][] = 'PonyDocsExtension::onArticleFromTitleStatic';
$wgHooks['ArticleFromTitle'][] = 'PonyDocsExtension::onArticleFromTitleQuickLookup';
$wgHooks['CategoryPageView'][] = 'PonyDocsCategoryPageHandler::onCategoryPageView';
$wgHooks['ArticleDelete'][] = 'PonyDocsExtension::onArticleDelete';
$wgHooks['ArticleSaveComplete'][] = 'PonyDocsExtension::onArticleSaveComplete';

// Add version field to edit form
$wgHooks['EditPage::showEditForm:fields'][] = 'PonyDocsExtension::onShowEditFormFields';