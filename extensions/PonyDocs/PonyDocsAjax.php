<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );
	
/**
 * This contains any Ajax functionality supported in the PonyDocs extension.
 * Each function should be added to the $wgAjaxExportList so it can be called from the sajax_do_call() JS function.
 * You should always call this with a callback function, using the callback to convert the response text to a String object,
 * otherwise it causes serious problems in IE and Firefox.
 * The syntax: sajax_do_call( 'functionName', args, callback );
 *
 * Requires $wgUseAjax to be set to true.
 */

$wgExtensionFunctions[] = 'efPonyDocsAjaxInit';

$wgAjaxExportList[] = 'efPonyDocsAjaxChangeProduct';
$wgAjaxExportList[] = 'efPonyDocsAjaxChangeVersion';
$wgAjaxExportList[] = 'efPonyDocsAjaxGetVersions';
$wgAjaxExportList[] = 'efPonyDocsAjaxRemoveVersions';

/**
 * Basic init function to ensure Ajax is enabled.
 */
function efPonyDocsAjaxInit()
{
	global $wgUseAjax;
	if ( !$wgUseAjax ) {
		wfDebug( 'efAjaxRemoveVersions: $wgUseAjax must be enabled for Ajax functionality.' );
	}
}

/**
 * This is called when a product change occurs in the select box.  It should update the product
 * only;  to update the page the Ajax function in JS should then refresh by using history.go(0)
 * or something along those lines, otherwise the content may reflect the old product selection.
 * 
 * @param string $product New product tag to set as current.  Should be some checking.
 * @param string $title The current title that the person resides in, if any.
 * @param boolean $force Force the change, no matter if a doc is in the same product or not
 * @return AjaxResponse
 */
function efPonyDocsAjaxChangeProduct( $product, $title, $force = false )
{
	global $wgArticlePath;

	$dbr = wfGetDB( DB_SLAVE );

	PonyDocsProduct::SetSelectedProduct( $product );
	$response = new AjaxResponse( );

	if ($force) {
		// This is coming from the search page.  let's not do any title look up,
		// and instead just pass back the same url.
		$leadingSlash = "/";
		if (substr($title, 0,1) == "/") {
			$leadingSlash = "";
		}
		$response->addText($leadingSlash . $title); // Need to make the url non-relative
		return $response;
	}

	$defaultTitle = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME;
	if( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*):(.*)/i', $title, $match )) {
		if (PONYDOCS_DEBUG) {
			error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule 1");
		}
		$response->addText( str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $product, $wgArticlePath ));
	} elseif ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '\/(.*)\/(.*)\/(.*)\/(.*)/i', $title, $match )) {
		/**
		 * Just swap out the source product tag ($match[1]) with the selected product in the output URL.
		 */
		//$response->addText( str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $product . '/' . $match[3] . '/' . $match[4], $wgArticlePath ));
		// just redirect to that product's main page, we can't carry over version and manual across products
		if (PONYDOCS_DEBUG) {	
			error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule 2");
		}
		$response->addText( str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $product, $wgArticlePath ));
	} elseif ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(Manuals|Versions)/i', $title, $match )) {
		if (PONYDOCS_DEBUG) {
			error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule 3");
		}
		$response->addText( str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $product . ':' . $match[2],
			$wgArticlePath ));
	} else {
		if (PONYDOCS_DEBUG) {
			error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule 4");
		}
		$response->addText( str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $product, $wgArticlePath ));
	}

	if (PONYDOCS_DEBUG) {
		error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect result " . print_r($response, true));
	}
	return $response;
}

/**
 * This is called when a version change occurs in the select box.  It should update the version
 * only;  to update the page the Ajax function in JS should then refresh by using history.go(0)
 * or something along those lines, otherwise the content may reflect the old version selection.
 * 
 * @param string $version New version tag to set as current.  Should be some checking.
 * @param string $title The current title that the person resides in, if any.
 * @param boolean $force Force the change, no matter if a doc is in the same version or not
 * @return AjaxResponse
 */
function efPonyDocsAjaxChangeVersion( $product, $version, $title, $force = false )
{
	global $wgArticlePath;

	$dbr = wfGetDB( DB_SLAVE );

	PonyDocsProduct::SetSelectedProduct( $product );
	PonyDocsProductVersion::SetSelectedVersion( $product, $version );

	$response = new AjaxResponse( );

	if ($force) {
		// This is coming from the search page.  let's not do any title look up, 
		// and instead just pass back the same url.
		$leadingSlash = "/";
		if (substr($title, 0,1) == "/") { 
			$leadingSlash = "";
		}
		$response->addText($leadingSlash . $title); // Need to make the url non-relative
		return $response;
	}

	$defaultTitle = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME;

	if ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*):(.*)/i', $title, $match ) ) {
		$res = $dbr->select(
			'categorylinks',
			'cl_from',
			array( 
				"cl_to = 'V:" . $dbr->strencode( $product . ":" . $version ) . "'",
				'cl_type = "page"',
				"cl_sortkey LIKE '" . $dbr->strencode( strtoupper( $product . ':' . $match[2] . ':' . $match[3] ) ) . ":%'",
			),
			__METHOD__
		);

		if( $res->numRows( ))
		{
			$response->addText( str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $product . '/' . $version . '/' . $match[2] . '/' . $match[3], $wgArticlePath ));
			if (PONYDOCS_DEBUG) {error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule 1");}
		}
		else
		{
			// same manual/topic doesn't exist for newly selected version, redirect to default
			$response->addText( str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $product . '/' . $version, $wgArticlePath ));
			if (PONYDOCS_DEBUG) {
				error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule 2");
			}
		}
	} elseif ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(Manuals|Versions)/i', $title, $match )) {
		// this is a manuals or versions page
		$add_text = str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $product . ':' . $match[2],
			$wgArticlePath);
		/// FIXME we probably need to clear objectcache for this [product]:Manuals page, or even better, do not cache it(?)
		$response->addText( $add_text );
		if (PONYDOCS_DEBUG) {
			error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule 3");
		}
	} elseif ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '\/(.*)\/(.*)\/(.*)\/(.*)/i', $title, $match )) {
		/**
		 * Just swap out the source version tag ($match[2]) with the selected version in the output URL.
		 */
		$response->addText( str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $product . '/' 
		. $version . '/' . $match[3] . '/' . $match[4], $wgArticlePath ));
		if (PONYDOCS_DEBUG) {
			error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule 4");
		}
	} elseif ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '\/(.*)\/(.*)\/(.*)/i', $title, $match )) {
		//Redirection for WEB-10264
		$response->addText( str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $product . '/' . $version . '/' . $match[3] , $wgArticlePath ));
		if (PONYDOCS_DEBUG) {
			error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule to switch versions on a static manual");
		}
	} else {
		$add_text = str_replace( '$1', $defaultTitle . '/' . $product . '/' . $version, $wgArticlePath );
		$response->addText( $add_text );
		if (PONYDOCS_DEBUG) {error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect rule 5");}
	}
	if (PONYDOCS_DEBUG) {
		error_log("DEBUG [" . __METHOD__ . ":" . __LINE__ . "] ajax redirect result " . print_r($response, true));
	}
	return $response;
}

