<?php
namespace wiggum\services\db;

use \PDO;
use \Closure;
use \InvalidArgumentException;

class Builder {
	
	
	//where($column, $operator = null, $value = null, $boolean = 'and')
	
	//orWhere
	
	//whereBetween($column, array $values, $boolean = 'and', $not = false)
	//orWhereBetween
	//whereNotBetween
	//orWhereNotBetween
	
	//whereNested($callback, $boolean = 'and')
	//addNestedWhereQuery
	
	//whereSub - hold
	
	//whereExists()
	//orWhereExists - delete
	//whereNotExists - delete
	//orWhereNotExists - delete
	
	//whereIn($column, $values, $boolean = 'and', $not = false)
	//orWhereIn
	//whereNotIn
	//orWhereNotIn
	
	//whereInSub - delete
	
	//whereNull()
	//orWhereNull
	//whereNotNull
	//orWhereNotNull
	
	//whereDate
	//whereDay
	//whereMonth
	//whereYear
	//whereTime
	
	protected $type;
	protected $db;
	protected $grammar;
	protected $bindings = [
			'select' => [],
			'join' => [],
			'where' => [],
			'having' => [],
			'order' => [],
			'inserts' => []
	];
	
	public $aggregate;
	public $columns;
	public $distinct = false;
	public $from;
	public $joins;
	public $wheres;
	public $groups;
	public $orders;
	public $limit;
	public $offset;
	public $lock;
	
	public $inserts;
	public $updates;
	
	
	public function __construct(DB $db, Grammar $grammar) {
		$this->db = $db;
		$this->grammar = $grammar;
	}
	
	//TODO simplify this and make a seprate method for batch inserts 
	/**
	 * 
	 * @param array $columns
	 * @return boolean|\wiggum\services\db\Builder
	 */
	public function insert(array $columns) {
		$this->type = 'insert';
	
		if (empty($columns)) return true;
	
		if (!is_array(reset($columns))) {
			$columns = [$columns];
		}
	
		foreach ($columns as $record) {
			foreach ($record as $column) {
				$this->addBinding($column, 'inserts');
			}
		}
	
		$this->inserts = $columns;
	
		return $this;
	}
	
	/**
	 * 
	 * @param array $values
	 * @return \wiggum\services\db\Builder
	 */
	public function update(array $values) {
		$this->type = 'update';
	
		$this->updates = $values;
	
		return $this;
	}
	
	/**
	 * 
	 * @param string $id
	 * @return \wiggum\services\db\Builder
	 */
	public function delete($id = null) {
		$this->type = 'delete';
	
		if (!is_null($id)) $this->where('id', '=', $id);
	
		return $this;
	}
	
	
	/**
	 * Set the columns to be selected.
	 *
	 * @param array $columns        	
	 * @return \wiggum\services\db\Builder
	 */
	public function select($columns = ['*']) {
		$this->type = 'select';
		
		$this->columns = is_array($columns) ? $columns : func_get_args();
		
		return $this;
	}
	
	/**
	 * Add a new select column to the query.
	 *
	 * @param  array|mixed  $column
	 * @return $this
	 */
	public function addSelect($column) {
	    $column = is_array($column) ? $column : func_get_args();
	    
	    $this->columns = array_merge((array) $this->columns, $column);
	    
	    return $this;
	}
	
	/**
	 * Force the query to only return distinct results.
	 *
	 * @return \wiggum\services\db\Builder
	 */
	public function distinct() {
		$this->distinct = true;
		
		return $this;
	}
	
	/**
	 * Set the table which the query is targeting.
	 *
	 * @param string $table        	
	 * @return \wiggum\services\db\Builder
	 */
	public function from($table) {
		$this->from = is_array($table) ? $table : [$table];
		
		return $this;
	}
	
	/**
	 * Add a join clause to the query.
	 *
	 * @param  string  $table
	 * @param  string  $firstColumn
	 * @param  string  $operator
	 * @param  string  $secondColumn
	 * @param  string  $type
	 * @param  bool    $where
	 * @return \wiggum\services\db\Builder
	 */
	public function join($table, $firstColumn, $operator = null, $secondColumn = null, $type = 'inner', $where = false) {
		
		if ($firstColumn instanceof Closure) {
			
			$this->joins[] = new JoinClause($type, $table);
			
			call_user_func($firstColumn, end($this->joins));
		
		} else {
			
			$join = new JoinClause($type, $table);
	
			$this->joins[] = $join->on($firstColumn, $operator, $secondColumn, 'and', $where);
		
		}
		
		return $this;
	}
	
