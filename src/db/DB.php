<?php
namespace wiggum\services\db;

class DB {
	
	private $connection = null;
	
	/**
	 * 
	 * @param array $config
	 */
	public function __construct($config) 
	{
	    if (!empty($config)) {

			$connectType = isset($config['connection']) ? $config['connection'] : '\wiggum\services\db\connections\MySql';

			$this->connection = new $connectType();
	        $this->connection->connect($config);
	    }
	}
	
	/**
	 * 
	 * @return Ambigous
	 */
	public function getConnection() 
	{
		if (!$this->connection) {
			throw new \RuntimeException('DB connection is not configured. Construct DB with a connection config.');
		}
		return $this->connection;
	}
	
	/**
	 * 
	 * @param string $table
	 * @return \wiggum\services\db\Builder
	 */
	public function table($table) 
	{
		if (!$this->connection) {
			throw new \RuntimeException('DB connection is not configured. Construct DB with a connection config.');
		}

		$query = new Builder($this->connection, $this->connection->getGrammar());
		
		return $query->from($this->connection->getPrefix().$table);
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function beginTransaction() 
	{
		if (!$this->connection) {
			throw new \RuntimeException('DB connection is not configured. Construct DB with a connection config.');
		}
		return $this->connection->beginTransaction();
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function doRollBack()
	{
		if (!$this->connection) {
			throw new \RuntimeException('DB connection is not configured. Construct DB with a connection config.');
		}
		return $this->connection->doRollBack();
	}
	
	/**
	 * 
	 * @param boolean $selfRollBack [default=false]
	 * 
	 * @return boolean
	 */
	public function doCommit($selfRollBack = false) 
	{
		if (!$this->connection) {
			throw new \RuntimeException('DB connection is not configured. Construct DB with a connection config.');
		}
		return $this->connection->doCommit($selfRollBack);
	}
		
}