/**
 * Return a list of available versions for a product.
 * 
 * To invoke from JS: sajax_do_call( "efPonyDocsAjaxGetVersions", [productName], callback );
 * 
 * @param string $productName
 * @return string json-encoded array of versions
 */
function efPonyDocsAjaxGetVersions( $productName ) {
	$versions = PonyDocsProductVersion::LoadVersionsForProduct( $productName, TRUE );
	$response = array();
	foreach ( $versions as $version ) {
		$response[] = array (
			'short_name' => $version->getVersionShortName(),
			'status' => $version->getVersionStatus(),
		);
	}
	return json_encode( $response );
}

/**
 * To use this inside the HTML, you need to execute the call:
 *
 * 	sajax_do_call("efPonyDocsAjexRemoveVersions", [title,versions], callback );
 *
 * Where 'title' is the title topic to process and versions is a colon delimited list of versions
 * to strip from the supplied title, both in categorylinks table and from the article content
 * itself.
 *
 * @param string $title Title/topic name.
 * @param string $versionList Colon delimited list of versions.
 * @return AjaxResponse
 */
function efPonyDocsAjaxRemoveVersions( $title, $versionList ) {
	global $wgRequest;

	/**
	 * First open the title and strip the [[Category]] tags from the content and save.
	 */
	$versions = explode( ':', $versionList );

	$title = Title::newFromText( $title );
	$article = new Article( $title );
	$content = $article->getContent( );

	$findArray = $repArray = array( );
	foreach( $versions as $v ) {
		$findArray[] = '/\[\[\s*Category\s*:\s*' . $v . '\s*\]\]/i';
		$repArray[] = '';
	}
	$content = preg_replace( $findArray, $repArray, $content );
	$article->doEdit( $content, 'Automatic removal of duplicate version tags.', EDIT_UPDATE );

	/**
	 * Now update the categorylinks table as well -- might not be needed, doEdit() might take care
	 * of this when saving the article.
	 */
	$q = "DELETE FROM categorylinks"
		. " WHERE cl_sortkey = '" . $dbr->strencode( strtoupper( $title->getText() ) ) . "'"
		. " AND cl_to IN ('V:" . implode( "','V:", $versions ) . "')"
		. " AND cl_type = 'page'";

	$res = $dbr->query( $q, __METHOD__ );

	/**
	 * Do not output anything, but perhaps a status would be nice to return?
	 */
	$response = new AjaxResponse( );
	return $response;
}


/**
 * This is used when an author wants to CLONE a title from outside the Documentation namespace into a
 * title within it.  We must be passed the title of the original/source topic and then the destination
 * title which should be a full form PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':<manual>:<topicName>:<version>'
 * which it will then tag with the supplied version and strip out any other Category tags (since they are
 * invalid in the Documentation namespace unless a DEFINED version).
 *
 * This will return an AjaxResponse object which MAY contain an error in the case the version is not
 * valid or the topic already exists (destination).
 *
 * @FIXME:  Should validate version is defined.
 *
 * @param string $topic Title of topic to clone.
 * @param string $destTitle Title of destination topic.
 * @return AjaxResponse
 */

function efPonyDocsAjaxCloneExternalTopic( $topic, $destTitle ) {
	$response = new AjaxResponse( );
	$response->setCacheDuration( false );

	$pieces = split( ':', $destTitle );
	if (( sizeof( $pieces ) < 4 || ( strcasecmp( $pieces[0], PONYDOCS_DOCUMENTATION_NAMESPACE_NAME ) != 0 ))) {  
		$response->addText( 'Destination title is not valid.' );
		return $response;
	}

	if ( !PonyDocsManual::IsManual( $pieces[1] )) {
		$response->addText( 'Destination title references an invalid manual.' );
		return $response;
	}

	if ( !PonyDocsVersion::IsVersion( $pieces[3] )) {
		$response->addText( 'Destination title references an undefined version.' );
		return $response;
	}

	$destArticle = new Article( Title::newFromText( $destTitle ));
	if ( $destArticle->exists( )) {
		$response->addText( 'Destination title already exists.' );
		return $response;
	}

	$article = new Article( Title::newFromText( $topic ));
	if ( !$article->exists( )) {
		$response->addText( 'Source article could not be found.' );
		return $response;
	}

	$article->getContent( );

	return $response;
}