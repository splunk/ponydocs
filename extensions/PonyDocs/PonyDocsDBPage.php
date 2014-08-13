<?php

class PonyDocsDBPage
{

	static public function getCaseInsensitiveMatch($pageTitle)
	{
		$dbr = wfGetDB( DB_SLAVE );
		$rows = array();
		$res = $dbr->select( 'page', 'page_title',
			array( 	"LOWER(cast(page_title AS CHAR)) LIKE '" . $dbr->strencode(strtolower( $pageTitle )) . "'",
					"page_namespace = " . NS_PONYDOCS ), __METHOD__ );
		while ($row = $res->fetchRow()) {
			$rows[] = $row;
		}
		return $rows;
	}

}

?>