	/**
	 * Add a "join where" clause to the query.
	 * 
	 * @param string $table
	 * @param string $firstColumn
	 * @param string $operator
	 * @param string $secondColumn
	 * @param string $type
	 * @return \wiggum\services\db\Builder
	 */
	public function joinWhere($table, $firstColumn, $operator, $value, $type = 'inner') {
		return $this->join($table, $firstColumn, $operator, $value, $type, true);
	}
	
	/**
	 * Add a left join to the query.
	 * 
	 * @param string $table
	 * @param string $firstColumn
	 * @param string $operator
	 * @param string $secondColumn
	 * @return \wiggum\services\db\Builder
	 */
	public function leftJoin($table, $firstColumn, $operator = null, $secondColumn = null) {
		return $this->join($table, $firstColumn, $operator, $secondColumn, 'left');
	}
	
	/**
	 * Add a "join where" clause to the query.
	 * 
	 * @param string $table
	 * @param string $firstColumn
	 * @param string $operator
	 * @param string $secondColumn
	 * @return \wiggum\services\db\Builder
	 */
	public function leftJoinWhere($table, $firstColumn, $operator, $value) {
		return $this->joinWhere($table, $firstColumn, $operator, $value, 'left');
	}
	
	/**
	 * Add a right join to the query.
	 * 
	 * @param string $table
	 * @param string $firstColumn
	 * @param string $operator
	 * @param string $secondColumn
	 * @return \wiggum\services\db\Builder
	 */
	public function rightJoin($table, $firstColumn, $operator = null, $secondColumn = null) {
		return $this->join($table, $firstColumn, $operator, $secondColumn, 'right');
	}
	
	/**
	 * Add a "right join where" clause to the query.
	 * 
	 * @param string $table
	 * @param string $firstColumn
	 * @param string $operator
	 * @param string $secondColumn
	 * @return \wiggum\services\db\Builder
	 */
	public function rightJoinWhere($table, $firstColumn, $operator, $value) {
		return $this->joinWhere($table, $firstColumn, $operator, $value, 'right');
	}
	
	/**
	 * Add a join clause manually
	 * 
	 * @param JoinClause $joinClause
	 * @return \wiggum\services\db\Builder
	 */
	public function addJoinClause(JoinClause $joinClause) {
	
		$this->joins[] = $joinClause;
	
		return $this;
	}
	
	/**
	 * 
	 * @param mixed $column
	 * @param string $operator
	 * @param string $value
	 * @param string $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function where($column, $operator = null, $value = null, $boolean = 'and') {
		// If the column is an array, we will assume it is an array of key-value pairs
		// and can add them each as a where clause. We will maintain the boolean we
		// received when the method was called and pass it into the nested where.
		if (is_array($column)) {
			foreach ($column as $key => $value) {
				$this->where($key, '=', $value, $boolean);
			}
			
			return $this;
		}
	
		// If the columns is actually a Closure instance, we will assume 
		// a nested where statement which is wrapped in parenthesis.
		if ($column instanceof Closure) {
			return $this->whereNested($column, $boolean);
		}
		
		// If the value is "null", we will just assume the developer wants to add a
		// where null clause to the query. So, we will allow a short-cut here to
		// that method for convenience so the developer doesn't have to check.
		if (is_null($value)) {
			return $this->whereNull($column, $boolean, is_null($operator) || $operator == '=' ? false : true);
		}
	
		// Now that we are working with just a simple query we can put the elements
		// in our array and add the query binding to our array of bindings that
		// will be bound to each SQL statements when it is finally executed.
		$type = 'Basic';
	
		$this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');
		$this->addBinding($value, 'where');
		
		return $this;
	}
	
	/**
	 * 
	 * @param Closure $callback
	 * @param string $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function whereNested(Closure $callback, $boolean = 'and') {
		$query = (new static($this->db, $this->grammar))->from($this->from);
	
		call_user_func($callback, $query);
	
		return $this->addNestedWhereQuery($query, $boolean);
	}
	
	/**
	 *
	 * @param  \wiggum\services\db\Builder $query
	 * @param  string  $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function addNestedWhereQuery($query, $boolean = 'and') {
		if (count($query->wheres)) {
			$type = 'Nested';
	
			$this->wheres[] = compact('type', 'query', 'boolean');
	
			$this->addBinding($query->getBindings(), 'where');
		}
	
		return $this;
	}
	
	/**
	 * 
	 * @param string $column
	 * @param string $operator
	 * @param string $value
	 * @return \wiggum\services\db\Builder
	 */
	public function orWhere($column, $operator = null, $value = null) {
	   return $this->where($column, $operator, $value, 'or');
	}
	
