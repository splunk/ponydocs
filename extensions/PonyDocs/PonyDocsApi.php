<?php

/**
 * @file Shared PonyDocs API methods live here
 */

// Ajax Handlers
$wgAjaxExportList[] = 'PonyDocsAPI::ajaxFetchJobID';
$wgAjaxExportList[] = 'PonyDocsAPI::ajaxFetchJobProgress';
$wgAjaxExportList[] = 'PonyDocsAPI::ajaxFetchManuals';

class PonyDocsApi {
	
	/**
	 * This is a static class
	 */
	private function __construct() {}
	
	/**
	 * AJAX method to fetch/initialize uniqid to identify this Job. Used to build progress report.
	 *
	 * @param string $code A unique code for this Job (Usually the name of the calling Special Page)
	 * @returns string The unique id for this job.
	 */
	public static function ajaxFetchJobId( $code ) {
		$uniqid = uniqid( $id, true );
		// Create the file.
		$path = PonyDocsExtension::getTempDir() . $uniqid;
		$fp = fopen( $path, 'w+' );
		fputs( $fp, 'Determining Progress...' );
		fclose( $fp );
		return $uniqid;
	}

	/**
	 * AJAX method to fetch job progress. Used to update progress report.
	 * 
	 * @param string $jobId
	 * @return string 
	 */
	public static function ajaxFetchJobProgress( $jobId ) {
		$path = PonyDocsExtension::getTempDir() . $jobId;
		$progress = file_get_contents( $path );
		if ( $progress === false ) {
			$progress = 'Unable to fetch Job Progress.';
		}
		return $progress;
	}
	
	/**
	 * AJAX method to fetch manuals for a specified product and, optionally version
	 *
	 * @param string $productName
	 * @param string $versionName (if empty, fetch all manuals for all versions)
 	 * @returns string JSON representation of the manuals
	 */
	public static function ajaxFetchManuals($productName, $versionName = NULL) {
		if ( $versionName ) {
			PonyDocsProductVersion::LoadVersionsForProduct( $productName );
			PonyDocsProductVersion::SetSelectedVersion( $productName, $versionName );
			$manuals = PonyDocsProductManual::GetManuals( $productName );
		} else {
			$manuals = PonyDocsProductManual::GetDefinedManuals( $productName );
		}
		$result = array();
		foreach( $manuals as $manual ) {
			$result[] = array( "shortname" => $manual->getShortName(), "longname" => $manual->getLongName() );
		}
		$result = json_encode( $result );
		return $result;
	}
}