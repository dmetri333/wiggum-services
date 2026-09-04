<?php
namespace wiggum\services\db;

use \PDO;
use \PDOStatement;
use \wiggum\services\db\Grammar;

/**
 * @method bool beginTransaction()
 * @method bool doRollBack()
 * @method bool doCommit(bool $selfRollBack = false)
 * @method ?object fetchObject(string $query, array $values, object $instance)
 * @method array fetchObjects(string $query, array $values, object $instance)
 * @method array|object|null fetchRow(string $query, array $values, bool|int $fetchMode = false)
 * @method array fetchRows(string $query, array $values, bool|int $fetchMode = false)
 * @method array fetchAllColumn(string $query, array $values)
 * @method mixed fetchColumn(string $query, array $values)
 * @method array fetchKeyValuePair(string $query, array $values)
 * @method ExecutionResult executeQuery(string $query, array $values, bool $captureLastInsertId = false)
 */
abstract class Connection {

    protected $prefix = '';

    public abstract function connect(array $config);
    public abstract function getGrammar() : Grammar;

    public function getPrefix() : string
    {
        return $this->prefix;
    }

    /**
     * Resolve the boolean shorthand while allowing native PDO fetch modes.
     */
    protected function resolveFetchMode(bool|int $fetchMode): int
    {
        if (is_bool($fetchMode)) {
            return $fetchMode ? PDO::FETCH_ASSOC : PDO::FETCH_OBJ;
        }

        return $fetchMode;
    }

    /**
     * Bind query values immediately rather than retaining variable references.
     */
    protected function bindValues(PDOStatement $statement, array $values): void
    {
        foreach ($values as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : $key;

            $dataType = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default       => PDO::PARAM_STR,
            };

            $statement->bindValue($parameter, $value, $dataType);
        }
    }

}