	/**
	 * 
	 * @param string $column
	 * @param array $values
	 * @param string $boolean
	 * @param string $not
	 * @return \wiggum\services\db\Builder
	 */
	public function whereBetween($column, array $values, $boolean = 'and', $not = false) {
		$type = 'between';
	
		$this->wheres[] = compact('type', 'column', 'boolean', 'not');
	
		$this->addBinding($values, 'where');
	
		return $this;
	}
	
	/**
	 *
	 * @param string $column
	 * @param array $values
	 * @return \wiggum\services\db\Builder
	 */
	public function orWhereBetween($column, array $values) {
	    return $this->whereBetween($column, $values, 'or');
	}
	
	/**
	 *
	 * @param string $column
	 * @param array $values
	 * @return \wiggum\services\db\Builder
	 */
	public function whereNotBetween($column, array $values, $boolean = 'and') {
	    return $this->whereBetween($column, $values, $boolean, true);
	}

	/**
	 *
	 * @param string $column
	 * @param array $values
	 * @return \wiggum\services\db\Builder
	 */
	public function orWhereNotBetween($column, array $values) {
	    return $this->whereBetween($column, $values, 'or', true);
	}
	
	/**
	 * 
	 * @param string $column
	 * @param array $values
	 * @param string $boolean
	 * @param string $not
	 * @return \wiggum\services\db\Builder
	 */
	public function whereIn($column, array $values, $boolean = 'and', $not = false) {
		$type = 'In';
	
		$this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');
	
		$this->addBinding($values, 'where');
	
		return $this;
	}
	
	/**
	 *
	 * @param string $column
	 * @param array $values
	 * @return \wiggum\services\db\Builder
	 */
	public function orWhereIn($column, array $values) {
	    return $this->whereIn($column, $values, 'or');
	}
	
	/**
	 *
	 * @param string $column
	 * @param array $values
	 * @param string $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function whereNotIn($column, array $values, $boolean = 'and') {
	    return $this->whereIn($column, $values, $boolean, true);
	}
	
	/**
	 *
	 * @param string $column
	 * @param array $values
	 * @return \wiggum\services\db\Builder
	 */
	public function orWhereNotIn($column, array $values) {
	    return $this->whereIn($column, $values, 'or', true);
	}
	
	/**
	 * 
	 * @param string $column
	 * @param string $boolean
	 * @param string $not
	 * @return \wiggum\services\db\Builder
	 */
	public function whereNull($column, $boolean = 'and', $not = false) {
		$type = 'Null';
	
		$this->wheres[] = compact('type', 'column', 'boolean', 'not');
	
		return $this;
	}
	
	/**
	 *
	 * @param string $column
	 * @return \wiggum\services\db\Builder
	 */
	public function orWhereNull($column) {
	    return $this->whereNull($column, 'or');
	}
	
	/**
	 *
	 * @param string $column
	 * @param string $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function whereNotNull($column, $boolean = 'and') {
	    return $this->whereNull($column, $boolean, true);
	}
	
	/**
	 *
	 * @param string $column
	 * @return \wiggum\services\db\Builder
	 */
	public function orWhereNotNull($column) {
	    return $this->whereNull($column, 'or', true);
	}
	
