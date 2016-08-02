<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "PonyDocs MediaWiki Extension" );
}

require_once( "$IP/includes/specialpage/SpecialPage.php" );

/**
 * Needed since we subclass it;  it doesn't seem to be loaded elsewhere.
 */
require_once( $IP . '/includes/specialpage/SpecialPage.php' );

/**
 * Register our 'Special' page so it is listed and accessible.
 */
$wgSpecialPages['StaticDocImport'] = 'SpecialStaticDocImport';

/**
 * Special page to control static documentation import
 */
class SpecialStaticDocImport extends SpecialPage {
	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 */
	public function __construct() {
		SpecialPage::__construct( "StaticDocImport" );
	}

	public function getDescription() {
		return 'Static Documentation Import Tool';
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.
	 * @param string $par the URL path following the special page name
	 */
	public function execute( $par ) {
		global $wgOut, $wgUser;

		$this->setHeaders();

		$parts = explode( '/', $par );

		$productName = isset( $parts[0] ) ? $parts[0] : PonyDocsProduct::GetSelectedProduct();
		$manualName = isset( $parts[1] ) ? $parts[1] : NULL;

		// Security Check
		$authProductGroup = PonyDocsExtension::getDerivedGroup( PonyDocsExtension::ACCESS_GROUP_PRODUCT, $productName );
		$groups = $wgUser->getGroups();
		if ( !in_array( $authProductGroup, $groups ) ) {
			$wgOut->addHTML( '<p>Sorry, but you do not have permission to access this Special page.</p>' );
			return;
		}

		$product = PonyDocsProduct::GetProductByShortName( $productName );
		$productLongName = $product->getLongName();
		PonyDocsProductVersion::LoadVersionsForProduct( $productName );

		if ( !is_null( $manualName ) ) {
			$manual = PonyDocsProductManual::GetManualByShortName( $productName, $manualName );
			if ( is_null( $manual ) ) {
				$wgOut->addHTML( '<p>Sorry, but the manual is not available for name - ' . $manualName .'.</p>' );
				return;
			}
			$manualLongName = $manual->getLongName();
		} else {
			$manual = NULL;
		}

		$wgOut->setPagetitle( 'Static Documentation Import Tool' );

		$h2 = "Static Documentation Import for $productLongName";
		if ( !is_null( $manualName ) ) {
			$h2 .= ", $manualLongName";
		}
		$wgOut->addHTML("<h2>$h2</h2>");

		if ( $this->validateProductAndManualAreStatic( $product, $manual ) ) {
			if ( isset($_POST['action']) ) {
				$this->processImportForm( $_POST['action'], $product, $manual );
			} else {
				$this->showImportForm( $product, $manual );
			}
		}

		$this->showExistingVersions( $product, $manual );
		$this->showHelpfulLinks( $productName );
	}

	/**
	 * Validate that the product, and manual if any, are static
	 *
	 * @global OutputPage $wgOut
	 * @param PonyDocsProduct $product
	 * @param mixed $manual PonyDocsManual or NULL
	 * @return boolean
	 */
	private function validateProductAndManualAreStatic( $product, $manual ) {
		global $wgOut;

		$valid = TRUE;
		if ( is_null( $manual ) && !$product->isStatic() ) {
			$wgOut->addHTML( "<h3>{$product->getLongName()} is not defined as a static product.</h3>" );
			$wgOut->addHTML( "<p>In order to define it as a product, please visit the product management page from the link"
				. " below and follow the instructions.</p>" );
			$valid = FALSE;
		}
		elseif ( !is_null( $manual ) && !$manual->isStatic() ) {
			$wgOut->addHTML( "<h3>{$manual->getLongName()} is not defined as a static manual.</h3>" );
			$wgOut->addHTML( "<p>In order to define it as a static manual, please visit the manuals management page from the link"
				. " below and follow the instructions.</p>" );
			$valid = FALSE;
		}

		return $valid;
	}

	/**
	 * Show the form that allows importing a new static product or manual zip file
	 *
	 * @global OutputPage $wgOut
	 * @param PonyDocsProduct $product
	 * @param mixed $manual PonyDocsManual or NULL
	 */
	private function showImportForm( $product, $manual ) {
		global $wgOut;
		$productName = $product->getShortName();
		$productLongName = $product->getLongName();
		$versions = PonyDocsProductVersion::LoadVersionsForProduct($productName);
		if ( !is_null( $manual ) ) {
			$manualName = $manual->getShortName();
		}

		$wgOut->addHTML( '<h3>Import to Version</h3>' );

		// Only display form if at least one version is defined
		if ( count( $versions ) > 0 ) {
			$action = "/Special:StaticDocImport/$productName";
			if ( !is_null( $manual ) ) {
				$action .= "/$manualName";
			}
			$wgOut->addHTML('<form action="' . $action . '" method="post" enctype="multipart/form-data">'
				. "\n" . '<label for="archivefile">File to upload:</label>'
				. '<input id="archivefile" name="archivefile" type="file" />'
				. '<input type="hidden" name="product" value="' . $productName . '"/>' . "\n" );
			if ( !is_null( $manual ) ) {
				$wgOut->addHTML( '<input type="hidden" name="manual" value="' . $manualName . '"/>' . "\n" );
			}
			$wgOut->addHTML( '<select name="version">' );
			foreach ( $versions as $version ) {
				$wgOut->addHTML( '<option value="' . $version->getVersionShortName() . '">' . $version->getVersionShortName()
					. "</option>\n" );
			}
			$wgOut->addHTML( "</select>\n" );
			$wgOut->addHTML('<input type="hidden" name="action" value="add"/>' . "\n"
				. '<input type="submit" name="submit" value="Submit"/>' . "\n" . '</form>' . "\n" );
			$wgOut->addHTML( "<p>Notes on upload file:</p>\n"
				. "<ul>\n"
				. "<li>should be zip format</li>\n"
				. "<li>verify it does not contain unneeded files: e.g. Mac OS resource, or Windows thumbs, etc.</li>\n"
				. "<li>requires a index.html file in the root directory</li>\n"
				. "<li>links to documents within content must be relative for them to work</li>\n"
				. "<li>may or may not contain sub directories</li>\n"
				. "<li>may not be bigger than " . ini_get('upload_max_filesize') . "</li>\n"
				. "</ul>\n" );
		} else {
			$wgOut->addHTML( "There are currently no versions defined for $productLongName."
				. "In order to upload static documentation please define at least one version and one manual from the"
				. "links below.\n" );
		}
	}

	/**
	 *
	 * @global OutputPage $wgOut
	 * @param string $action
	 * @param PonyDocsProduct $product
	 * @param mixed $manual PonyDocsManual or NULL
	 */
	private function processImportForm( $action, $product, $manual ) {
		global $wgOut, $wgUser;

		$importer = new PonyDocsStaticDocImporter( PONYDOCS_STATIC_DIR );

		if ( isset( $_POST['version'] ) && isset( $_POST['product'] )
			&& ( is_null( $manual ) || isset( $_POST['manual'] ) ) ) {
			switch ($action) {
				case "add":
					if ( PonyDocsProductVersion::IsVersion( $_POST['product'], $_POST['version'] ) ) {
						$wgOut->addHTML( '<h3>Results of Import</h3>' );
						// Okay, let's make sure we have file provided
						if ( !isset( $_FILES['archivefile'] ) || $_FILES['archivefile']['error'] != 0 )  {
							$wgOut->addHTML(
								'There was a problem using your uploaded file. Make sure you uploaded a file and try again.');
						} else {
							try {
								if ( is_null( $manual ) ) {
									$importer->importFile($_FILES['archivefile']['tmp_name'], $_POST['product'],
										$_POST['version'] );
									$wgOut->addHTML(
										"Success: imported archive for {$_POST['product']} version {$_POST['version']}" );
								} else {
									$importer->importFile($_FILES['archivefile']['tmp_name'], $_POST['product'],
										$_POST['version'], $_POST['manual'] );
									$wgOut->addHTML(
										"Success: imported archive for {$_POST['product']} version {$_POST['version']}"
										. " manual {$_POST['manual']}" );
								}
							} catch (Exception $e) {
								$wgOut->addHTML( 'Error: ' . $e->getMessage() );
								error_log('WARNING [ponydocs] [staticdocs] [' . __METHOD__ . '] action="add" status="error"'
									. ' message="' . addcslashes($e->getMessage(), '"') . '"');
							}
						}
					}
					break;

				case "remove":
					//Loading product versions for WEB-10732
					PonyDocsProductVersion::LoadVersionsForProduct($_POST['product']);
					if ( PonyDocsProductVersion::IsVersion( $_POST['product'], $_POST['version'] ) ) {
						$wgOut->addHTML( '<h3>Results of Deletion</h3>' );
						try {
							if ( is_null( $manual ) ) {
								$importer->removeVersion( $_POST['product'], $_POST['version'] );
								$wgOut->addHTML( "Successfully deleted {$_POST['product']} version {$_POST['version']}" );
							} else {
								$importer->removeVersion( $_POST['product'], $_POST['version'], $_POST['manual'] );
								$wgOut->addHTML( "Successfully deleted {$_POST['product']} version {$_POST['version']}"
									. " manual {$_POST['manual']}" );
							}
						} catch (Exception $e) {
							$wgOut->addHTML('Error: ' . $e->getMessage() );
							error_log('WARNING [ponydocs] [staticdocs] [' . __METHOD__ . '] action="remove" status="error"'
								. ' message="' . addcslashes($e->getMessage(), '"') . '"');
						}
					} else {
						$wgOut->addHTML( "Error: Version {$_POST['version']} does not exist, or is not accessible" );
						error_log( 'WARNING [ponydocs] [staticdocs] [' . __METHOD__ . '] action="remove" status="error"'
							. ' message="version ' . $_POST['version'] . ' does not exist, or is not accessible"'
							. ' username="' . $wgUser->getName() . '"'
							. ' ip="' . IP::sanitizeIP( wfGetIP() ) . '"');
					}
					break;
			}
			$this->clearProductCache($_POST['product'], $_POST['version']);
		}
	}

	/**
	 * Clear NAVDATA cache by product and version
	 * @param string $product
	 * @param string $version
	 */
	private function clearProductCache( $productName, $versionName ) {
		//verify product has the version
		$versionObj = PonyDocsProductVersion::GetVersionByName( $productName, $versionName );
		if ( $versionObj != FALSE ) {
			PonyDocsProductVersion::clearNAVCache( $versionObj );
		}
	}

	/**
	 * Show existing versions and remove form
	 *
	 * @global OutputPage $wgOut
	 * @param PonyDocsProduct $product
	 * @param mixed $manual PonyDocsManual or NULL
	 */
	private function showExistingVersions( $product, $manual ) {
		global $wgOut;

		$productName = $product->getShortName();
		if ( !is_null( $manual ) ) {
			$manualName = $manual->getShortName();
		}

		// Display existing versions
		$wgOut->addHTML( '<h3>Existing Content</h3>' );
		if ( is_null( $manual ) ) {
			$existingVersions = $product->getStaticVersionNames();
		} else {
			$existingVersions = $manual->getStaticVersionNames();
		}
		if ( count($existingVersions) > 0 ) {
			$wgOut->addHTML(
				'<script type="text/javascript">function verify_delete() {return confirm("Are you sure?");}</script>' );
			$wgOut->addHTML( '<table>' );
			$wgOut->addHTML( '<tr><th>Version</th><th></th></tr>' );
			foreach ( $existingVersions as $versionName ) {
				$wgOut->addHTML( "<tr>\n"
					. "<td>$versionName</td>\n"
					. "<td>\n"
					. '<form method="POST" onsubmit="return verify_delete()">' . "\n"
					. '<input type="submit" name="action" value="remove"/>' . "\n"
					. '<input type="hidden" name="product" value="' . $productName. '"/>' . "\n"
					. '<input type="hidden" name="version" value="' . $versionName . '"/>' . "\n" );
				if ( !is_null( $manual ) ) {
					$wgOut->addHTML( '<input type="hidden" name="manual" value="' . $manualName. '"/>' . "\n" );
				}
				$wgOut->addHTML( "</form>\n</td>\n</tr>\n" );
			}
			$wgOut->addHTML( '</tr></table>' );
		} else {
			$wgOut->addHTML( '<p>No existing version defined.</p>' );
		}
	}

	/**
	 * List of links for the bottom of the page
	 * @global string $wgArticlePath
	 * @global OutputPage $wgOut
	 * @param PonyDocsProduct $productName
	 */
	private function showHelpfulLinks( $productName ) {
		global $wgArticlePath, $wgOut;

		$wgOut->addHTML( '<h2>Other Useful Management Pages</h2>'
			. '<a href="'
				. str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productName .
					PONYDOCS_PRODUCTVERSION_SUFFIX, $wgArticlePath )
				. '">Version Management</a> - Define and update available ' . $productName . ' versions.<br />'
			. '<a href="'
				. str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $productName .
					PONYDOCS_PRODUCTMANUAL_SUFFIX, $wgArticlePath )
				. '">Manuals Management</a> - Define the list of available manuals for the Documentation namespace.<br />'
			. '<a href="'
				. str_replace( '$1', PONYDOCS_DOCUMENTATION_PRODUCTS_TITLE, $wgArticlePath )
				. '">Products Management</a> - Define the list of available products for the Documentation namespace.'
				. '<br/><br/>' );
	}
}
