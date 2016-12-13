<?php
namespace wiggum\services\db;

class Grammar {
	
	protected $selectComponents = array (
			'aggregate',
			'columns',
			'from',
			'joins',
			'wheres',
			'groups',
			'orders',
            'limit',
            'offset'
	);
	
	/**
	 * Compile a select query into SQL.
	 * 
	 * @param Builder $query
	 * @return string
	 */
	public function compileSelect(Builder $query) {
		if (is_null($query->columns)) $query->columns = array('*');
		
		return trim(implode(' ', $this->compileComponents($query)));
	}
	
	/**
	 * Compile the components necessary for a select clause.
	 *
	 * @param Builder $query
	 * @return array
	 */
	protected function compileComponents(Builder $query) {
		$sql = array();
		
		foreach ($this->selectComponents as $component) {
			if (!is_null($query->$component)) {
				$method = 'compile' . ucfirst($component);
				
				$sql[$component] = $this->$method($query, $query->$component);
			}
		}
		
		return $sql;
	}
	
	/**
	 * 
	 * @param Builder $query
	 * @param array $aggregate
	 * @return string
	 */
	protected function compileAggregate(Builder $query, $aggregate) {
		
		$column = $this->columnize($aggregate['columns']);
		
		if ($query->distinct && $column !== '*') {
			$column = 'distinct '.$column;
		}
		
		return 'select '.$aggregate['function'].'('.$column.') as aggregate';
	}
	
	/**
	 * Compile the "select *" portion of the query.
	 *
	 * @param Builder $query       	
	 * @param array $columns        	
	 * @return string
	 */
	protected function compileColumns(Builder $query, $columns) {
		
		// If the query is performing an aggregating select, we will let that
		// compiler handle the building of the select clauses.
		if (!is_null($query->aggregate)) {
			return;
		}
		
		$select = $query->distinct ? 'select distinct ' : 'select ';
		
		return $select.$this->columnize($columns);
	}
	
	/**
	 * Compile the "from" portion of the query.
	 *
	 * @param Builder $query        	
	 * @param string $tables
	 * @return string
	 */
	protected function compileFrom(Builder $query, $tables) {
		return 'from ' . implode(', ', $this->wrapArray($tables));
	}
	
	/**
	 * Compile the "join" portions of the query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $joins
	 * @return string
	 */
	protected function compileJoins(Builder $query, $joins) {
		$sql = array();
	
		$query->setBindings(array(), 'join');

		foreach ($joins as $join) {
			$table = $this->wrap($join->table);
	
			// First we need to build all of the "on" clauses for the join. There may be many
			// of these clauses so we will need to iterate through each one and build them
			// separately, then we'll join them up into a single string when we're done.
			$clauses = array();
	
			foreach ($join->clauses as $clause) {
				$clauses[] = $this->compileJoinConstraint($clause);
			}
	
			foreach ($join->bindings as $binding) {
				$query->addBinding($binding, 'join');
			}
	
			// Once we have constructed the clauses, we'll need to take the boolean connector
			// off of the first clause as it obviously will not be required on that clause
			// because it leads the rest of the clauses, thus not requiring any boolean.
			$clauses[0] = $this->removeLeadingBoolean($clauses[0]);
	
			$clauses = implode(' ', $clauses);
	
			$type = $join->type;
	
			// Once we have everything ready to go, we will just concatenate all the parts to
			// build the final join statement SQL for the query and we can then return the
			// final clause back to the callers as a single, stringified join statement.
			$sql[] = "$type join $table on $clauses";
		}
	
		return implode(' ', $sql);
	}
	
	/**
	 * Create a join clause constraint segment.
	 *
	 * @param  array   $clause
	 * @return string
	 */
	protected function compileJoinConstraint(array $clause) {
		$firstColumn = $this->wrap($clause['firstColumn']);

		if ($clause['where']) {
			if ($clause['operator'] === 'in' || $clause['operator'] === 'not in') {
				$secondColumn = '('.implode(', ', array_fill(0, $clause['secondColumn'], '?')).')';
			} else {
				$secondColumn = '?';
			}
		} else {
			$secondColumn = $this->wrap($clause['secondColumn']);
		}
		
		
		return "{$clause['boolean']} $firstColumn {$clause['operator']} $secondColumn";
	}
	