	/**
	 * 
	 * @param string $column
	 * @param string $operator
	 * @param string $value
	 * @param string $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function whereDate($column, $operator, $value, $boolean = 'and')
	{
	    return $this->addDateBasedWhere('Date', $column, $operator, $value, $boolean);
	}
	
	/**
	 * 
	 * @param string $column
	 * @param string $operator
	 * @param string $value
	 * @param string $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function whereDay($column, $operator, $value, $boolean = 'and')
	{
	    return $this->addDateBasedWhere('Day', $column, $operator, $value, $boolean);
	}
	
	/**
	 * 
	 * @param string $column
	 * @param string $operator
	 * @param string $value
	 * @param string $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function whereMonth($column, $operator, $value, $boolean = 'and')
	{
	    return $this->addDateBasedWhere('Month', $column, $operator, $value, $boolean);
	}
	
	/**
	 * 
	 * @param string $column
	 * @param string $operator
	 * @param string $value
	 * @param string $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function whereYear($column, $operator, $value, $boolean = 'and')
	{
	    return $this->addDateBasedWhere('Year', $column, $operator, $value, $boolean);
	}
	
	/**
	 * Add a "where time" statement to the query.
	 *
	 * @param  string  $column
	 * @param  string   $operator
	 * @param  \DateTimeInterface|string  $value
	 * @param  string   $boolean
	 * @return \wiggum\services\db\Builder
	 */
	public function whereTime($column, $operator, $value = null, $boolean = 'and')
	{
	    return $this->addDateBasedWhere('Time', $column, $operator, $value, $boolean);
	}
	
	/**
	 * Add a date based (year, month, day) statement to the query.
	 *
	 * @param  string  $type
	 * @param  string  $column
	 * @param  string  $operator
	 * @param  int  $value
	 * @param  string  $boolean
	 * @return $this
	 */
	protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and')
	{
	    $this->wheres[] = compact('column', 'type', 'boolean', 'operator', 'value');
	    $this->addBinding($value, 'where');
	    return $this;
	}
	
	/**
	 * Add a "where JSON contains" clause to the query.
	 *
	 * @param  string  $column
	 * @param  mixed  $value
	 * @param  string  $boolean
	 * @param  bool  $not
	 * @return $this
	 */
	public function whereJsonContains($column, $value, $boolean = 'and', $not = false)
	{
	    $type = 'JsonContains';
	    
	    $this->wheres[] = compact('type', 'column', 'value', 'boolean', 'not');
	    
	    $this->addBinding(json_encode($value), 'where');

	    return $this;
	}
	/**
	 * Add a "or where JSON contains" clause to the query.
	 *
	 * @param  string  $column
	 * @param  mixed  $value
	 * @return $this
	 */
	public function orWhereJsonContains($column, $value)
	{
	    return $this->whereJsonContains($column, $value, 'or');
	}
	/**
	 * Add a "where JSON not contains" clause to the query.
	 *
	 * @param  string  $column
	 * @param  mixed  $value
	 * @param  string  $boolean
	 * @return $this
	 */
	public function whereJsonDoesntContain($column, $value, $boolean = 'and')
	{
	    return $this->whereJsonContains($column, $value, $boolean, true);
	}
	/**
	 * Add a "or where JSON not contains" clause to the query.
	 *
	 * @param  string  $column
	 * @param  mixed  $value
	 * @return $this
	 */
	public function orWhereJsonDoesntContain($column, $value)
	{
	    return $this->whereJsonDoesntContain($column, $value, 'or');
	}
	
	/**
     * Add a "where JSON length" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereJsonLength($column, $operator, $value = null, $boolean = 'and')
    {
        $type = 'JsonLength';
		
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');
		
		$this->addBinding($value, 'where');
        
        return $this;
	}

	  /**
     * Add a "or where JSON length" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function orWhereJsonLength($column, $operator, $value = null)
    {
        return $this->whereJsonLength($column, $operator, $value, 'or');
    }
	
	/**
	 *
	 * @param  array|string $column,...
	 * @return $this
	 */
	public function groupBy() {
		foreach (func_get_args() as $arg) {
			$this->groups = array_merge((array) $this->groups, is_array($arg) ? $arg : [$arg]);
		}
	
		return $this;
	}
	
	/**
	 * 
	 * @param string $column
	 * @param string $direction
	 * @return \wiggum\services\db\Builder
	 */
	public function orderBy($column, $direction = 'asc') {
		$direction = strtolower($direction) == 'asc' ? 'asc' : 'desc';
	
		$this->orders[] = compact('column', 'direction');
	
		return $this;
	}
	
