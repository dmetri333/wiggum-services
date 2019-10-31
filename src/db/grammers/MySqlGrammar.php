<?php
namespace wiggum\services\db\grammers;

use wiggum\services\db\Grammar;
use wiggum\services\db\Builder;

class MySqlGrammar extends Grammar {
	
	protected $selectComponents = [
			'aggregate',
			'columns',
			'from',
			'joins',
			'wheres',
			'groups',
			'orders',
            'limit',
            'offset'
	];
	
	/**
	 * Compile a select query into SQL.
	 * 
	 * @param \wiggum\services\db\Builder $query
	 * @return string
	 */
	public function compileSelect(Builder $query) {
		if (is_null($query->columns)) $query->columns = ['*'];
		
		return trim(implode(' ', $this->compileComponents($query)));
	}
	
	/**
	 * Compile the components necessary for a select clause.
	 *
	 * @param \wiggum\services\db\Builder $query
	 * @return array
	 */
	protected function compileComponents(Builder $query) {
		$sql = [];
		
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
	 * @param \wiggum\services\db\Builder $query
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
	 * @param \wiggum\services\db\Builder $query       	
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
		
		return $select.$this->columnizeUnquote($columns);
	}
	
	/**
	 * Compile the "from" portion of the query.
	 *
	 * @param \wiggum\services\db\Builder $query        	
	 * @param string $tables
	 * @return string
	 */
	protected function compileFrom(Builder $query, $tables) {
		return 'from ' . implode(', ', $this->wrapArray($tables));
	}
	
