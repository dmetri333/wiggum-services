<?php
namespace wiggum\services\db;

use \PDO;
use \PDOException;
use \wiggum\services\db\grammers\MySqlGrammar;

class DB {
	
	private $pdo;
	private $transactionError = false;
	private $prefix = '';
	
	/**
	 * 
	 * @param array $config
	 */
	public function __construct($config) {
	    
	    if (!empty($config)) {
			$this->prefix = isset($config['prefix']) ? $config['prefix'] : '';

	        $port = isset($config['port']) ? $config['port'] : '3306';
	        $characterSet = isset($config['characterSet']) ? $config['characterSet'] : 'utf8';
	        
	        $this->connect($config['protocol'], $config['username'], $config['password'], $config['url'], $config['name'], $port, $characterSet);
	    }
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
	 * @return \wiggum\services\db\Builder
	 */
	public function table($table) {
		$query = new Builder($this, new MySqlGrammar());
		
		return $query->from($this->prefix.$table);
	}
	
	/**
	 *
	 * @param string $protocol
	 * @param string $user
	 * @param string $password
	 * @param string $url
	 * @param string $name
	 * @param string $port [3306]
	 * @param string $characterSet ['utf8']
	 * 
	 * @return /PDO
	 */
	public function connect($protocol, $user, $password, $url, $name, $port = 3306, $characterSet = 'utf8') {
		$pdo = null;
		try {
			$options = [PDO::ATTR_PERSISTENT => true];
			$pdo = new PDO("{$protocol}:host={$url};port={$port};dbname={$name}", $user, $password, $options);
			$pdo->exec('SET NAMES '.$characterSet);
		} catch (PDOException $e) {
			$pdo = null;
			
			throw new \wiggum\exceptions\InternalErrorException('Database failed to connect');
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
			error_log($e->getMessage());
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
			error_log($e->getMessage());
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
			error_log($e->getMessage());
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

			foreach ($values as $key => &$val) {
			    if (is_int($val)) {
			        $statement->bindParam($key+1, $val, PDO::PARAM_INT);
			    } else {
			        $statement->bindParam($key+1, $val);
			    }
			}

			if ($statement->execute()) {
				$obj = $statement->fetch($fetchMode);
				if (!$obj) return null;
			} else {
				$errorInfo = $statement->errorInfo();
				error_log($errorInfo[2]);
			}
		} catch (PDOException $e) {
			error_log($e->getMessage());
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

			foreach ($values as $key => &$val) {
			    if (is_int($val)) {
			        $statement->bindParam($key+1, $val, PDO::PARAM_INT);
			    } else {
			        $statement->bindParam($key+1, $val);
			    }
			}

			if ($statement->execute()) {
				while (($obj = $statement->fetch($fetchMode))) {
					if (isset($obj))
						$objects[] = clone $obj;
				}
			} else {
				$errorInfo = $statement->errorInfo();
				error_log($errorInfo[2]);
			}
		} catch (PDOException $e) {
			error_log($e->getMessage());
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
			
			foreach ($values as $key => &$val) {
			    if (is_int($val)) {
			        $statement->bindParam($key+1, $val, PDO::PARAM_INT);
			    } else {
			        $statement->bindParam($key+1, $val);
			    }
			}

			if ($statement->execute()) {
				$row = $statement->fetch($fetchMode);
				if ($row === false) $row = null;
			} else {
				$errorInfo = $statement->errorInfo();
				error_log($errorInfo[2]);
			}
		} catch (PDOException $e) {
			error_log($e->getMessage());
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

			foreach ($values as $key => &$val) {
			    if (is_int($val)) {
			        $statement->bindParam($key+1, $val, PDO::PARAM_INT);
			    } else {
			        $statement->bindParam($key+1, $val);
			    }
			}

			if ($statement->execute()) {
				$rows = $statement->fetchAll($fetchMode);
			} else {
				$errorInfo = $statement->errorInfo();
				error_log($errorInfo[2]);
			}
		} catch (PDOException $e) {
			error_log($e->getMessage());
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

			foreach ($values as $key => &$val) {
			    if (is_int($val)) {
			        $statement->bindParam($key+1, $val, PDO::PARAM_INT);
			    } else {
			        $statement->bindParam($key+1, $val);
			    }
			}

			if ($statement->execute()) {
				$col = $statement->fetchColumn();
				if (!$col) return null;
			} else {
				$errorInfo = $statement->errorInfo();
				error_log($errorInfo[2]);
			}
		} catch (PDOException $e) {
			error_log($e->getMessage());
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

			foreach ($values as $key => &$val) {
			    if (is_int($val)) {
			        $statement->bindParam($key+1, $val, PDO::PARAM_INT);
			    } else {
			        $statement->bindParam($key+1, $val);
			    }
			}
			
			if ($statement->execute()) {
				if ($lastInsId) {
					$result = $this->pdo->lastInsertId();
				} else {
					$result = true;
				}
			} else {
				$this->transactionError = true;
				$errorInfo = $statement->errorInfo();
				error_log($errorInfo[2]);
			}
		} catch (PDOException $e) {
			$this->transactionError = true;
			error_log($e->getMessage());
		}
		return $result;
	}
	
	
}