	/**
	 * 
	 * @param int $value
	 * @param int $offset
	 * @return \wiggum\services\db\Builder
	 */
	public function limit($value, $offset = null) {
		if ($value > 0) 
			$this->limit = $value;
	
		if (isset($offset)) 
			$this->offset($offset);
		
		return $this;
	}
	
	/**
	 * 
	 * @param int $value
	 * @return \wiggum\services\db\Builder
	 */
	public function offset($value) {
		$this->offset = max(0, $value);

		return $this;
	}
	
	/**
	 * Get the SQL representation of the query.
	 *
	 * @return string
	 */
	public function toSql() {
		
		if ($this->type == 'insert') {
			return $this->grammar->compileInsert($this);
		} else if ($this->type == 'update') {
			return $this->grammar->compileUpdate($this);
		} else if ($this->type == 'delete') {
			return $this->grammar->compileDelete($this);
		} else {
			return $this->grammar->compileSelect($this);
		}
		
	}
	
	/**
	 * 
	 * @param boolean $assoc
	 */
	public function fetchRow($assoc = false) {
		if ($assoc) {
			return $this->db->fetchRow($this->toSql(), $this->getBindings());
		} else {
			return $this->db->fetchRow($this->toSql(), $this->getBindings(), PDO::FETCH_OBJ);
		}
	}
	
	/**
	 * 
	 * @param boolean $assoc
	 */
	public function fetchRows($assoc = false) {
		if ($assoc) {
			return $this->db->fetchRows($this->toSql(), $this->getBindings());
		} else {
			return $this->db->fetchRows($this->toSql(), $this->getBindings(), PDO::FETCH_OBJ);
		}
	}
	
	/**
	 * Fetch all values of a single column from the result set into an array.
	 *
	 * @return array
	 */
	public function fetchAllColumn() {
		return $this->db->fetchRows($this->toSql(), $this->getBindings(), PDO::FETCH_COLUMN);
	}
	
	/**
	 * Fetch a single value from first column in the next row of a result set.
	 *
	 * @return string
	 */
	public function fetchValue() {
		return $this->db->fetchColumn($this->toSql(), $this->getBindings());
	}
	
	/**
	 * 
	 * @param string $columnKey
	 * @param boolean $assoc
	 */
	public function fetchRowsWithColumnKey($columnKey = 'id', $assoc = true) {

		if (!is_array($this->columns) || count($this->columns) <= 0 || $this->columns[0] == '*') {
			$this->columns = [$columnKey];
		}
		
		array_unshift($this->columns, $columnKey);
		
		if ($assoc) {
			return $this->db->fetchRows($this->toSql(), $this->getBindings(), PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);
		} else {
			return $this->db->fetchRows($this->toSql(), $this->getBindings(), PDO::FETCH_UNIQUE|PDO::FETCH_OBJ);
		}
	}
	
	/**
	 * 
	 * @param string $keyColumn
	 * @param string $valueColumn
	 */
	public function fetchKeyValuePair($keyColumn, $valueColumn) {
	
		$this->columns = [$keyColumn, $valueColumn];
	
		return $this->db->fetchRows($this->toSql(), $this->getBindings(), PDO::FETCH_KEY_PAIR);
	}
	
	/**
	 * 
	 * @param object $instance
	 */
	public function fetchObject($instance) {
		return $this->db->fetchObject($this->toSql(), $this->getBindings(), $instance);
	}
	
	/**
	 * 
	 * @param object $instance
	 */
	public function fetchObjects($instance) {
		return $this->db->fetchObjects($this->toSql(), $this->getBindings(), $instance);
	}
	
/*	public function fetchObjectsWithColumnKey($instance, $columnKey = 'id') {
		
		if (!is_array($this->columns) || count($this->columns) <= 0 || $this->columns[0] == '*') {
			$this->columns = [$columnKey];
		}
		
		array_unshift($this->columns, $columnKey);
		
		return array_map('reset', $this->db->fetchObjects($this->toSql(), $this->getBindings(), $instance, PDO::FETCH_GROUP|PDO::FETCH_INTO));
	}
*/
	
