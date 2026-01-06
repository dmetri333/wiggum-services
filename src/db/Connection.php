<?php
namespace wiggum\services\db;

use \wiggum\services\db\Grammar;

abstract class Connection {

    protected $prefix = '';

    public abstract function connect(array $config);
    public abstract function getGrammar() : Grammar;
    public abstract function getConnection();

    public function getPrefix() : string
    {
        return $this->prefix;
    }
}
