<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

$wgSpecialPages['SpecialLatestDoc'] = 'SpecialLatestDoc';


/**
 * This page states the title is not available in the latest documentation 
 * version available to the user and gives the user a chance to view the topic 
 * in a previous version where it is available.
 */

class SpecialLatestDoc extends SpecialPage {
	private $categoryName;
	private $skin;
	private $titles;

	/**
	 * Call the parent with our name.
	 */
	public function __construct() {
		SpecialPage::__construct("SpecialLatestDoc");
	}

	/**
	 * Return our description.  Used in Special::Specialpages output.
	 */
	public function getDescription() {
		return "View the latest version that the requested documentation is available in.";
	}

	/**
	 * This is called upon loading the special page.  It should write output to 
	 * the page with $wgOut
	 */
	public function execute($params) {
		global $wgOut, $wgArticlePath, $wgScriptPath, $wgUser;
		global $wgRequest;

		$this->setHeaders();
		$title =  $wgRequest->getVal('t');
		$sanitizedTitle = htmlspecialchars($title, ENT_QUOTES);
		$wgOut->setPagetitle("Latest Documentation For " . $title );

		$dbr = wfGetDB( DB_SLAVE );

		/**
		 * We only care about Documentation namespace for rewrites and they must contain a slash, so scan for it.
		 * $matches[1] = product
		 * $matches[2] = latest|version
		 * $matches[3] = manual
		 * $matches[4] = topic
		 */
		if (!isset($title) || $title == '' || $title = NULL) {
                        $logFields = "action=SpecialDoc status=failure error=\"Failed to obtain value for parameter t\"";
                        error_log('WARNING [' . __METHOD__ ."] [SpecialLastestDoc] $logFields");
                        ?>
                        <p>
                        Sorry, please pass in a valid parameter <b>t</b> to get the desired documentation.
                        </p>
                        <?php
		} else if( !preg_match( '/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '\/([' . PONYDOCS_PRODUCT_LEGALCHARS. ']*)\/(.*)\/(.*)\/(.*)$/i', $title, $matches )) {
			?>
			<p>
			Sorry, but <?php echo $sanitizedTitle;?> is not a valid Documentation url.
			</p>
			<?php
		}
		else {
			/**
			 * At this point $matches contains:
			 * 	0= Full title.
			 *  1= Product name (short name).
			 *  2= Version OR 'latest' as a string.
			 *  3= Manual name (short name).
			 *  4= Wiki topic name.
			 */
			$productName = $matches[1];
			$versionName = $matches[2];
			$manualName = $matches[3];
			$topicName = $matches[4];
			if(strcasecmp('latest', $versionName) !== 0) { // version is NOT 'latest'
				?>
				<p>
				Sorry, but <?php echo $sanitizedTitle;?> is not a latest Documentation url.
				</p>
				<?php
			} else { // version is 'latest'
				$versionList = PonyDocsProductVersion::GetReleasedVersions( $productName, true );
				if (!is_array($versionList)) { // product is bunk or didn't return any released versions
					?>
						<p>
						Sorry, but <?php echo $productName; ?> is not a valid Product.
						</p>
					<?php
					} else { // we found some versions for this product. proceed.
						$versionList = array_reverse( PonyDocsProductVersion::GetReleasedVersions( $productName, true ));

					/**
					 * This will be a DESCENDING mapping of version name to PonyDocsVersion object and will ONLY contain the
					 * versions available to the current user (i.e. LoadVersions() only loads the ones permitted).
					 */

					$versionNameList = array( );
					$versionSql = array();
					$latestVersionSql = null;
					foreach($versionList as $pV ) {
						if( $latestVersionSql == null ) 
						{
							$latestVersionSql = 'V:' . $productName . ':' . $pV->getVersionName( );
						}
						$versionNameList[] = $pV->getVersionName( );
						$versionSql[] = '\'V:' . $productName . ':' . $pV->getVersionName( ) . '\'';
					}
					$versionSql = '(' . implode( ",",$versionSql ) . ')';

					$suggestions = array();
					$primarySuggestions = array();

					/**
					 * Now build a list of suggestions in priority.
					 * 1) Same product, different manual, current version.
					 */
					$res = $dbr->select( 'categorylinks', array( 'cl_sortkey', 'cl_to' ),
										 "LOWER(cast(cl_sortkey AS CHAR)) REGEXP '" . 
										 $dbr->strencode( '^' . strtolower( PONYDOCS_DOCUMENTATION_PREFIX . $productName . ":[^:]+:" . $topicName .":[^:]+$" ) ) . "'" .
										 " AND cast(cl_to AS CHAR) = '" . $latestVersionSql . "'", 
										__METHOD__ );

					if( $res->numRows( ) )
					{
						$tempSuggestions = $this->buildSuggestionsFromResults( $res );
						// Take the top 5 and put them in primary suggestions
						$primarySuggestions = array_splice( $tempSuggestions, 0, count( $tempSuggestions ) > 5 ? 5 : count( $tempSuggestions ) );
						$suggestions = $suggestions + $tempSuggestions;
					}
					/*
					 * 2) Same product, same manual, earlier version
					 */
					$res = $dbr->select( 'categorylinks', array( 'cl_sortkey', 'cl_to' ),
										 "LOWER(cast(cl_sortkey AS CHAR)) REGEXP '" . 
										 $dbr->strencode( '^' . strtolower( PONYDOCS_DOCUMENTATION_PREFIX . $productName . ":" . $manualName . ":" . $topicName .":[^:]+$" ) ) . "'" .
										 " AND cast(cl_to AS CHAR) IN" . $versionSql, 
										__METHOD__ );

					if( $res->numRows( ) )
					{
						$tempSuggestions = $this->buildSuggestionsFromResults( $res );
						// Take the top 5 and put them in primary suggestions
						$primarySuggestions = $primarySuggestions + array_splice( $tempSuggestions, 0, count( $tempSuggestions ) > 5 ? 5 : count( $tempSuggestions ) );
						$suggestions = $suggestions + $tempSuggestions;
					}

					/*
					 * 3) Same product, different manual, earlier version
					 *
					 * Note: The regular expression will match ALL manuals, including the passed manual. There is no good regex way to 
					 * properly evaluate not matching a string but match others. So we will filter it out of the results.
					 */
					$res = $dbr->select( 'categorylinks', array( 'cl_sortkey', 'cl_to' ),
										 "LOWER(cast(cl_sortkey AS CHAR)) REGEXP '" . 
										 $dbr->strencode( '^' . strtolower( PONYDOCS_DOCUMENTATION_PREFIX . $productName . ":[^:]+:" . $topicName .":[^:]+$" ) ) . "'" .
										 " AND cast(cl_to AS CHAR) IN" . $versionSql, 
										__METHOD__ );
					if( $res->numRows( ) )
					{
						$tempSuggestions = $this->buildSuggestionsFromResults( $res );
						// Take the top 5 and put them in primary suggestions
						$primarySuggestions = $primarySuggestions + array_splice( $tempSuggestions, 0, count( $tempSuggestions ) > 5 ? 5 : count( $tempSuggestions ) );
						$suggestions = $suggestions + $tempSuggestions;
					}
					ob_start();
					?>
					<p>
					Hi! Just wanted to let you know:
					</p>
					<p>
					The topic you've asked to see does not apply to the most recent version.
					</p>
					<p>
					To search the latest version of the documentation, click <a href="<?php echo $wgScriptPath;;?>/Special:Search?search=<?php echo $matches[4];?>">Search</a></li>
					</p>
					<?php
					if( count( $primarySuggestions ) )
					{
						?>
						<h2>Suggestions</h2>
						<p>
						The following suggestions for the topic you requested were found:
						</p>
						<ul id="suggestions">
						<?php
						foreach( $primarySuggestions as $suggestion ) {
							?>
							<li><?php echo $suggestion['product'];?> &raquo; <?php echo $suggestion['version'];?> &raquo; <?php echo $suggestion['manual'];?> &raquo; 
							<a href="<?php echo $wgScriptPath;?>/<?php echo $suggestion['url'];?>"><?php echo $suggestion['title'];?></a></li>
							<?php
						}
						if( count( $suggestions ) )
						{
							foreach( $suggestions as $suggestion ) {
								?>
									<li style="display: none;"><?php echo $suggestion['product'];?> &raquo; <?php echo $suggestion['version'];?> &raquo; <?php echo $suggestion['manual'];?> &raquo; 
									<a href="<?php echo $wgScriptPath;?>/<?php echo $suggestion['url'];?>"><?php echo $suggestion['title'];?></a></li>
								<?php

							}
						}
						?>
						</ul>
						<?php
						if( count( $suggestions ) )
						{
							?>
								<a href="#" id="suggestion_expand">Show Additional <?php echo count( $suggestions );?> Suggestion<?php echo count( $suggestions ) == 1 ? '' : 's';?></a>
								<script type="text/javascript">
								jQuery(function() {
									   jQuery('#suggestion_expand').click( function(event) {
											event.preventDefault();
											jQuery('#suggestions li').show();
											jQuery(this).hide();
										});
								});
								</script>
							<?php
						}
					}
					?>
					<?php
				}
			}
		}

		$htmlContent = ob_get_contents();
		ob_end_clean();
		$wgOut->addHTML( $htmlContent );
		return true;
	}

	/**
	 * Builds and returns a suggestion array from results retrieved from the 
	 * categorylinks table.
	 *
	 * @param $res array A collection of records from the categorylinks table. Represents valid suggestions.
	 * @return array A list of suggestions with populated information for url,title,manual,product and version.
	 */
	private function buildSuggestionsFromResults( $res )
	{
		$suggestions = array();
		foreach( $res as $row ) 
		{
			$tags = explode( ":", $row->cl_to );
			$productName = $tags[1];
			$versionName = $tags[2];

			$article = PonyDocsArticleFactory::getArticleByTitle( $row->cl_sortkey );
			if( !$article )
			{
				continue;
			}
			$topic = new PonyDocsTopic( $article );
			$ver = PonyDocsProductVersion::GetVersionByName( $productName, $versionName );
			if( !$ver )
			{
				continue;
			}
			$meta = PonyDocsArticleFactory::getArticleMetadataFromTitle( $row->cl_sortkey );
			$url = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . '/' . $meta['product'] . '/' . $versionName . '/' . $meta['manual'] . '/' . $meta['topic'];
			$suggestions[$url] = array(
				'url' => $url,
				'title' => $topic->FindH1ForTitle( $row->cl_sortkey ),
				'manual' => $meta['manual'],
				'product' => $meta['product'],
				'version' => $versionName
			);
		}
		// Sort array based on manual/version.
		uasort( $suggestions, "SpecialLatestDoc::suggestionSort" );
		return $suggestions;
	}

	/**
	 * Sorts a suggestion array. For use in uasort call in 
	 * buildSuggestionsFromResults
	 *
	 * @param $first array The first operator in comparison
	 * @param $second array The second operator in comparison
	 * @return 0 if same, -1 if less than, 1 if greater than.
	 */
	private static function suggestionSort($first, $second)
	{
		$manualCompare = strcmp( $first['manual'], $second['manual'] );
		if( $manualCompare != 0 )
		{
			return $manualCompare;
		}
		$versionList = array_keys( array_reverse( PonyDocsProductVersion::GetReleasedVersions( $first['product'], true ) ) );
		$firstIndex = array_search( $first['version'], $versionList );
		$secondIndex = array_search( $second['version'], $versionList );
		if( $firstIndex < $secondIndex )
		{
			return -1;
		} else if( $firstIndex > $secondIndex )
		{
			return 1;
		}
		return 0;
	}
}

