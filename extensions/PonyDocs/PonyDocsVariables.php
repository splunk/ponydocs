<?php

/**
 * Hooks to provide product, manual, and version variables
 */
class PonyDocsVariables {
	static public function wfPonyDocsGetVariableValueSwitch( &$parser, &$cache, &$magicWordId, &$ret ) {
		$wiki = PonyDocsWiki::getInstance();
		switch ( $magicWordId ) {
			case 'PonyDocs_Product':
				$product = $wiki->getCurrentProduct();
				if ( $product ) {
					$ret = $product->getLongName( TRUE );
				}
				break;
			case 'PonyDocs_Version':
				$version = $wiki->getCurrentVersion();
				if ( $version ) {
					$ret = $version->getVersionLongName();
				}
				break;
			case 'PonyDocs_Manual':
				$manual = $wiki->getCurrentManual();
				if ( $manual ) {
					$ret = $manual->getLongName();
				}
				break;
		}

		return TRUE;
	}

	static public function wfPonyDocsMagicWordwgVariableIDs( &$customVariableIds ) {
		$customVariableIds[] = 'PonyDocs_Product';
		$customVariableIds[] = 'PonyDocs_Version';
		$customVariableIds[] = 'PonyDocs_Manual';
		$customVariableIds[] = 'PonyDocs_Topic';

		return TRUE;
	}
}