	/**
	 * Compile the "join" portions of the query.
	 *
	 * @param  \wiggum\services\db\Builder  $query
	 * @param  array  $joins
	 * @return string
	 */
	protected function compileJoins(Builder $query, $joins) {
		$sql = [];
	
		$query->setBindings([], 'join');

		foreach ($joins as $join) {
			$table = $this->wrap($join->table);
	
			// First we need to build all of the "on" clauses for the join. There may be many
			// of these clauses so we will need to iterate through each one and build them
			// separately, then we'll join them up into a single string when we're done.
			$clauses = [];
	
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
	 * @param  \wiggum\services\db\Builder $query
	 * @return string
	 */
	protected function compileWheres(Builder $query) {
		$sql = array();

		if (is_null($query->wheres)) {
		    return '';
		}
	
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
	 * @param \wiggum\services\db\Builder $query
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
	 * @param \wiggum\services\db\Builder $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereBasic(Builder $query, $where) {
		return $this->wrap($where['column']).' '.$where['operator'].' ?';
	}
	
	/**
	 * Compile a "between" where clause.
	 *
	 * @param  \wiggum\services\db\Builder $query
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
	 * @param  \wiggum\services\db\Builder $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereIn(Builder $query, $where) {
		$in = $where['not'] ? 'not in' : 'in';
		
		if (empty($where['values'])) 
		    return $where['not'] ? '1 = 1' : '0 = 1';
	
		$values = $this->parameterize($where['values']);
	
		return $this->wrap($where['column']).' '.$in.' ('.$values.')';
	}
	
	/**
	 * Compile a "where null" clause.
	 *
	 * @param  \wiggum\services\db\Builder $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereNull(Builder $query, $where) {
		$null = $where['not'] ? 'not null' : 'null';
		
		return $this->wrap($where['column']).' is '.$null;
	}
	
	/**
	 * Compile a "where date" clause.
	 *
	 * @param  \wiggum\services\db\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereDate(Builder $query, $where) {
	    return $this->dateBasedWhere('date', $query, $where);
	}
	
	/**
	 * Compile a "where time" clause.
	 *
	 * @param  \wiggum\services\db\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereTime(Builder $query, $where) {
	    return $this->dateBasedWhere('time', $query, $where);
	}
	
	/**
	 * Compile a "where day" clause.
	 *
	 * @param  \wiggum\services\db\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereDay(Builder $query, $where) {
	    return $this->dateBasedWhere('day', $query, $where);
	}
	
	/**
	 * Compile a "where month" clause.
	 *
	 * @param  \wiggum\services\db\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereMonth(Builder $query, $where) {
	    return $this->dateBasedWhere('month', $query, $where);
	}
	
	/**
	 * Compile a "where year" clause.
	 *
	 * @param  \wiggum\services\db\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereYear(Builder $query, $where) {
	    return $this->dateBasedWhere('year', $query, $where);
	}
	
	/**
	 * Compile a date based where clause.
	 *
	 * @param  string  $type
	 * @param  \wiggum\services\db\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function dateBasedWhere($type, Builder $query, $where) {
	    return $type.'('.$this->wrap($where['column']).') '.$where['operator'].' ?';
	}
	
	/**
	 * Compile a "where JSON contains" clause.
	 *
	 * @param  \wiggum\services\db\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereJsonContains(Builder $query, $where)
	{
	    $value = '?';
	    $not = $where['not'] ? 'not ' : '';
	    
	    [$field, $path] = $this->wrapJsonFieldAndPath($where['column']);
	    
	    return $not.'json_contains('.$field.', '.$value.$path.')';
	}

	/**
     * Compile a "where JSON length" clause.
     *
     * @param  \wiggum\services\db\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereJsonLength(Builder $query, $where)
    {
		$value = '?';
		$operator = $where['operator'];

		[$field, $path] = $this->wrapJsonFieldAndPath($where['column']);
        return 'json_length('.$field.$path.') '.$operator.' '.$value;
    }
	
	/**
	 * Compile the "group by" portions of the query.
	 *
	 * @param  \wiggum\services\db\Builder $query
	 * @param  array  $groups
	 * @return string
	 */
	protected function compileGroups(Builder $query, $groups) {
		return 'group by '.$this->columnize($groups);
	}
	
	/**
	 * Compile the "order by" portions of the query.
	 *
	 * @param  \wiggum\services\db\Builder $query
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
	 * @param  \wiggum\services\db\Builder $query
	 * @param  int  $limit
	 * @return string
	 */
	protected function compileLimit(Builder $query, $limit) {
		return 'limit '.(int) $limit;
	}
	
	/**
	 * Compile the "offset" portions of the query.
	 *
	 * @param  \wiggum\services\db\Builder $query
	 * @param  int $offset
	 * @return string
	 */
	protected function compileOffset(Builder $query, $offset) {
		return 'offset '.(int) $offset;
	}
	
	/**
	 *
	 * @param \wiggum\services\db\Builder $query
	 * @param string|boolean $value
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
	 * @param  \wiggum\services\db\Builder $query
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
	 * @param  \wiggum\services\db\Builder $query
	 * @param  array  $values
	 * @return string
	 */
	public function compileUpdate(Builder $query) {
		$table = $this->wrap($query->from[0]);
	
		// Each one of the columns in the update statements needs to be wrapped in the
		// keyword identifiers, also a place-holder needs to be created for each of
		// the values in the list of bindings so we can make the sets statements.
		$columns = [];
	
		foreach ($query->updates as $key => $value) {
		    if ($this->isJsonSelector($key)) {
		        [$field, $path] = $this->wrapJsonFieldAndPath($key);
		        
		        $columns[] = "{$field} = json_set({$field}{$path}, ?)";
		    } else {
		        $columns[] = $this->wrap($key).' = ?';
		    }
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
	 * @param  \wiggum\services\db\Builder $query
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
	
	/* helpers */
	
	/**
	 * 
	 * @param array $columns
	 * @return string
	 */
	protected function columnizeUnquote(array $columns)
	{
	    return implode(', ', array_map(function($value) {
	        $value = str_replace('->', '->>', $value);
	        return $this->wrap($value);
	    }, $columns));
	}
	
	/**
	 * Wrap the given JSON selector.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function wrapJsonSelector($value)
	{
	    $delimiter = strpos($value, '->>') !== false ? '->>' : '->';
	    
	    [$field, $path] = $this->wrapJsonFieldAndPath($value, $delimiter);
	    
	    if ($delimiter == '->>') {
	        return 'json_unquote(json_extract('.$field.$path.'))';
	    } else {
	        return 'json_extract('.$field.$path.')';
	    }
	    
	}
}
