<?php
namespace wiggum\services\db;

class JoinClause {
	
	public $type;
	public $table;
	public $clauses = [];
	public $bindings = [];
	
	/**
	 * Create a new join clause instance.
	 *
	 * @param string $type        	
	 * @param string $table        	
	 * @return void
	 */
	public function __construct($type, $table) {
		$this->type = $type;
		$this->table = $table;
	}
	
	/**
	 * Add an "on" clause to the join.
	 *
	 * @param string $first        	
	 * @param string $operator        	
	 * @param string $second        	
	 * @param string $boolean        	
	 * @param bool $where        	
	 * @return \wiggum\services\db\JoinClause
	 */
	public function on($firstColumn, $operator, $secondColumn, $boolean = 'and', $where = false) {
		
		if ($where)
			$this->bindings[] = $secondColumn;
		
		if ($where && ($operator === 'in' || $operator === 'not in') && is_array($secondColumn)) {
			$secondColumn = count($secondColumn);
		}
		
		$this->clauses[] = compact('firstColumn', 'operator', 'secondColumn', 'boolean', 'where');
		
		return $this;
	}
	
	/**
	 * 
	 * @param string $first
	 * @param string $operator
	 * @param string $second
	 * @return \wiggum\services\db\JoinClause
	 */
	public function orOn($first, $operator, $second) {
	    return $this->on($first, $operator, $second, 'or');
	}
	
	/**
	 * Add an "on where" clause to the join.
	 * 
	 * @param string $firstColumn
	 * @param string $operator
	 * @param string $second
	 * @param string $boolean
	 * @return \wiggum\services\db\JoinClause
	 */
	public function where($firstColumn, $operator, $secondColumn, $boolean = 'and') {
		return $this->on($firstColumn, $operator, $secondColumn, $boolean, true);
	}
	
	/**
	 * Add an "on where" clause to the join.
	 *
	 * @param string $firstColumn
	 * @param string $operator
	 * @param string $second
	 * @return \wiggum\services\db\JoinClause
	 */
	public function orWhere($firstColumn, $operator, $secondColumn) {
	    return $this->on($firstColumn, $operator, $secondColumn, 'or', true);
	}
	
	/**
	 * Add an "on where is null" clause to the join.
	 *
	 * @param string $column        	
	 * @param string $boolean        	
	 * @return \wiggum\services\db\JoinClause
	 */
	public function whereNull($column, $boolean = 'and', $not = false) {
		$null = $not ? 'not null' : 'null';
		
		return $this->on($column, 'is', $null, $boolean, false);
	}
	
	/**
	 * Add an "on where is null" clause to the join.
	 *
	 * @param string $column
	 * @param string $boolean
	 * @return \wiggum\services\db\JoinClause
	 */
	public function orWhereNull($column) {
	    return $this->whereNull($column, 'or');
	}
	
	/**
	 * Add an "on where is not null" clause to the join.
	 *
	 * @param string $column
	 * @param string $boolean
	 * @return \wiggum\services\db\JoinClause
	 */
	public function whereNotNull($column, $boolean = 'and') {
	    return $this->whereNull($column, $boolean, true);
	}
	
	/**
	 * Add an "on where is not null" clause to the join.
	 *
	 * @param string $column
	 * @param string $boolean
	 * @return \wiggum\services\db\JoinClause
	 */
	public function orWhereNotNull($column) {
	    return $this->whereNull($column, 'or', true);
	}
	
	/**
	 * Add an "on where in (...)" clause to the join.
	 *
	 * @param  string  $column
	 * @param  array  $values
	 * @return \wiggum\services\db\JoinClause
	 */
	public function whereIn($column, array $values, $boolean = 'and', $not = false) {
		$in = $not ? 'not in' : 'in';
		
		return $this->on($column, $in, $values, $boolean, true);
	}
	
	/**
	 * Add an "on where not in (...)" clause to the join.
	 *
	 * @param  string  $column
	 * @param  array  $values
	 * @return \wiggum\services\db\JoinClause
	 */
	public function orWhereIn($column, array $values) {
	    return $this->whereIn($column, $values, 'or');
	}
	
	/**
	 * Add an "on where not in (...)" clause to the join.
	 *
	 * @param  string  $column
	 * @param  array  $values
	 * @return \wiggum\services\db\JoinClause
	 */
	public function whereNotIn($column, array $values, $boolean = 'and') {
	    return $this->whereIn($column, $values, $boolean, true);
	}
	
	/**
	 * Add an "on where not in (...)" clause to the join.
	 *
	 * @param  string  $column
	 * @param  array  $values
	 * @return \wiggum\services\db\JoinClause
	 */
	public function orWhereNotIn($column, array $values) {
	    return $this->whereIn($column, $values, 'or', true);
	}
	
}