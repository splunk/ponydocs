<?php

/**
 * Parser functions live here
 */
class PonyDocsParsers {

	/**
	 * This section handles the parser function {{#manual:<shortName>|<longName>}} which defines a manual.
	 */
	static public function efManualParserFunction_Setup() {
		global $wgParser;
		$wgParser->setFunctionHook( 'manual', 'PonyDocsParsers::efManualParserFunction_Render' );
	}

	static public function efManualParserFunction_Magic( &$magicWords, $langCode ) {
		$magicWords['manual'] = array( 0, 'manual' );
		return TRUE;
	}

	/**
	 * This is called when {{#manual:short|long}} is found in an article content. It should produce an output
	 * set of HTML which provides the name (long) as a link to the most recent (based on version tags) TOC
	 * management page for that manual.
	 *
	 * @param Parser $parser
	 * @param string $shortName Short name of the Manual used in links.
	 * @param string $longName Long/display name of Manual.
	 * @param string $categories The categories for the Manual, in a comma-separated list
	 * @return array
	 */
	static public function efManualParserFunction_Render( &$parser, $shortName = '', $longName = '', $categories = '' ) {
		global $wgArticlePath;

		$valid = TRUE;
		if ( !preg_match( PONYDOCS_PRODUCTMANUAL_REGEX, $shortName ) || !strlen( $shortName ) || !strlen( $longName ) ) {
			return $parser->insertStripItem( '', $parser->mStripState );
		}

		$manualName = preg_replace( '/([^' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS . ']+)/', '', $shortName );
		// TODO: It's silly to do this twice (the other is in LoadManualsForProduct().
		//       We should get the manual object from PonyDocsProductManual
		$static = FALSE;
		if ( strpos( $shortName, PONYDOCS_PRODUCT_STATIC_PREFIX ) === 0 ) {
			$static = TRUE;
			$manualName = substr( $manualName, strlen( PONYDOCS_PRODUCT_STATIC_PREFIX ) );
		}
		$productName = PonyDocsProduct::GetSelectedProduct();
		$version = PonyDocsProductVersion::GetSelectedVersion( $productName );

		// Don't cache Documentation:[product]:Manuals pages because when we switch selected version the content will come from cache
		$parser->disableCache();

		// If static, link to Special:StaticDocImport
		if ( $static ) {
			$output = "<p><a href=\"" . str_replace( '$1', "Special:StaticDocImport/$productName/$manualName" , $wgArticlePath )
				. "\" style=\"font-size: 1.3em;\">$longName</a></p>\n"
				. "<span style=\"padding-left: 20px;\">Click manual to manage static documentation.</span>\n";
		// Otherwise, link to TOC for current Version OR add a link to create a new TOC if none exists
		} else {
			// TODO: We should call PonyDocsTOC.php or maybe PonyDocsProductManual to see if there's a TOC in this manual
			//       or maybe actually get the manual object and query it
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				array('categorylinks', 'page'),
				'page_title',
				array(
					'cl_from = page_id',
					'page_namespace = "' . NS_PONYDOCS . '"',
					"cl_to = 'V:$productName:$version'",
					'cl_type = "page"',
					"cl_sortkey LIKE '%:" . $dbr->strencode( strtoupper( $manualName ) ) . "TOC%'"
				),
				__METHOD__
			);

			if ( !$res->numRows() )	{
				/**
				 * Link to create new TOC page -- should link to current version TOC and then add message to explain.
				 */
				$output = '<p><a href="'
					. str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productName . ':' . $manualName . 'TOC'
						. $version, $wgArticlePath ) 
					. '" style="font-size: 1.3em;">' . "$longName</a></p>\n <span style=\"padding-left: 20px;\">"
					. "Click manual to create TOC for current version ($version).</span>\n";
			} else {
				$row = $dbr->fetchObject( $res );
				$output = '<p><a href="'
					. str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ":{$row->page_title}", $wgArticlePath )
					. '" style="font-size: 1.3em;">' . $longName . "</a></p>\n";
			}
		}

		if ( $categories != '' ) {
			$output .= "<br>Categories: $categories";
		}

		return $parser->insertStripItem( $output, $parser->mStripState );
	}

	/**
	 * This section handles the parser function {{#version:<name>|<status>}} which defines a version.
	 */
	static public function efVersionParserFunction_Setup() {
		global $wgParser;
		$wgParser->setFunctionHook( 'version', 'PonyDocsParsers::efVersionParserFunction_Render' );
		$wgParser->setFunctionHook( 'versiongroup', 'PonyDocsParsers::efVersionGroupParserFunction_Render' );
	}

	static public function efVersionParserFunction_Magic( &$magicWords, $langCode ) {
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
	 * @param string $param3 The version long name.
	 * @return array
	 */
	static public function efVersionParserFunction_Render( &$parser, $param1 = '', $param2 = '', $param3 = '' ) {
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

		$output = 'Version ' . $param1 . ' (' . $param3 . ') ' . $param2;

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
	static public function efVersionGroupParserFunction_Render( &$parser, $param1 = '', $param2 = '' ) {
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
	static public function efProductParserFunction_Setup() {
		global $wgParser;
		$wgParser->setFunctionHook( 'product', 'PonyDocsParsers::efProductParserFunction_Render' );
	}

	static public function efProductParserFunction_Magic( &$magicWords, $langCode ) {
		$magicWords['product'] = array( 0, 'product' );
		return TRUE;
	}

	/**
	 * The product parser function is of the form {{#product:name|long_name|description|parent}}
	 * Which defines a product and its state.
	 * When output it currently does nothing but should perhaps be a list to Category:<product>.
	 *
	 * @param Parser $parser
	 * @param string $shortName The Product name itself.
	 * @param string $longName The long Product name.
	 * @param string $description The Product description
	 * @param string $parent The short name of the parent Product
	 * @param string $categories The categories for the Product, in a comma-separated list
	 *
	 * @return array
	 */
	static public function efProductParserFunction_Render(
		&$parser, $shortName = '', $longName = '', $description = '', $parent = '', $categories = '' ) {
		global $wgArticlePath, $wgScriptPath, $wgUser;

		$static = FALSE;
		if ( strpos( $shortName, PONYDOCS_PRODUCT_STATIC_PREFIX ) === 0 ) {
			$static = TRUE;
			$shortName = substr( $shortName, strlen(PONYDOCS_PRODUCT_STATIC_PREFIX ) );
		}

		$output = "$shortName (" . strip_tags($longName, '<del><em><ins><strong><sub><sup>') . ')';

		// Invalid $shortName
		if ( !preg_match(PONYDOCS_PRODUCT_REGEX, $shortName ) ) {
			$output .= ' - Invalid Product Name, Please Fix<br>';
		}

		if ( $description != '' ) {
			$output .= "$description<br>";
		}

		if ( $parent != '' ) {
			$output .= "Parent: $parent<br>";
		}

		if ( $categories != '') {
			$output .= "Categories: $categories<br>";
		}

		if ( $static ) {
			$output .= "<a href=\"" . str_replace( '$1', "Special:StaticDocImport/$shortName" , $wgArticlePath )
				. "\">Click to manage static documentation</a><br>\n";
		// Add link to manage manuals
		} else {
			$output .= "<a href=\"" . str_replace( '$1', "Documentation:$shortName:Manuals" , $wgArticlePath )
				. "\">Click to manage $shortName manuals</a><br>\n";
		}

		// Add link to manage versions
		$output .= "<a href=\"" . str_replace( '$1', "Documentation:$shortName:Versions" , $wgArticlePath )
			. "\">Click to manage $shortName versions</a><br>\n";

		$output .= "<br>\n";

		return $parser->insertStripItem( $output, $parser->mStripState );
	}

	/**
	 * Our topic parser functions used in TOC management to define a topic to be listed within a section.
	 * This is simply the form {{#topic:Name of Topic}}
	 */

	static public function efTopicParserFunction_Setup() {
		global $wgParser;
		$wgParser->setFunctionHook( 'topic', 'PonyDocsParsers::efTopicParserFunction_Render' );
	}

	static public function efManualDescriptionParserFunction_Setup() {
		global $wgParser;
		$wgParser->setFunctionHook( 'manualDescription', 'PonyDocsParsers::efManualDescriptionParserFunction_Render' );
	}

	static public function efTopicParserFunction_Magic( &$magicWords, $langCode ) {
		$magicWords['topic'] = array( 0, 'topic' );
		return TRUE;
	}

	static public function efManualDescriptionParserFunction_Magic( &$magicWords, $langCode ) {
		$magicWords['manualDescription'] = array( 0, 'manualDescription' );
		return TRUE;
	}

	static public function efGetTitleFromMarkup( $markup = '' ) {
		global $wgArticlePath, $wgTitle, $action;

		/**
		 * We ignore this parser function if not in a TOC management page.
		 */
		if ( !preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*)TOC(.*)/i', $wgTitle->__toString(),
			$matches ) ) {
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
		$wikiPath = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productShortName . ':' . $manualShortName . ':' . $wikiTopic;

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
		foreach ( $manVersionList as $pV ) {
			$versionIn[] = $pV->getVersionShortName();
		}

		$res = $dbr->select(
			array('categorylinks', 'page'),
			'page_title',
			array(
				'cl_from = page_id',
				'page_namespace = "' . NS_PONYDOCS . '"',
				"cl_to IN ( 'V:$productShortName:" . implode( "','V:$productShortName:", $versionIn ) . "')",
				'cl_type = "page"',
				"cl_sortkey LIKE '"	. $dbr->strencode( strtoupper( $manualShortName . ':' . $wikiTopic ) ) . ":%'"
			),
			__METHOD__
		);

		$topicName = '';
		if ( !$res->numRows() ) {
			/**
			 * No match -- so this is a "new" topic. Set name.
			 */
			$topicName = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productShortName . ':' . $manualShortName . ':' . $wikiTopic . ':'
				. $earliestVersion->getVersionShortName();
		} else {
			$row = $dbr->fetchObject( $res );
			$topicName = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ":{$row->page_title}";
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
	static public function efTopicParserFunction_Render( &$parser, $param1 = '' ) {
		global $wgArticlePath, $wgTitle, $action;

		if ( PonyDocsExtension::isSpeedProcessingEnabled() ) {
			return TRUE;
		}

		/**
		 * We ignore this parser function if not in a TOC management page.
		 */
		if ( !preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*)TOC(.*)/i', $wgTitle->__toString(), $matches ) ) {
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
		$wikiPath = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productShortName . ':' . $manualShortName . ':' . $wikiTopic;

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
			$versionIn[] = $productShortName . ':' . $pV->getVersionShortName();
		}

		$res = $dbr->select(
			array('categorylinks', 'page'),
			'page_title',
			array(
				'cl_from = page_id',
				'page_namespace = "' . NS_PONYDOCS . '"',
				"cl_to IN ('V:" . implode( "','V:", $versionIn ) . "')",
				'cl_type = "page"',
				"cl_sortkey LIKE '" . $dbr->strencode( strtoupper( $productShortName . ':' . $manualShortName . ':' . $wikiTopic ) )
					. ":%'",
			),
			__METHOD__
		);

		$topicName = '';
		if ( !$res->numRows() ) {
			/**
			 * No match -- so this is a "new" topic. Set name.
			 */
			$topicName = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productShortName . ':' . $manualShortName . ':' .
				$wikiTopic . ':' . $earliestVersion->getVersionShortName();
		} else {
			$row = $dbr->fetchObject( $res );
			$topicName = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ":{$row->page_title}";
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
	static public function efManualDescriptionParserFunction_Render( &$parser, $param1 = '' ) {
		global $wgTitle;

		if ( PonyDocsExtension::isSpeedProcessingEnabled() ) {
			return TRUE;
		}

		/**
		 * We ignore this parser function if not in a TOC management page.
		 */
		if ( !preg_match(
			'/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':([' . PONYDOCS_PRODUCT_LEGALCHARS.']*):([' . PONYDOCS_PRODUCTMANUAL_LEGALCHARS
				. ']*)TOC([' . PONYDOCS_PRODUCTVERSION_LEGALCHARS.']*)/i',
			$wgTitle->__toString(),
			$matches) )	{
			return FALSE;
		}

		return '<h3>Manual Description: </h3><h4>' . $param1 . '</h4>'; // Return formated output
	}	
}