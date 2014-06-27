<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

$wgSpecialPages['SpecialDocumentLinks'] = 'SpecialDocumentLinks';

/**
 * This page states the title is not available in the latest documentation 
 * version available to the user and gives the user a chance to view the topic 
 * in a previous version where it is available.
 */

class SpecialDocumentLinks extends SpecialPage {
	private $categoryName;
	private $skin;
	private $titles;

	/**
	 * Call the parent with our name.
	 */
	public function __construct() {
		SpecialPage::__construct("SpecialDocumentLinks");
	}

	/**
	 * Return our description.  Used in Special::Specialpages output.
	 */
	public function getDescription() {
		return "View the inbound links from other Documentation topics that links to a specific Documentation topic.";
	}
	
	/**
	 * Return the colon-separated link with actual version (not base version) for the link to SpecialDocumentLinks
	 */
	
	public static function getDocumentLinksArticle() {
		
		global $wgTitle;
		
		$partialUrl = '';
		$currentVersion = PonyDocsProductVersion::GetSelectedVersion(PonyDocsProduct::GetSelectedProduct(), false);
		$partialUrl = htmlspecialchars($wgTitle->getPartialURL());
		$partialUrlParts = explode(':', $partialUrl);
		$version = $partialUrlParts[3];
		if (isset($version) && isset($currentVersion)) {
			$partialUrl = str_replace($version, $currentVersion, $partialUrl);
		}
		
		return $partialUrl;
		
	}

