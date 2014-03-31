<?php

class PonyDocsCache 
{
	private $dbr;

	private static $instance;

	private function __construct()	{
		$this->dbr = wfGetDB(DB_MASTER);
		$this->expire();
	}
	
	static public function & getInstance() {
		if ( !self::$instance )	{
			self::$instance = new PonyDocsCache();
		}
		return self::$instance;
	}
	
	public function put( $key, $data, $expires = null )	{
		if ( PONYDOCS_CACHE_ENABLED ) {
			if ( !$expires ) {
				$expires = time() + 3600;
			}
			$data = mysql_real_escape_string(serialize($data));
			$query = "INSERT IGNORE INTO ponydocs_cache VALUES('$key', '$expires',  '$data')";
			try {
				$this->dbr->query( $query );
			} catch ( Exception $ex ){
				error_log( "FATAL [PonyDocsCache::put] DB call failed on Line " . $ex->getLine() . " on file " . $ex->getFile()
					. ", error Message is: \n" . $ex->getMessage() . "Stack Trace is:" . $ex->getTraceAsString() );
			}
		}
		return true;		
	}
	
	public function get( $key ) {
		if ( PONYDOCS_CACHE_ENABLED ) {
			$query = "SELECT *  FROM ponydocs_cache WHERE cachekey = '$key'";
			try {
				$res = $this->dbr->query( $query );
				$obj = $this->dbr->fetchObject( $res );
				if ( $obj ) {
					return unserialize( $obj->data );
				}
			} catch ( Exception $ex ) {
				error_log( "FATAL [PonyDocsCache::get] DB call failed on Line " . $ex->getLine() . " on file " . $ex->getFile()
					. ", error Message is: \n" . $ex->getMessage() . " Stack Trace Is: " . $ex->getTraceAsString() );
			}
		}
		return null;
	}
	
	public function remove( $key ) {
		if ( PONYDOCS_CACHE_ENABLED ) {
			$query = "DELETE FROM ponydocs_cache WHERE cachekey = '$key'";
			try {
				$res = $this->dbr->query( $query );
			} catch ( Exception $ex ) {
				error_log( "FATAL [PonyDocsCache::remove] DB call failed on Line " . $ex->getLine() . " on file " . $ex->getFile()
					. ", error Message is: \n" . $ex->getMessage() . " Stack Trace Is: " . $ex->getTraceAsString() );
			}
		}
		return true;
	}	

	public function expire() {
		if ( PONYDOCS_CACHE_ENABLED ) {
			$now = time();
			$query = "DELETE FROM ponydocs_cache WHERE expires < $now";
			try {
				$res = $this->dbr->query( $query );
			} catch ( Exception $ex ) {
				error_log( "FATAL [PonyDocsCache::expire] DB call failed on Line " . $ex->getLine() . " on file " . $ex->getFile()
					. ", error Message is: \n" . $ex->getMessage() . " Stack Trace Is: " . $ex->getTraceAsString());
			}
		}
		return true;
	}
};