	/**
	 * Compile the "where" portions of the query.
	 *
	 * @param  Builder $query
	 * @return string
	 */
	protected function compileWheres(Builder $query) {
		$sql = array();

		if (is_null($query->wheres)) return '';
	
		// Each type of where clauses has its own compiler function which is responsible
		// for actually creating the where clauses SQL. This helps keep the code nice
		// and maintainable since each clause has a very small method that it uses.
		foreach ($query->wheres as $where) {
			$method = "where{$where['type']}";
	
			$sql[] = $where['boolean'].' '.$this->$method($query, $where);
		}
	
		// If we actually have some where clauses, we will strip off the first boolean
		// operator, which is added by the query builders for convenience so we can
		// avoid checking for the first clauses in each of the compilers methods.
		if (count($sql) > 0) {
			$sql = implode(' ', $sql);
	
			return 'where '.$this->removeLeadingBoolean($sql);
		}
	
		return '';
	}
	
	/**
	 * 
	 * @param Builder $query
	 * @param array $where
	 * @return string
	 */
	protected function whereNested(Builder $query, $where) {
		$nested = $where['query'];
	
		return '('.substr($this->compileWheres($nested), 6).')';
	}
	
	/**
	 * Compile a basic where clause.
	 *
	 * @param Builder $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereBasic(Builder $query, $where) {
		return $this->wrap($where['column']).' '.$where['operator'].' ?';
	}
	
	/**
	 * Compile a "between" where clause.
	 *
	 * @param  Builder $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereBetween(Builder $query, $where) {
		$between = $where['not'] ? 'not between' : 'between';
	
		return $this->wrap($where['column']).' '.$between.' ? and ?';
	}
	
	/**
	 * Compile a "where in" clause.
	 *
	 * @param  Builder $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereIn(Builder $query, $where) {
		$in = $where['not'] ? 'not in' : 'in';
		
		if (empty($where['values'])) return '0 = 1';
	
		$values = $this->parameterize($where['values']);
	
		return $this->wrap($where['column']).' '.$in.' ('.$values.')';
	}
	
	/**
	 * Compile a "where null" clause.
	 *
	 * @param  Builder $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereNull(Builder $query, $where) {
		$null = $where['not'] ? 'not null' : 'null';
		
		return $this->wrap($where['column']).' is '.$null;
	}
	
	/**
	 * Compile the "group by" portions of the query.
	 *
	 * @param  Builder $query
	 * @param  array  $groups
	 * @return string
	 */
	protected function compileGroups(Builder $query, $groups) {
		return 'group by '.$this->columnize($groups);
	}
	
	/**
	 * Compile the "order by" portions of the query.
	 *
	 * @param  Builder $query
	 * @param  array  $orders
	 * @return string
	 */
	protected function compileOrders(Builder $query, $orders) {
		return 'order by '.implode(', ', array_map(function($order) {
			return $this->wrap($order['column']).' '.$order['direction'];
		}, $orders));
	}
	
	/**
	 * Compile the "limit" portions of the query.
	 *
	 * @param  Builder $query
	 * @param  int  $limit
	 * @return string
	 */
	protected function compileLimit(Builder $query, $limit) {
		return 'limit '.(int) $limit;
	}
	
	/**
	 * Compile the "offset" portions of the query.
	 *
	 * @param  Builder $query
	 * @param  int  $offset
	 * @return string
	 */
	protected function compileOffset(Builder $query, $offset) {
		return 'offset '.(int) $offset;
	}
	
	/**
	 *
	 * @param Builder $query
	 * @param unknown $value
	 * @return string
	 */
	protected function compileLock(Builder $query, $value) {
		if (is_string($value)) {
			return $value;
		}
		return $value ? 'for update' : 'lock in share mode';
	}
	
	/**
	 * Compile an insert statement into SQL.
	 *
	 * @param  Builder $query
	 * @param  array  $values
	 * @return string
	 */
	public function compileInsert(Builder $query) {
		$table = $this->wrap($query->from[0]);
	
		$columns = $this->columnize(array_keys(reset($query->inserts)));
		
		// We need to build a list of parameter place-holders of values that are bound
		// to the query. Each insert should have the exact same amount of parameter
		// bindings so we can just go off the first list of values in this array.
		$parameters = $this->parameterize(reset($query->inserts));
		
		$value = array_fill(0, count($query->inserts), "($parameters)");
	
		$parameters = implode(', ', $value);
	
		return "insert into $table ($columns) values $parameters";
	}
	
