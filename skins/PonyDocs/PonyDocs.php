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

$wgResourceModules['skins.ponydocs.css'] = array(
	'styles' => array(
		'css/main.css' => array('media' => 'screen'),
	),
	'remoteSkinPath' => 'PonyDocs',
	'localBasePath' => __DIR__,
	'position' => 'top',
);

$wgValidSkinNames['ponydocs'] = 'PonyDocs';
$wgAutoloadClasses['SkinPonyDocs'] = __DIR__ . '/SkinPonyDocs.php';
$wgAutoloadClasses['PonyDocsTemplate'] = __DIR__ . '/PonyDocsTemplate.php';