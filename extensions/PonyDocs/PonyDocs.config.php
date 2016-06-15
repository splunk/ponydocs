<?php

define( 'PONYDOCS_DOCUMENTATION_PRODUCTS_TITLE', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':Products' );
define( 'PONYDOCS_PRODUCT_LEGALCHARS', 'A-Za-z0-9_,.-' );
define( 'PONYDOCS_PRODUCT_REGEX', '/([' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)/' );
define( 'PONYDOCS_PRODUCT_STATIC_PREFIX', '.' );

define( 'PONYDOCS_PRODUCTVERSION_SUFFIX', ':Versions' );
define( 'PONYDOCS_PRODUCTVERSION_LEGALCHARS', 'A-Za-z0-9_,.-' );
define( 'PONYDOCS_PRODUCTVERSION_REGEX', '/([' . PONYDOCS_PRODUCTVERSION_LEGALCHARS . ']+)/' );
define( 'PONYDOCS_PRODUCTVERSION_TITLE_REGEX',
	'/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':([' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)' . PONYDOCS_PRODUCTVERSION_SUFFIX . '/' );

define( 'PONYDOCS_PRODUCTMANUAL_SUFFIX', ':Manuals' );
define( 'PONYDOCS_PRODUCTMANUAL_LEGALCHARS', 'A-Za-z0-9,.-' );
define( 'PONYDOCS_PRODUCTMANUAL_REGEX', '/([' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']+)/' );
define( 'PONYDOCS_PRODUCTMANUAL_TITLE_REGEX',
	'/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':([' . PONYDOCS_PRODUCT_LEGALCHARS . ']+)' . PONYDOCS_PRODUCTMANUAL_SUFFIX . '/' );

// Directories
define( 'PONYDOCS_STATIC_DIR', '/var/www/useruploads/docs/staticDocs' );
define( 'PONYDOCS_STATIC_PATH', 'DocumentationStatic' );
define( 'PONYDOCS_STATIC_URI', '/' . PONYDOCS_STATIC_PATH . '/' );

// Specify URI to CSS file to dynamically override static documentation iframe CSS
define( 'PONYDOCS_STATIC_CSS', '' );

// Capitalization settings
define( 'PONYDOCS_CASE_SENSITIVE_TITLES', FALSE );

// Auto-Create Topics when referenced during editing
define( 'PONYDOCS_AUTOCREATE_ON_ARTICLE_EDIT', FALSE );

// Configuration variables to map ponydoc groups to mediawiki groups
if ( !isset( $wgPonyDocsEmployeeGroup ) ) {
	$wgPonyDocsEmployeeGroup = 'employees';
}

if ( !isset( $wgPonyDocsBaseAuthorGroup ) ) {
	$wgPonyDocsBaseAuthorGroup = 'docteam';
}

if ( !isset( $wgPonyDocsBasePreviewGroup ) ) {
	$wgPonyDocsBasePreviewGroup = 'preview';
}

// Put all PonyDocs special pages into the same group
$wgSpecialPageGroups['BranchInherit'] = 'ponydocs';
$wgSpecialPageGroups['DocListing'] = 'ponydocs';
$wgSpecialPageGroups['SpecialDocumentLinks'] = 'ponydocs';
$wgSpecialPageGroups['SpecialLatestDoc'] = 'ponydocs';
$wgSpecialPageGroups['RecentProductChanges'] = 'ponydocs';
$wgSpecialPageGroups['RenameVersion'] = 'ponydocs';
$wgSpecialPageGroups['StaticDocImport'] = 'ponydocs';
$wgSpecialPageGroups['StaticDocServer'] = 'ponydocs';
$wgSpecialPageGroups['TOCList'] = 'ponydocs';
$wgSpecialPageGroups['TopicList'] = 'ponydocs';
