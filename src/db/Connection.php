<?php
namespace wiggum\services\db;

use \wiggum\services\db\Grammar;

/**
 * @method bool beginTransaction()
 * @method bool doRollBack()
 * @method bool doCommit(bool $selfRollBack = false)
 * @method ?object fetchObject(string $query, array $values, object $instance)
 * @method array fetchObjects(string $query, array $values, object $instance)
 * @method array|object|null fetchRow(string $query, array $values, bool $assoc = false)
 * @method array fetchRows(string $query, array $values, bool $assoc = false)
 * @method array fetchAllColumn(string $query, array $values)
 * @method mixed fetchColumn(string $query, array $values)
 * @method array fetchRowsWithColumnKey(string $query, array $values, bool $assoc = false)
 * @method array fetchKeyValuePair(string $query, array $values)
 * @method int|string|bool executeQuery(string $query, array $values, bool $lastInsId = true)
 */
abstract class Connection {

    protected $prefix = '';

    public abstract function connect(array $config);
    public abstract function getGrammar() : Grammar;

    public function getPrefix() : string
    {
        return $this->prefix;
    }
}
