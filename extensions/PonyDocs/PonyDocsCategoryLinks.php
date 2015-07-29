<?php

class PonyDocsCategoryLinks
{

	static public function getTOCByProductManualVersion( $productShort, $manualShort, $version ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'categorylinks',
			'cl_to', 
			array(
				"cl_to = 'V:" . $dbr->strencode( "$productShort:$version" ) . "'",
				'cl_type = "page"',
				"cl_sortkey LIKE '" . $dbr->strencode( strtoupper( "$productShort:$manualShort" ) ) . "TOC%'",
			),
			__METHOD__
		);
		return $res;
	}

	static public function getTOCCountsByProductVersion( $productShort ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'categorylinks',
			array('cl_to', 'COUNT(*) AS cl_to_ct'), 
			array(
				"cl_to LIKE 'V:" . $dbr->strencode( $productShort ) . ":%'",
				'cl_type = "page"',
				"cl_sortkey LIKE '%TOC%'",
			),
			__METHOD__,
			'GROUP BY cl_to'
		);
		return $res;
	}

	static public function getTOCCountsByProduct() {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			'categorylinks',
			array('cl_to', 'COUNT(*) AS cl_to_ct'), 
			array(
				"cl_to LIKE 'V:%:%'",
				'cl_type = "page"',
				"cl_sortkey LIKE '%TOC%'",
			),
			__METHOD__,
			'GROUP BY cl_to'
		);
		return $res;
	}
}