	/**
	 * 
	 * @param boolean $lastInsId
	 */
	public function execute($lastInsId = false) {
	
		if ($this->type == 'insert') {
				
			$sql = $this->grammar->compileInsert($this);
			return $this->db->executeQuery($sql, $this->getBindings(), $lastInsId);
				
		} else if ($this->type == 'update') {
				
			$sql = $this->grammar->compileUpdate($this);
			//bindings done here to make sure the order is correct.
			$bindings = array_values(array_merge($this->updates, $this->getBindings()));
			return $this->db->executeQuery($sql, $bindings, false);
				
		} else if ($this->type == 'delete') {
			
			$sql = $this->grammar->compileDelete($this);
			return $this->db->executeQuery($sql, $this->getBindings(), false);
		
		}
	
	}
	
	/**
	 * 
	 * @param string $columns
	 * @return number
	 */
	public function count($columns = '*') {
		if (!is_array($columns)) {
			$columns = [$columns];
		}
	
		return (int) $this->aggregate('count', $columns);
	}
	
	/**
	 * 
	 * @param string $column
	 * @return integer
	 */
	public function sum($column) {
		$result = $this->aggregate('sum', [$column]);
	
		return $result ?: 0;
	}
	
	/**
	 * 
	 * @param string $column
	 * @return integer
	 */
	public function min($column) {
		return $this->aggregate('min', [$column]);
	}
	
	/**
	 * 
	 * @param string $column
	 * @return integer
	 */
	public function max($column) {
		return $this->aggregate('max', [$column]);
	}
	
	/**
	 * 
	 * @param string $column
	 * @return integer
	 */
	public function avg($column) {
		return $this->aggregate('avg', [$column]);
	}
	
	/**
	 * 
	 * @param string $function
	 * @param array $columns
	 * @return integer
	 */
	public function aggregate($function, $columns = ['*']) {
		$this->aggregate = compact('function', 'columns');
	
		// We will also back up the columns and select bindings since the 
		// select clause will be removed when performing the aggregate function. 
		$previousColumns = $this->columns;
		$previousSelectBindings = $this->bindings['select'];
		
		$this->bindings['select'] = [];
		$result = $this->fetchRow(true);
		
		// Once we have executed the query, we will reset the aggregate property so
		// that more select queries can be executed against the database.
		$this->aggregate = null;
		$this->columns = $previousColumns;
		$this->bindings['select'] = $previousSelectBindings;
	
		if (isset($result)) {
			return $result['aggregate'];
		}
	}
	
	/**
	 * 
	 * @return array
	 */
	public function getColumnListing() {
		$sql = $this->grammar->compileColumnExists();
		
		return $this->db->fetchRows($sql, [$this->from[0]], PDO::FETCH_COLUMN);
	}
	
	/**
	 * Add a binding to the query.
	 *
	 * @param  mixed   $value
	 * @param  string  $type
	 * @return $this
	 * 
	 * @throws \InvalidArgumentException
	 */
	public function addBinding($value, $type) {
		if (!array_key_exists($type, $this->bindings)) {
			throw new InvalidArgumentException("Invalid binding type: {$type}.");
		}
		
		//TODO - add bindings flat
		if (is_array($value)) {
			$this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
		} else {
			$this->bindings[$type][] = $value;
		}
	
		return $this;
	}
	
	/**
	 * 
	 * @param array $bindings
	 * @param string $type
	 * @throws InvalidArgumentException
	 * @return \wiggum\services\db\Builder
	 */
	public function setBindings(array $bindings, $type) {
		if (!array_key_exists($type, $this->bindings)) {
			throw new InvalidArgumentException("Invalid binding type: {$type}.");
		}
	
		$this->bindings[$type] = $bindings;
	
		return $this;
	}
	
	/**
	 * 
	 * @return multitype:unknown
	 */
	public function getBindings() {
		return $this->flattenArray($this->bindings);
	}
	
	//TODO - remove once bindings are added flat
	/**
	 * 
	 * @param array $array
	 * @return multitype:unknown
	 */
	private function flattenArray($array) {
		$return = [];
	
		array_walk_recursive($array, function($x) use (&$return) { $return[] = $x; });
	
		return $return;
	}
	
}
