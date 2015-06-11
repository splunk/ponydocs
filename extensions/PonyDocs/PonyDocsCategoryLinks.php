<?php

class PonyDocsCategoryLinks
{

	static public function getTOCByProductManualVersion($productShort, $manualShort, $version)
	{
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'categorylinks', 'cl_to', 
			array( 	"LOWER(cast(cl_sortkey AS CHAR)) LIKE 'documentation:" . $dbr->strencode(strtolower( $productShort ) . ':' . strtolower( $manualShort )) . "toc%'",
					"cl_to = 'V:" . $dbr->strencode($productShort . ":" . $version) . "'" ), __METHOD__ );
		return $res;
	}

	static public function getTOCCountsByProductVersion($productShort)
	{
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->query( "SELECT cl_to, COUNT(*) AS cl_to_ct 
							 FROM categorylinks 
							 WHERE LOWER(cast(cl_sortkey AS CHAR)) LIKE 'documentation:%toc%'
							 AND cl_to LIKE 'V:" . $dbr->strencode($productShort) . "%' 
							 GROUP BY cl_to" );
		return $res;
	}

	static public function getTOCCountsByProduct()
	{
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->query( "SELECT cl_to, COUNT(*) AS cl_to_ct 
							 FROM categorylinks 
							 WHERE LOWER(cast(cl_sortkey AS CHAR)) LIKE 'documentation:%toc%'
							 AND cl_to LIKE 'V:%' 
							 GROUP BY cl_to" );
		return $res;
	}

}

?>