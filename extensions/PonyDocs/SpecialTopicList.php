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
$wgSpecialPages['TopicList'] = 'SpecialTopicList';

/**
 * This page should be passed a title which contains 'Documentation:<product>:<manual>:<topic>' only OR, if w/o params, shows all.
 * It is intended to show all topics of the given name.
 */
class SpecialTopicList extends SpecialPage
{
	/**
	 * Just call the base class constructor and pass the 'name' of the page as defined in $wgSpecialPages.
	 */
	public function __construct( )
	{
		SpecialPage::__construct( 'TopicList' );
	}

	public function getDescription( )
	{
		return 'Show Topic Listing';
	}

	/**
	 * This is called upon loading the special page.  It should write output to the page with $wgOut.  If passed a topic
	 * as 'topic' it lists all pages for that topic;  else it displays EVERYTHING for the selected version?
	 */
	public function execute( )
	{
		global $wgOut, $wgArticlePath, $wgRequest;

		$topic = $wgRequest->getVal( 'topic' );
		if( !$topic || !strlen( $topic )) {
			ob_start();
			?>
			<p>
			This special page is supposed to be called from a Documentation topic.  It's use is to list all available articles for a requested topic.
			</p>
			<p>
			To use this feature, browse to a Documentation article and click on the 'View All' link at the top of the article.
			</p>
			<?php
			$this->setHeaders();
			$wgOut->setPagetitle('Invalid Use Of This Special Page');
			$wgOut->addHTML(ob_get_contents());
			ob_end_clean();
			return;
		}

		if( !preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*)/i', $topic, $match ))
			return;

		$dbr = wfGetDB( DB_SLAVE );

		$this->setHeaders( );
		$wgOut->setPagetitle( 'Topic Listing For ' . $topic );
		$wgOut->addHTML( '<h2>Topic Listing For Topic <b>'. $match[3] . '</b> in ' . $match[2] . ' manual for ' . $match[1] . ' product.</h2>' );

		$q =	"SELECT DISTINCT(cl_sortkey) " .
				"FROM categorylinks " .
				"WHERE LOWER(CAST(cl_sortkey AS CHAR)) LIKE '" . $dbr->strencode( strtolower( $topic ) ) . ":%'";

		$res = $dbr->query( $q, __METHOD__ );
		if( !$res->numRows( ))
		{
			return;
		}

		$wgOut->addHTML( 'The following is a list of articles for the specified topic and the versions to which they apply.<br><br><ul>' );

		while( $row = $dbr->fetchObject( $res ))
		{
			$vRes = $dbr->select( 'categorylinks', 'cl_to', "cl_sortkey = '" . $row->cl_sortkey . "'", __METHOD__ );
			if( !$vRes->numRows( ))
				continue;

			$wgOut->addHTML( '<li>' . $row->cl_sortkey . ': ' );

			$hasVersions = false;
			while( $vRow = $dbr->fetchObject( $vRes ))
			{
				if( preg_match( '/^V:(.*):(.*)/i', $vRow->cl_to, $vmatch ))
				{
					$wgOut->addHTML( '<a href="' . str_replace( '$1', $row->cl_sortkey, $wgArticlePath ) . '">' . $vmatch[2] . '</a> ' );
					$hasVersions = true;
				}
			}
			if( !$hasVersions )
				$wgOut->addHTML( 'None' );

			$wgOut->addHTML( '</li><br>' );
		}

		$wgOut->addHTML( '</ul><br><br>' );
		return;
	}
}

/**
 * End of file.
 */
?>