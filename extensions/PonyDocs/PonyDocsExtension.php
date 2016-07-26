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

// TODO: can we use $wgAutoLoadClasses[] for this instead?
require_once( "$IP/extensions/PonyDocs/PonyDocsExtension.body.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocs.config.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsAjax.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsBaseExport.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsBranchInheritEngine.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsCache.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsCategoryLinks.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsCategoryPageHandler.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsCrawlerPassthrough.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsParsers.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsPdfBook.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsProduct.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsProductManual.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsProductVersion.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsRenameVersionEngine.php");
require_once( "$IP/extensions/PonyDocs/PonyDocsStaticDocImporter.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsTOC.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsTopic.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsVariables.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsWiki.php" );
require_once( "$IP/extensions/PonyDocs/PonyDocsZipExport.php");
require_once( "$IP/extensions/PonyDocs/SpecialBranchInherit.php");
require_once( "$IP/extensions/PonyDocs/SpecialDocumentLinks.php");
require_once( "$IP/extensions/PonyDocs/SpecialLatestDoc.php");
require_once( "$IP/extensions/PonyDocs/SpecialRecentProductChanges.php");
require_once( "$IP/extensions/PonyDocs/SpecialRenameVersion.php");
require_once( "$IP/extensions/PonyDocs/SpecialStaticDocImport.php");
require_once( "$IP/extensions/PonyDocs/SpecialStaticDocServer.php");
require_once( "$IP/extensions/PonyDocs/SpecialTOCList.php" );
require_once( "$IP/extensions/PonyDocs/SpecialTopicList.php" );

/**
 * Setup credits for this extension to appear in the credits page of wiki.
 * TODO: Fix for github
 */
$wgExtensionCredits['other'][] = array(
	'name' => 'PonyDocs Customized MediaWiki', 
	'author' => 'Splunk',
	'svn-date' => '$LastChangedDate$',
	'svn-revision' => '$LastChangedRevision: 207 $',
	'url' => 'http://docs.splunk.com',
	'description' => 'Provides custom support for product documentation'
);

$wgExtensionCredits['variable'][] = array(
	'name' => 'PonyDocsMagic',
	'author' => 'Splunk',
	'version' => '1.0',
	'svn-date' => '$LastChangedDate$',
	'svn-revision' => '$LastChangedRevision: 207 $',
	'url' => 'http://docs.splunk.com',
	'description' => 'Provides product, version, manual, and topic variables for PonyDocs',
);

/**
 * SVN revision #. This requires we enable this property in svn for this file:
 * svn propset ?:? "Revision" <file>
 * TODO: fix for github
 */
$wgRevision = '$Revision: 207 $';

/**
 * Register article hooks using side-effects in constructor.
 * TODO: hook registration should move to the bottom of this file, URL logic should move to PonyDocsWiki
 */
$ponydocs = new PonyDocsExtension();

/**
 * Register a module for our scripts and css
 */
$wgResourceModules['ext.PonyDocs'] = array(
	'scripts' => 'js/docs.js',
	'dependencies' => 'jquery.json',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'PonyDocs',
	'position' => 'top',
);

// check for empty product list
if ( !isset ( $ponyDocsProductsList ) || sizeof( $ponyDocsProductsList ) == 0) {
	$ponyDocsProductsList[] = PONYDOCS_DEFAULT_PRODUCT;
}

/**
 * User Rights
 */

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

/**
 * Setup
 */

$wgExtensionFunctions[] = 'PonyDocsExtension::efPonyDocsSetup';

/**
 * Parser methods to run on start-up
 */
$wgExtensionFunctions[] = 'PonyDocsParsers::efManualParserFunction_Setup';
$wgExtensionFunctions[] = 'PonyDocsParsers::efVersionParserFunction_Setup';
$wgExtensionFunctions[] = 'PonyDocsParsers::efProductParserFunction_Setup';
$wgExtensionFunctions[] = 'PonyDocsParsers::efTopicParserFunction_Setup';
$wgExtensionFunctions[] = 'PonyDocsParsers::efManualDescriptionParserFunction_Setup';

/**
 * Magic words for parser methods
 */
$wgHooks['LanguageGetMagic'][] = 'PonyDocsParsers::efManualParserFunction_Magic';
$wgHooks['LanguageGetMagic'][] = 'PonyDocsParsers::efVersionParserFunction_Magic';
$wgHooks['LanguageGetMagic'][] = 'PonyDocsParsers::efProductParserFunction_Magic';
$wgHooks['LanguageGetMagic'][] = 'PonyDocsParsers::efTopicParserFunction_Magic';
$wgHooks['LanguageGetMagic'][] = 'PonyDocsParsers::efManualDescriptionParserFunction_Magic';

/**
 * Create a single global instance of our extension.
 */
$wgPonyDocs = new PonyDocsExtension();

/**
 * Register a module for our scripts and css
 */
$wgResourceModules['ext.PonyDocs'] = array(
	'scripts' => 'js/docs.js',
	'dependencies' => 'jquery.json',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'PonyDocs',
	'position' => 'top',
);

/**
 * Variables
 */

$wgExtensionMessagesFiles['PonyDocsMagic'] = __DIR__ . '/PonyDocs.i18n.magic.php';
$wgHooks['ParserGetVariableValueSwitch'][] = 'PonyDocsVariables::wfPonyDocsGetVariableValueSwitch';
$wgHooks['MagicWordwgVariableIDs'][] = 'PonyDocsVariables::wfPonyDocsMagicWordwgVariableIDs';

/**
 * Hooks
 * More details and list of hooks @ http://www.mediawiki.org/wiki/Manual:Hooks
 */

$wgHooks['ArticleDelete'][] = 'PonyDocsExtension::onArticleDelete';
$wgHooks['ArticleFromTitle'][] = 'PonyDocsExtension::onArticleFromTitleStatic';
$wgHooks['ArticleFromTitle'][] = 'PonyDocsExtension::onArticleFromTitleQuickLookup';
$wgHooks['ArticleSave'][] = 'PonyDocsExtension::onArticleSave';
$wgHooks['ArticleSave'][] = 'PonyDocsExtension::onArticleSave_AutoLinks';
$wgHooks['ArticleSaveComplete'][] = 'PonyDocsExtension::onArticleSave_CheckTOC';
$wgHooks['ArticleSaveComplete'][] = 'PonyDocsExtension::onArticleSaveComplete';
$wgHooks['AlternateEdit'][] = 'PonyDocsExtension::onEdit_TOCPage';
$wgHooks['BeforePageDisplay'][] = 'PonyDocsExtension::onBeforePageDisplay';
$wgHooks['CategoryPageView'][] = 'PonyDocsCategoryPageHandler::onCategoryPageView';
$wgHooks['EditPage::showEditForm:fields'][] = 'PonyDocsExtension::onShowEditFormFields';
$wgHooks['GetFullURL'][] = 'PonyDocsExtension::onGetFullURL';
$wgHooks['ParserBeforeStrip'][] = 'PonyDocsExtension::onParserBeforeStrip';
$wgHooks['UnknownAction'][] = 'PonyDocsZipExport::onUnknownAction';
$wgHooks['UnknownAction'][] = 'PonyDocsExtension::onUnknownAction';
$wgHooks['userCan'][] = 'PonyDocsExtension::onUserCan';