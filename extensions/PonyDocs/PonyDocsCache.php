<?php

class PonyDocsCache 
{
	private $dbr;

	private static $instance;
	
	private function __construct()	{
		$this->dbr = wfGetDB(DB_MASTER);
	}
	
	static public function & getInstance() {
		if ( !self::$instance )	{
			self::$instance = new PonyDocsCache();
		}
		return self::$instance;
	}
	
	/**
	 * Add an etnry to the cache
	 * 
	 * @param string $key
	 * @param string $data
	 * @param int $ttl number of seconds that the cache entry is valid
	 * @param int $fudgeFactor max number of seconds to randomly fudge the ttl
	 * @return boolean
	 */
	public function put( $key, $data, $ttl, $fudgeFactor = 0 )	{
			if ( PONYDOCS_CACHE_ENABLED ) {
				$expires = 'UNIX_TIMESTAMP() + ' . $ttl;
				$data = $this->dbr->strencode(serialize($data));
				$query = "INSERT INTO ponydocs_cache VALUES('$key', $expires, '$data')"
					. " ON DUPLICATE KEY UPDATE data = '$data', expires = $expires";
				try {
					$this->dbr->query( $query );
					$this->dbr->query( "COMMIT" );
				} catch ( Exception $ex ){
					$this->logException('put', __METHOD__, $ex);
				}
			}
			return true;
		}
		
	public function get( $key ) {
		if ( PONYDOCS_CACHE_ENABLED ) {
			$query = "SELECT *  FROM ponydocs_cache WHERE cachekey = '$key' AND expires > UNIX_TIMESTAMP()";
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
	public function getTopicHeaderCache( $key ) {
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
				$res = $this->dbr->query( "COMMIT" );
			} catch ( Exception $ex ) {
				$this->logException('remove', __METHOD__, $ex);
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
	
	/**
	 * Randomly fudge the timeout constant to avoid having multiple cache entries with the same expiration time
	 * Ideally this will help avoid cache storms
	 * 
	 * @param integer $ttl the TTL for a cache entry
	 * @param integer $fudgeFactor The TTL will be increased or decreased by a random number of seconds between 0 and $fudgeFactor
	 * 
	 * @return integer
	 */
	private function fudgeTtl($ttl, $fudgeFactor) {
		if ( $fudgeFactor ) {
			$ttl += rand(-$fudgeFactor, $fudgeFactor);
		}
		
		return $ttl;
	}
};
