<?php
namespace wiggum\services\db;

use \PDO;

class DB {
	
	private $pdo;
	private $transactionError = false;
	
	/**
	 * 
	 * @param array $config
	 */
	public function __construct($config) {
		$this->connect($config['protocol'], $config['username'], $config['password'], $config['url'], $config['name']);
	}
	
	/**
	 * 
	 * @return Ambigous <NULL, \PDO>
	 */
	public function getPDO() {
		return $this->pdo;
	}
	
	/**
	 * 
	 * @param string $table
	 * @return \wiggum\db\Builder
	 */
	public function table($table) {
		$query = new Builder($this, new Grammar());
		
		return $query->from($table);
	}
	
	/**
	 *
	 * @param string $protocol
	 * @param string $user
	 * @param string $url
	 * @param string $database
	 * @return /PDO
	 */
	public function connect($protocol, $user, $password, $url, $name) {
		$pdo = null;
		try {
			$options = array(PDO::ATTR_PERSISTENT => true);
			$pdo = new PDO("{$protocol}:host={$url};dbname={$name}", $user, $password, $options);
			$pdo->exec('SET NAMES utf8');
		} catch (PDOException $e) {
			$pdo = null;
			//Logger::error($e->getMessage(), __CLASS__);
			throw new InternalErrorException('Database failed to connect');
		}
		$this->pdo = $pdo;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function beginTransaction() {
		try {
			$this->pdo->beginTransaction();
			return true;
		} catch (PDOException $e) {
			//Logger::error($e->getMessage(), __CLASS__);
		}
		return false;
	}
	
	
	/**
	 * 
	 * @return boolean
	 */
	public function doRollBack() {
		try {
			$this->pdo->rollBack();
			return true;
		} catch (PDOException $e) {
			//Logger::error($e->getMessage(), __CLASS__);
		}
		return false;
	}
	
	/**
	 * 
	 * @param boolean $selfRollBack [default=false]
	 * @return boolean
	 */
	public function doCommit($selfRollBack = false) {
		try {
			if ($selfRollBack) {
				if ($this->transactionError) {
					$this->doRollBack();
					return false;
				}
			}
			
			$this->pdo->commit();
			return true;
		} catch (PDOException $e) {
			//Logger::error($e->getMessage(), __CLASS__);
		}
		return false;
	}
	
	
	/**
	 * Set corresponding fields in an object from result set.
	 *
	 * @param string $query
	 * @param array $values
	 * @param object $instance - the initialized object that the fields should be set in
	 * @param array $callback [default=array()]
	 * @return object
	 */
	public function fetchObject($query, array $values, $instance, $fetchMode = PDO::FETCH_INTO) {
		$obj = null;
		try {
			$statement = $this->pdo->prepare($query);
			$statement->setFetchMode($fetchMode, $instance);
			if ($statement->execute($values)) {
				$obj = $statement->fetch($fetchMode);
				if (!$obj) return null;
			} else {
				$errorInfo = $statement->errorInfo();
				//Logger::error($errorInfo[2], __CLASS__);
			}
		} catch (PDOException $e) {
			//Logger::error($e->getMessage(), __CLASS__);
		}
		return $obj;
	}
	
	/**
	 * Set corresponding fields in an object from result set.
	 * 	$instance is the initialized object that the fields should be set in
	 *
	 * @param string $query
	 * @param array $values
	 * @param object $instance - the initialized object that the fields should be set in
	 * @param array $callback [default=array()]
	 * @return array
	 */
	public function fetchObjects($query, array $values, $instance, $fetchMode = PDO::FETCH_INTO) {

		$objects = array();
		try {
			$statement = $this->pdo->prepare($query);
			$statement->setFetchMode($fetchMode, $instance);
			if ($statement->execute($values)) {
				while (($obj = $statement->fetch($fetchMode))) {
					if (isset($obj))
						$objects[] = clone $obj;
				}
			} else {
				//Logger::error('Error exec statement' , __CLASS__);
			}
		} catch (PDOException $e) {
			//Logger::error($e->getMessage(), __CLASS__);
		}
		return $objects;
	
	}
	
	/**
	 *
	 * @param string $query
	 * @param array $values
	 * @param int $fetchMode [default=PDO::FETCH_ASSOC]
	 * @return array|object
	 */
	public function fetchRow($query, array $values, $fetchMode = PDO::FETCH_ASSOC) {
		$row = null;
		try {
			$statement = $this->pdo->prepare($query);
			if ($statement->execute($values)) {
				$row = $statement->fetch($fetchMode);
				if ($row === false) $row = null;
			} else {
				$errorInfo = $statement->errorInfo();
				//Logger::error($errorInfo[2], __CLASS__);
			}
		} catch (PDOException $e) {
			//Logger::error($e->getMessage(), __CLASS__);
		}
		return $row;
	}
	
	/**
	 *
	 * @param string $query
	 * @param array $values
	 * @param int $fetchMode [default=PDO::FETCH_ASSOC]
	 * @return array
	 */
	public function fetchRows($query, array $values, $fetchMode = PDO::FETCH_ASSOC) {
		$rows = array();
		try {
			$statement = $this->pdo->prepare($query);
			if ($statement->execute($values)) {
				$rows = $statement->fetchAll($fetchMode);
			} else {
				$errorInfo = $statement->errorInfo();
				//Logger::error($errorInfo[2], __CLASS__);
			}
		} catch (PDOException $e) {
			//Logger::error($e->getMessage(), __CLASS__);
		}
		return $rows;
	}
	
	
	/**
	 * Retrieve first column in results set
	 *
	 * @param string $query
	 * @param array $values
	 * @return string
	 */
	public function fetchColumn($query, array $values) {
		$col = null;
		try {
			$statement = $this->pdo->prepare($query);
			if ($statement->execute($values)) {
				$col = $statement->fetchColumn();
				if (!$col) return null;
			} else {
				$errorInfo = $statement->errorInfo();
				//Logger::error($errorInfo[2], __CLASS__);
			}
		} catch (PDOException $e) {
			//Logger::error($e->getMessage(), __CLASS__);
		}
		return $col;
	}
	
	/**
	 *
	 * @param string $query
	 * @param array $values
	 * @param boolean $lastInsId [default=true]
	 * @return int | boolean
	 */
	public function executeQuery($query, array $values, $lastInsId = true) {
		$result = false;
		try {
			$statement = $this->pdo->prepare($query);
			if ($statement->execute($values)) {
				if ($lastInsId) {
					$result = $this->pdo->lastInsertId();
				} else {
					$result = true;
				}
			} else {
				$this->transactionError = true;
				$errorInfo = $statement->errorInfo();
				//Logger::error($errorInfo[2], __CLASS__);
			}
		} catch (PDOException $e) {
			$this->transactionError = true;
			//Logger::error($e->getMessage(), __CLASS__);
		}
		return $result;
	}
	
	
}
?>