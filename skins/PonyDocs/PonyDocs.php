<?php
/**
 * PonyDocs Theme, based off of monobook
 * Gives ability to support documentation namespace.
 *
 * Translated from gwicke's previous TAL template version to remove
 * dependency on PHPTAL.
 *
 * @todo document
 * @file
 * @ingroup Skins
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( -1 );
}

$wgExtensionCredits['skin'][] = array(
	'path' => __FILE__,
	'name' => 'PonyDocs', // name as shown under [[Special:Version]]
	'namemsg' => 'skinname-ponydocs', // used since MW 1.24, see the section on "Localisation messages" below
	'version' => '1.0',
	'author' => '[https://mediawiki.org/wiki/User:Firebus Russell Uman]',
	'descriptionmsg' => 'ponydocs-desc', // see the section on "Localisation messages" below
);

$wgValidSkinNames['ponydocs'] = 'PonyDocs';

$wgAutoloadClasses['SkinPonyDocs'] = __DIR__ . '/SkinPonyDocs.php';
$wgAutoloadClasses['PonyDocsTemplate'] = __DIR__ . '/PonyDocsTemplate.php';

$wgMessagesDirs['PonyDocs'] = __DIR__ . '/i18n';

$wgResourceModules['skins.ponydocs.css'] = array(
	'styles' => array(
		'css/main.css' => array('media' => 'screen'),
	),
	'remoteSkinPath' => 'PonyDocs',
	'localBasePath' => __DIR__,
	'position' => 'top',
);