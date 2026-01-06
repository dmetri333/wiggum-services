<?php
namespace wiggum\services\db\connections;

use \PDO;
use \PDOException;
use \wiggum\services\db\Connection;
use \wiggum\services\db\Grammar;
use \wiggum\services\db\grammers\SqliteGrammar;

class Sqlite extends Connection {
	
	private $pdo;
	private $transactionError = false;

	/**
	 *
	 * @param array $config
	 * 
	 */
	public function connect(array $config) 
	{
		$pdo = null;
		try {
			$options = [
				PDO::ATTR_PERSISTENT => true,
				PDO::ATTR_EMULATE_PREPARES => false,
				PDO::ATTR_STRINGIFY_FETCHES => false
			];
			
			$url = $config['url'];
		
			$path = (is_string($url) && $url !== '') ? $url : ':memory:';
			$pdo = new PDO("sqlite:{$path}", null, null, $options);
		
		} catch (PDOException $e) {
			$pdo = null;
			
			throw new \wiggum\exceptions\InternalErrorException('Database failed to connect');
		}
		$this->pdo = $pdo;
	}

	/**
	 * @return Grammer
	 */
	public function getGrammar() : Grammar
	{
		return new SqliteGrammar();
	}

	/**
	 * 
	 * @return Ambigous <NULL, \PDO>
	 */
	public function getPDO()
	{
		return $this->pdo;
	}

	/**
	 * 
	 * @return bool
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
	 * @return bool
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
	 * @param bool $selfRollBack [default=false]
	 * 
	 * @return bool
	 */
	public function doCommit(bool $selfRollBack = false) {
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
	 * 
	 * @return object
	 */
	public function fetchObject(string $query, array $values, $instance) : ?object
	{
		$obj = null;
		try {
			$statement = $this->pdo->prepare($query);
			$statement->setFetchMode(PDO::FETCH_INTO, $instance);

			foreach ($values as $key => &$val) {
			    if (is_int($val)) {
			        $statement->bindParam($key+1, $val, PDO::PARAM_INT);
			    } else {
			        $statement->bindParam($key+1, $val);
			    }
			}

			if ($statement->execute()) {
				$obj = $statement->fetch(PDO::FETCH_INTO);
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
	 * 
	 * @return array
	 */
	public function fetchObjects(string $query, array $values, $instance) : array 
	{

		$objects = [];
		try {
			$statement = $this->pdo->prepare($query);
			$statement->setFetchMode(PDO::FETCH_INTO, $instance);

			foreach ($values as $key => &$val) {
			    if (is_int($val)) {
			        $statement->bindParam($key+1, $val, PDO::PARAM_INT);
			    } else {
			        $statement->bindParam($key+1, $val);
			    }
			}

			if ($statement->execute()) {
				while (($obj = $statement->fetch(PDO::FETCH_INTO))) {
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
	 * @param bool $assoc
	 * 
	 * @return array|object
	 */
	public function fetchRow(string $query, array $values, bool $assoc = false) 
	{

		$fetchMode = $assoc ? PDO::FETCH_ASSOC : PDO::FETCH_OBJ;

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
	 * @param bool $assoc
	 * 
	 * @return array
	 */
	public function fetchRows(string $query, array $values, bool $assoc = false) : array
	{
		
		$fetchMode = $assoc ? PDO::FETCH_ASSOC : PDO::FETCH_OBJ;

		$rows = [];
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
	 *
	 * @param string $query
	 * @param array $values
	 * @param bool $assoc
	 * 
	 * @return array
	 */
	public function fetchRowsWithColumnKey(string $query, array $values, bool $assoc = false) : array
	{
		
		if ($assoc) {
			$fetchMode = PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC;
		} else {
			$fetchMode = PDO::FETCH_UNIQUE|PDO::FETCH_OBJ;
		}
		
		$rows = [];
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
	 *
	 * @param string $query
	 * @param array $values
	 * 
	 * @return array
	 */
	public function fetchAllColumn(string $query, array $values) : array 
	{
		$rows = [];
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
				$rows = $statement->fetchAll(PDO::FETCH_COLUMN);
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
	 * 
	 * @return mixed
	 */
	public function fetchColumn($query, array $values)
	{
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
	 * 
	 * @return array
	 */
	public function fetchKeyValuePair(string $query, array $values) : array
	{
		$rows = [];
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
				$rows = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
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
	 *
	 * @param string $query
	 * @param array $values
	 * @param bool $lastInsId [default=true]
	 * 
	 * @return int | boolean
	 */
	public function executeQuery(string $query, array $values, bool $lastInsId = true) 
	{
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
