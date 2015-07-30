<?php
require_once( "$IP/includes/page/Article.php" );

/**
 * Ok, so when using a URL alias we have to hook into ArticleFromTitle so that it can take the input TITLE and translate it to the REAL
 * article title.  From that it creates an Article object to pass back.  However this would NOT work -- it would act like there was no
 * content for every article resulting.
 * 
 * Oddly what did work is subclassing it and removing the fluff from 'getContent()' -- meaning everything.  It just returns the stored
 * content from the attribute 'mContent'.  This for some strange reason works perfectly -- onArticleFromTitle returns an instance of this
 * instead and voila.
 *
 */
class PonyDocsAliasArticle extends Article {
	
	/**
	 * Simply return the already loaded content.
	 *
	 * @param integer $oldid
	 * @return string
	 * 
	 */
	/** Doesn't look like we need this anymore...
	function getContent( $oldid = 0 ) 	{
		if ( !strlen( $this->mContent )) {
			$this->loadContent();
		}
		return $this->mContent;		
	}
	 */
};