	/**
	 * This is called upon loading the special page.  It should write output to 
	 * the page with $wgOut
	 */
	public function execute() {
		global $wgOut, $wgArticlePath, $wgScriptPath, $wgUser;
		global $wgRequest;
		global $wgDBprefix;

		$currentProduct = '';
		$currentVersion = '';
		$collapseAll = FALSE;
		
		$dbr = wfGetDB(DB_SLAVE);

		// Set headers and title of the page, get value of "t" from GET/POST
		$this->setHeaders();
		$title = $wgRequest->getVal('t');
		if (empty($title)) {
			$wgOut->setPagetitle("Documentation Linkage" );
			$wgOut->addHTML('No topic specified.');
			return;
		}
		$wgOut->setPagetitle("Documentation Linkage For " . $title);

		// Parse "t" (the title we're looking for inbound links to)
		// Find titles for all inherited versions, etc.
		$titlePieces = explode(':', $title);
		$plainTitle = $title;
		// Create a new Title from text, such as what one would find in a link. Decodes any HTML entities in the text.
 		$title = Title::newFromText($title); 

		$toUrls = array();
		// Do PonyDocs-specific stuff (loop through all inherited versions)
		if ($titlePieces[0] == PONYDOCS_DOCUMENTATION_NAMESPACE_NAME) {

			// Get all the versions in the product
			$versions = PonyDocsProductVersion::LoadVersionsForProduct($titlePieces[1], true);
			if (empty($versions)) {
				error_log('WARNING [PonyDocs] [' . __CLASS__ . '] Unable to find product versions for this topic: ' . $title);
			}

			$currentProduct = $titlePieces[1];
			$currentVersion = $titlePieces[4];

			// Get the latest released version of this product
			$latestVersionObj = PonyDocsProductVersion::GetLatestReleasedVersion($titlePieces[1]);
			if (is_object($latestVersionObj)) {
				$latestVersion = $latestVersionObj->getVersionName();
			} else {
				error_log('WARNING [PonyDocs] [' . __CLASS__ . '] Unable to find latest released version of ' . $titlePieces[1]);
			}

			// Generate a title without the version so we can dynamically generate a list of titles with all inherited versions
			$titleNoVersion = $titlePieces[0] . ":" . $titlePieces[1] . ":" . $titlePieces[2] . ":" . $titlePieces[3];

			// Search the database for matching to_links for each inherited version
			if (is_array($versions)) {
				foreach ($versions as $ver) {

					// Add this URL to array of URLs to search db for
					$toUrls[] = PonyDocsExtension::translateTopicTitleForDocLinks($titleNoVersion, NULL, $ver);

					// Compare this version with latest version. If they're the same, add the URL with "latest" too.
					$thisVersion = $ver->getVersionName();
					if ($thisVersion == $latestVersion) {
						$titleLatestVersion = 
							$titlePieces[0] . ':' . $titlePieces[1] . ':' . $titlePieces[2] . ':' . $titlePieces[3] . ':latest';
						$toUrls[] = PonyDocsExtension::translateTopicTitleForDocLinks($titleLatestVersion);
					}
				}
			} else {
				error_log('WARNING [PonyDocs] [' . __CLASS__ . '] Unable to find versions for ' . $title);
			}

		} else { // Do generic mediawiki stuff for non-PonyDocs namespaces
			$collapseAll = TRUE;
			$toUrls[] = PonyDocsExtension::translateTopicTitleForDocLinks($title);
		}

		// Query the database for the list of toUrls we've collated
		if (!empty($toUrls)) {
			foreach ($toUrls as &$toUrl) {
				$toUrl = $dbr->strencode($toUrl);
			}
			$inUrls = "'" . implode("','", $toUrls) . "'";
			$query = "SELECT * FROM " . $wgDBprefix . "ponydocs_doclinks WHERE to_link IN ($inUrls)";
			$results = $dbr->query($query);
		}

		// Create array of links, sorted by product and version
		$links = array();
		// Loop through results and save into handy dandy links array
		if (!empty($results)) {
			foreach ($results as $result) {
				$fromProduct = '';
				$fromVersion = '';
				$displayUrl = '';

				if (strpos($result->from_link, PONYDOCS_DOCUMENTATION_NAMESPACE_NAME) !== false) {
					// If this is a PonyDocs style links, with slashes,
					// save product, version, display URL accordingly.
					$pieces = explode('/', $result->from_link);
					$fromProduct = ucfirst($pieces[1]);
					$fromVersion = ucfirst($pieces[2]);
					$displayUrl = $result->from_link;
				} else {
					// If this is a generic mediawiki style link, with colons (or not),
					// set product to the namespace, and remove namespace
					// from the display URL. Leave version blank.
					if (strpos($result->from_link, ':') !== false) {
						$pieces = explode(':', $result->from_link);
						$fromProduct = ucfirst($pieces[0]); // The "product" will be the namespace
						$displayUrl = $pieces[1]; // So the namespace doesn't show in every URL
					} else { // it's possible to have a link with no colons
						$fromProduct = 'Other'; // No namespace, so the "product" will be the string "Other"
						$displayUrl = ucfirst($result->from_link);
					}
					$fromVersion = 'None'; // No concept of versions outside of PonyDocs
				}

				// Put all this stuff in an array that we can use to generate HTML
				$links[$fromProduct][$fromVersion][] = array(
					'from_link' => $result->from_link,
					'to_link' => $result->to_link,
					'display_url' => $displayUrl
				);
			}
		}
		
		// Make HTML go!
		ob_start(); ?>

		<div class="doclinks">
			<h2>Inbound links to <?php echo $plainTitle; ?> from other topics.</h2>

			<?php
			// If there are no links, display a message saying as much
			if (empty($links)) { ?>
				<p>No links to <?php echo $plainTitle; ?> (and its inherited versions) from other topics.</p>
			<?php
			} else {
				// Display all links, ordered by product then version
				foreach ( $links as $fromProduct => $fromVersions ) {
					// If this is a PonyDocs Product
					if ( PonyDocsProduct::IsProduct($fromProduct) ) { 
						// Get versions for this product, so we can display the versions in the correct order
						PonyDocsProductVersion::LoadVersionsForProduct($fromProduct, true);
						$fromProductVersions = PonyDocsProductVersion::GetVersions($fromProduct);
						// If there are no valid versions for this product/user, then skip the product name header.
						if (! count($fromProductVersions)) {
							continue;
						} ?>
						<h2><?php echo $fromProduct; ?></h2>
						<?php
						foreach ($fromProductVersions as $fromProductVersionObj) {
							$fromProductVersionName = $fromProductVersionObj->getVersionName();
							// If there are doclinks from this version, print them
							if (array_key_exists($fromProductVersionName, $fromVersions)) {
								// Expand containers of incoming links from the current Product and Version
								// Expand containers of incoming links from other Products
								// But don't expand any containers if this is not a PonyDocs product
								$selected = '';
								if (($currentProduct != $fromProduct || $currentVersion == $fromProductVersionName)
									&& ! $collapseAll) {
									$selected = 'selected';
								} ?>
								<h3 class="doclinks-collapsible <?php print $selected; ?>">
									<?php echo $fromProduct . ' ' . $fromProductVersionName; ?>
								</h3>
								<ul>
								<?php
								foreach ($fromVersions[$fromProductVersionName] as $linkAry) {
									?>
									<li>
										<a href="<?php echo str_replace('$1', $linkAry['from_link'], $wgArticlePath); ?>">
											<?php echo $linkAry['display_url']; ?>
										</a>
									</li>
								<?php
								} ?>
								</ul>
								<?php
							}
						}
					} else { ?>
						<h2><?php echo $fromProduct; ?> </h2>
						<h3 class="doclinks-collapsible selected">Latest</h3>
						<?php
						// This is not a PonyDocs product, don't worry about sorting
						foreach ($fromVersions as $fromVersion => $fromVersionData) { ?>
							<ul>
							<?php
							foreach ($fromVersionData as $linkAry) { ?>
								<li>
									<a href="<?php echo str_replace('$1', $linkAry['from_link'], $wgArticlePath); ?>">
										<?php echo $linkAry['display_url']; ?>
									</a>
								</li>
							<?php
							} ?>
							</ul>
							<?php
						}
					}
				}
			} ?>
		</div>

		<?php
		$htmlContent = ob_get_contents();
		ob_end_clean();
		$wgOut->addHTML($htmlContent);
		return true;
	}
}