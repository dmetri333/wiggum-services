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
		return $this->connection->getConnection();
	}
	
	/**
	 * 
	 * @param string $table
	 * @return \wiggum\services\db\Builder
	 */
	public function table($table) 
	{
		$query = new Builder($this, $this->connection->getGrammar());
		
		return $query->from($this->connection->getPrefix().$table);
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function beginTransaction() 
	{
		return $this->connection->beginTransaction();
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function doRollBack()
	{
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
		return $this->connection->doCommit($selfRollBack);
	}
	
	
	/**
	 * Set corresponding fields in an object from result set.
	 *
	 * @param string $query
	 * @param array $values
	 * @param object $instance - the initialized object that the fields should be set in
	 * 
	 * @return object
	 */
	public function fetchObject(string $query, array $values, $instance) 
	{
		return $this->connection->fetchObject($query, $values, $instance);
	}
	
	/**
	 * Set corresponding fields in an object from result set.
	 * 	$instance is the initialized object that the fields should be set in
	 *
	 * @param string $query
	 * @param array $values
	 * @param object $instance - the initialized object that the fields should be set in
	 * 
	 * @return array
	 */
	public function fetchObjects(string $query, array $values, $instance) 
	{
		return $this->connection->fetchObjects($query, $values, $instance);
	}
	
	/**
	 *
	 * @param string $query
	 * @param array $values
	 * @param bool $assoc
	 * 
	 * @return array|object
	 */
	public function fetchRow(string $query, array $values, bool $assoc = false) 
	{
		return $this->connection->fetchRow($query, $values, $assoc);
	}
	
	/**
	 *
	 * @param string $query
	 * @param array $values
	 * @param bool $assoc
	 * 
	 * @return array
	 */
	public function fetchRows(string $query, array $values, bool $assoc = false) 
	{
		return $this->connection->fetchRows($query, $values, $assoc);
	}
	
	/**
	 * 
	 * @param string $query
	 * @param array $values
	 *
	 * @return array
	 */
	public function fetchAllColumn(string $query, array $values) {
		return $this->connection->fetchAllColumn($query, $values);
	}

	/**
	 * Retrieve first column in results set
	 *
	 * @param string $query
	 * @param array $values
	 * 
	 * @return string
	 */
	public function fetchColumn(string $query, array $values) 
	{
		return $this->connection->fetchColumn($query, $values);
	}
	
	/**
	 *
	 * @param string $query
	 * @param array $values
	 * @param bool $assoc
	 * 
	 * @return array
	 */
	public function fetchRowsWithColumnKey(string $query, array $values, bool $assoc = false) 
	{
		return $this->connection->fetchRowsWithColumnKey($query, $values);
	}

	/**
	 *
	 * @param string $query
	 * @param array $values
	 * 
	 * @return array
	 */
	public function fetchKeyValuePair(string $query, array $values) 
	{
		return $this->connection->fetchKeyValuePair($query, $values);
	}
	
	/**
	 *
	 * @param string $query
	 * @param array $values
	 * @param bool $lastInsId [default=true]
	 * 
	 * @return int | boolean
	 */
	public function executeQuery(string $query, array $values, bool $lastInsId = true) 
	{
		return $this->connection->executeQuery($query, $values, $lastInsId);
	}
	
	
}
