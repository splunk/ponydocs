<?php

/**
 * Inherit main code from SkinTemplate, set the CSS and template filter.
 * @todo document
 * @ingroup Skins
 */
class SkinPonyDocs extends SkinTemplate {
	var $skinname = 'ponydocs'
		, $stylename = 'PonyDocs'
		, $template = 'PonyDocsTemplate'
		, $useHeadElement = TRUE;

	function setupSkinUserCss( OutputPage $out ) {
		global $wgHandheldStyle;

		parent::setupSkinUserCss( $out );
		$out->addModuleStyles('skins.splunk.css');

		$out->addStyle( 'css/IE50Fixes.css', 'screen', 'lt IE 5.5000' );
		$out->addStyle( 'css/IE55Fixes.css', 'screen', 'IE 5.5000' );
		$out->addStyle( 'css/IE60Fixes.css', 'screen', 'IE 6' );
		$out->addStyle( 'css/IE70Fixes.css', 'screen', 'IE 7' );
		$out->addStyle( 'css/rtl.css', 'screen', '', 'rtl' );
	}
}