	/**
	 * Compile an update statement into SQL.
	 *
	 * @param  Builder $query
	 * @param  array  $values
	 * @return string
	 */
	public function compileUpdate(Builder $query) {
		$table = $this->wrap($query->from[0]);
	
		// Each one of the columns in the update statements needs to be wrapped in the
		// keyword identifiers, also a place-holder needs to be created for each of
		// the values in the list of bindings so we can make the sets statements.
		$columns = array();
	
		foreach ($query->updates as $key => $value) {
			$columns[] = $this->wrap($key).' = ?';
		}
	
		$columns = implode(', ', $columns);
	
		$joins = isset($query->joins) ? ' '.$this->compileJoins($query, $query->joins) : '';

		// Of course, update queries may also be constrained by where clauses so we'll
		// need to compile the where clauses and attach it to the query so only the
		// intended records are updated by the SQL statements we generate to run.
		$where = $this->compileWheres($query);
	
		$sql = trim("update {$table}{$joins} set {$columns} {$where}");
		
		if (isset($query->orders)) {
			$sql .= ' '.$this->compileOrders($query, $query->orders);
		}
		if (isset($query->limit)) {
			$sql .= ' '.$this->compileLimit($query, $query->limit);
		}
		
		return trim($sql);
	}
	
	/**
	 * Compile a delete statement into SQL.
	 *
	 * @param  Builder $query
	 * @return string
	 */
	public function compileDelete(Builder $query) {
		$table = $this->wrap($query->from[0]);
	
		$where = $this->compileWheres($query);
	
		if (isset($query->joins)) {
			$joins = ' '.$this->compileJoins($query, $query->joins);

			$sql = trim("delete $table from {$table}{$joins} $where");
		
		} else {
			$sql = trim("delete from $table $where");
			
			if (isset($query->orders)) {
				$sql .= ' '.$this->compileOrders($query, $query->orders);
			}
			
			if (isset($query->limit)) {
				$sql .= ' '.$this->compileLimit($query, $query->limit);
			}
		}
		return $sql;
		
		
	}
	
	/**
	 * Compile the query to determine the list of columns.
	 *
	 * @return string
	 */
	public function compileColumnExists() {
		return 'select column_name from information_schema.columns where table_name = ?';
	}

	// Helper methods
	
	/**
	 * Wrap a value in keyword identifiers.
	 *
	 * @param  string  $value
	 * @return string
	 */
	private function wrap($value) {

		if (strpos(strtolower($value), 'null') !== false) {
			return $value;
		}

		// If the value being wrapped has a column alias we will need to separate out
		// the pieces so we can wrap each of the segments of the expression on it
		// own, and then joins them both back together with the "as" connector.
		if (strpos(strtolower($value), ' as ') !== false) {
			$segments = explode(' ', $value);

			return $this->wrap($segments[0]).' as '.$this->wrapValue($segments[2]);
		}
	
		$wrapped = array();
	
		$segments = explode('.', $value);
	
		// If the value is not an aliased table expression, we'll just wrap it like
		// normal, so if there is more than one segment, we will wrap the first
		// segments as if it was a table and the rest as just regular values.
		foreach ($segments as $key => $segment) {
			$wrapped[] = $this->wrapValue($segment);
		}
	
		return implode('.', $wrapped);
	}
	
	private function wrapValue($value) {
		if ($value === '*') return $value;
	
		return '`'.$value.'`';
	}
	
	/**
	 * Create query parameter place-holders for an array.
	 *
	 * @param  array $values
	 * @return string
	 */
	private function parameterize(array $values) {
		return implode(', ', array_fill(0 , count($values), '?'));
	}
	
	/**
	 * Convert an array of column names into a delimited string.
	 *
	 * @param  array $columns
	 * @return string
	 */
	private function columnize(array $columns) {
		return implode(', ', array_map(array($this, 'wrap'), $columns));
	}
	
	/**
	 * Wrap an array of values.
	 *
	 * @param  array  $values
	 * @return array
	 */
	private function wrapArray(array $values) {
		return array_map(array($this, 'wrap'), $values);
	}
	
	/**
	 * Remove the leading boolean from a statement.
	 *
	 * @param string $value        	
	 * @return string
	 */
	private function removeLeadingBoolean($value) {
		return preg_replace('/and |or /', '', $value, 1);
	}
}
