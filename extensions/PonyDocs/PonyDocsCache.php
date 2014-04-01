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
				$this->logException('get', __METHOD__, $ex);
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
				$this->logException('get', __METHOD__, $ex);
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
				$this->logException('remove', __METHOD__, $ex);
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
				$this->logException('expire', __METHOD__, $ex);
			}
		}
		return true;
	}
	
	/**
	 * Log an exception so we don't have to repeat ourselves
	 * 
	 * @param string $action
	 * @param string $method
	 * @param Exception $exception 
	 */
	private function logException( $action, $method, $exception ) {
		$logArray = array(
			'action' => $action,
			'status' => 'failure',
			'line' => $exception->getLine(),
			'file' => $exception->getFile(),
			'message' => $exception->getMessage(),
			'trace' => $exception->getTraceAsString(),
		);
		$logString = '';
		// Surround value in quotes after escaping any existing double-quotes
		foreach ( $logArray as $key => $value) {
			$logString .= "$key=\"" . addcslashes( $value, '"' ) .'" ';
		}
		$logString = trim( $logString );
		
		error_log( "FATAL [PonyDocsCache] [$method] $logString" );
	}
};