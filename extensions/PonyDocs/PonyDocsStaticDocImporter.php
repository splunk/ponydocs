<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "PonyDocs MediaWiki Extension" );
}

/**
 * Provides backend functionality for static documentation import
 * @see SpecialStaticDocImport
 */

class PonyDocsStaticDocImporter {

	/** @var string Directory where static documentation is kept */
	private $baseDir;

	/**
	 * Constructor instantiates with static doc directory location
	 * @param string $baseDir local path to base static documentation
	 */
	public function __construct( $baseDir ) {
		$this->baseDir = $baseDir;
	}

	/**
	 * Imports given .zip file into static directory location
	 * @param string $filename full path to file to extract
	 * @param string $product PonyDocs product short name
	 * @param string $version PonyDocs version name
	 * @param string $manulaName PonyDocs manual name
	 * @throw RuntimeException if there is a problem with the file or the path
	 */
	public function importFile( $filename, $product, $version, $manualName = NULL ) {
		// build path to create
		$directory = $this->baseDir . DIRECTORY_SEPARATOR . $product . DIRECTORY_SEPARATOR . $version;
		if ( $manualName ) {
			$directory .= DIRECTORY_SEPARATOR . $manualName;
		}
		// create directory
		if ( !mkdir( $directory, 0755, TRUE ) ) {
			throw new RuntimeException( 'There was a problem creating the directory.' );
		}
		// verify path resides inside the expected base directory
		$realdir = realpath( $directory );
		if ( $realdir !== FALSE && !( strpos( $realdir, $this->baseDir . DIRECTORY_SEPARATOR) === 0 ) ) {
			throw new InvalidArgumentException( 'There was a problem deleting directory. The directory ' . $directory
				. ' is not valid.' );
		}
		// extract archive to the created directory
		exec( "unzip " . escapeshellarg( $filename ) . " -d " . escapeshellarg( $directory ), $output, $returnval );
		if ( $returnval != 0 ) {
			$errorText = "There was a problem extracting your archive (Code: $returnval)";
			if ( $returnval == 2 ) {
				$errorText .= ' The file you provided was not a valid zip archive.';
			}
			throw new RuntimeException( $errorText );
		}
	}

	/**
	 * Removes static documentation for given product and version
	 * @param string $product Ponydocs short product name
	 * @param string $version Ponydocs version name
	 * @param string $manulaName PonyDocs manual name
	 * @throw RuntimeException if deletion fails
	 * @throw InvalidArgumentException when product and version path does not exist
	 */
	public function removeVersion( $product, $version, $manualName = NULL )
	{
		// build directory to delete
		$directory = $this->baseDir . DIRECTORY_SEPARATOR . $product . DIRECTORY_SEPARATOR . $version;
		if ( $manualName ) {
			$directory .= DIRECTORY_SEPARATOR . $manualName;
		}
		// verify path resides inside the expected base directory
		$realdir = realpath( $directory );
		if ( $realdir !== FALSE && !( strpos( $realdir, $this->baseDir . DIRECTORY_SEPARATOR) === 0 ) ) {
			throw new InvalidArgumentException( 'There was a problem deleting directory. The directory ' . $directory
				. ' is not valid.' );
		}
		// verify the path is a directory
		if ( !is_dir( $directory ) ) {
			throw new InvalidArgumentException( 'There was a problem deleting directory. The directory ' . $directory
				. ' does not exist.' );
		}
		// execute delete
		exec( "rm -rf " . escapeshellarg( $directory ), $output, $returnval );
		if ( $returnval != 0 ) {
			$errorText = "There was a problem deleting the directory $directory (Code: $returnval)";
			throw new RuntimeException( $errorText );
		}
	}
}