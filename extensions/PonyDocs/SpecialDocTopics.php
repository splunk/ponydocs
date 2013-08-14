<?php
if( !defined( 'MEDIAWIKI' ))
	die( "PonyDocs MediaWiki Extension" );

/**
 * Needed since we subclass it;  it doesn't seem to be loaded elsewhere.
 */
require_once( $IP . '/includes/SpecialPage.php' );

/**
 * Register our 'Special' page so it is listed and accessible.
 */
$wgSpecialPages['DocTopics'] = 'SpecialDocTopics';

/**
 * Simple 'Special' MediaWiki page which must list all defined TOC management pages (as links) along with the
 * list of versions for which they are tagged.  Additionally it provides links to the special Manuals and
 * Versions management pages for easier access to this functionality.
 */
class SpecialDocTopics extends SpecialPage
{
	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 */
	public function __construct( )
	{
		SpecialPage::__construct( "DocTopics" );
	}

	public function getDescription( )
	{
		return 'Documentation Topic Listing';
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.
	 */
	public function execute( )
	{
		global $wgOut, $wgArticlePath;
		global $wgUser, $ponyDocsBaseAuthorGroup;

		// disable this special page for now per WEB-5698
		$wgOut->addHTML( "<h1>Page Disabled</h1><p>This page has been disabled.</p>" );
		return false;







		$dbr = wfGetDB( DB_SLAVE );

		$this->setHeaders( );
		$wgOut->setPagetitle( 'Documentation Topics Listing' );

		// Security Check
		$groups = $wgUser->getGroups( );

		if(!in_array( $ponyDocsBaseAuthorGroup, $groups)) {
			$wgOut->addHTML("<p>Sorry, but you do not have permission to access this Special page.</p>");
			return;
		}

		$wgOut->addHTML( 	"<p>This page lists all topics in the Documentation namespace.  For each, the title(s) are listed
							along with the versions they are tagged for.  A list of TOC management pages which list this
							topic as part of their contents is shown, or if there are none, it will be described as Orphaned.</p><br><br>" );

		/**
		 * Get ALL documentation namespace topics.  For each, topic list all pages and the versions associated
		 * with them (tagged) and list and link which TOC management pages reference each topic.  We do this in
		 * table form.  
		 * | Topic Base Name | Actual Title (1.0 2.0) | 
		 * |				 | Actual Title (2.1)
		 * This is a huge headache.  But we can make some assumptions.  For any given title, it can be only linked
		 * TO from a TOC page which shares one or more version tags with it.  So we can at least reduce the number
		 * of TOC pages to search
		 */
		$res = $dbr->select( 'page', 'page_title', "page_namespace = '" . PONYDOCS_DOCUMENTATION_NAMESPACE_ID . "'", __METHOD__ );

		/**
		 * This will hold a LIST of base topic names to build off of.
		 */
		$baseTopicList = array( );
		$outputData = array( );

		while( $row = $dbr->fetchObject( $res ))
		{
			/**
			 * Only store doc topics and do not store duplicates -- we just want the unique list of BASE topic
			 * names, not titles.
			 */
			if( preg_match( '/(.*):(.*):(.*):(.*)/', $row->page_title, $match ))
			{
				$baseTopic =  $match[1] . ':' . $match[2] . ':' . $match[3];
				if( !in_array( $baseTopic, $baseTopicList ))
					$baseTopicList[] = $baseTopic;
			}
		}

		/**
		 * Now loop through our base topic list.  For each we need to retrieve all titles associated with it.  Then
		 * for each title we need to get the versions tagged for from category links.  The final part is to find
		 * any TOCs which list this as content.
		 */
		foreach( $baseTopicList as $baseTopic )
		{
			//echo "Base Topic is [$baseTopic]\n";

			$res = $dbr->select( 'page', 'page_title', "page_title LIKE '" . $dbr->strencode( $baseTopic ) . ":%'", __METHOD__ );
			if( !$res->numRows( ))
				continue;

			//echo "Found one or more pages for base topic.\n";

			$outputData[$baseTopic] = array( 'topics' => array( ), 'tocs' => array( ));

			$pieces = explode( ':', $baseTopic );
			$product = $pieces[0];
			$manual = $pieces[1];

			/**
			 * This holds a list of all versions for which any title was tagged in this base topic name.
			 */
			$allVersions = array( );

			while( $row = $dbr->fetchObject( $res ))
			{

				$outputData[$baseTopic]['topics'][$row->page_title] = array( );
				$versionRes = $dbr->select( 'categorylinks', 'cl_to', "cl_sortkey = '" . PONYDOCS_DOCUMENTATION_PREFIX . $dbr->strencode( $row->page_title ) . "'", __METHOD__ );
				if( $versionRes->numRows( ))
				{
					while( $versionRow = $dbr->fetchObject( $versionRes ))
					{
						if( preg_match( '/^V:' . $product . ':(.*)/i', $versionRow->cl_to, $vmatch ))
						{
							$outputData[$baseTopic]['topics'][$row->page_title][] =  $product . ':' . $vmatch[1];
							if( !in_array( $vmatch[1], $allVersions ))
								$allVersions[] = $vmatch[1];
						}
					}
				}
			}

			/**
			 * At this point, each base topic in $outputData should have an array called 'topics'.  This array has a key for each
			 * title (which belongs to the topic) with the value being a list of versions for which it is tagged.  Our $allVersions
			 * should also be a list of ANY version which this topic (in any title) has been tagged.
			 */

			/**
			 * Now we need to find the TOC pages which link to this.  There is no clean/easy way of doing this.  What we do is
			 * determine the list of applicable TOC pages.  This will be a set of titles '<manual>TOC*' which are tagged with
			 * one or more versions in our $allVersions list.  We then need to open each and parse the {{#topic}} tags by 
			 * converting them to title names THEN find any instance of baseTopic in that list.
			 */

			$toc = PONYDOCS_DOCUMENTATION_PREFIX . $product . ':' . $manual . 'TOC';

			$res = $dbr->select( 'categorylinks', 'cl_sortkey',
				array( 	"LOWER(cast(cl_sortkey AS CHAR)) LIKE '" . $dbr->strencode( strtolower( $toc )) . "%'",
						"cl_to IN ('V:$product:" . implode( "','V:$product:", $allVersions ) . "')" ), __METHOD__ );

			/**
			 * Loop through all TOC pages tagged with a version our base topic is tagged for.
			 */
			while( $row = $dbr->fetchObject( $res ))
			{
				/**
				 * Open the TOC page so we can parse it.  We will extract all {{#topic:}} tags and conver them to wiki names
				 * and store into a list.  Then we just check if your base topic is in this list.
				 */
				$article = new Article( Title::newFromText( $row->cl_sortkey ));
				if( !$article->exists( ))
					continue;

				$content = $article->getContent( );

				if( preg_match_all( '/{{#topic:(.*)}}/i', $content, $matches, PREG_SET_ORDER ))
				{
					$topicList = array( );
					foreach( $matches as $m )
					{
						$topicList[] = $manual . ':' . preg_replace( '/([^' . str_replace( ' ', '', Title::legalChars( )) . '])/', '', $m[1] );
					}
					if( in_array( $baseTopic, $topicList ))
						$outputData[$baseTopic]['tocs'][] = $row->cl_sortkey;
				}
			}
		}

		/**
		 * Construct output HTML as a table.
		 */

		$html ="<table>
				<tr>
					<th>Topic</th>
					<th>Titles &amp; Versions</th>
					<th>TOC Pages Linked From</th>
				</tr>";

		foreach( $outputData as $baseTopic => $data )
		{
			$html .=   "<tr vAlign=\"top\">
						<td>$baseTopic</td>
						<td>";

			foreach( $data['topics'] as $title => $versionList )
			{
				$html .=	"<a href=\"" . str_replace( '$1', PONYDOCS_DOCUMENTATION_PREFIX . $title, $wgArticlePath ) . "\">$title</a> ";
				if( sizeof( $versionList ))
				{
					$html .= ' - [Version(s): ';
					foreach( $versionList as $version )
						$html .= "<a href=\"" . str_replace( '$1', "Category:" . $version, $wgArticlePath ) . "\">$version</a> ";
					$html .= ']<br>';
				}
				else
					$html .= '- [Not tagged for any version.]<br>';
			}

			$html .=	"</td><td>";

			if( sizeof( $data['tocs'] ))
			{
				foreach( $data['tocs'] as $toc )
				{
					$html .=	"<a href=\"" . str_replace( '$1', $toc, $wgArticlePath ) . "\">$toc</a><br>";
				}
			}
			else
				$html .= 'Orphaned';
			$html .=	"</td></tr>";
		}

		$html .=	"</table>";

		$wgOut->addHTML( $html );

	}
}
?>
