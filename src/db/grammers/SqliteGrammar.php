<?php
namespace wiggum\services\db\grammers;

use wiggum\services\db\Builder;
use wiggum\services\db\Grammar;

class SqliteGrammar extends Grammar
{
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
	public function compileSelect(Builder $query) : string 
	{
		if (is_null($query->columns)) {
			$query->columns = ['*'];
		}

		return trim(implode(' ', $this->compileComponents($query)));
	}

	/**
	 * Compile the components necessary for a select clause.
	 *
	 * @param \wiggum\services\db\Builder $query
     * 
	 * @return array
	 */
	protected function compileComponents(Builder $query)
	{
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
	 * @param \wiggum\services\db\Builder $query
	 * @param array $aggregate
     * 
	 * @return string
	 */
	protected function compileAggregate(Builder $query, $aggregate)
	{
		$column = $this->columnize($aggregate['columns']);

		if ($query->distinct && $column !== '*') {
			$column = 'distinct ' . $column;
		}

		return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
	}

	/**
	 * Compile the "select *" portion of the query.
	 *
	 * @param \wiggum\services\db\Builder $query
	 * @param array $columns
     * 
	 * @return string
	 */
	protected function compileColumns(Builder $query, $columns)
	{
		if (!is_null($query->aggregate)) {
			return;
		}

		$select = $query->distinct ? 'select distinct ' : 'select ';

		return $select . $this->columnizeUnquote($columns);
	}

	/**
	 * @param \wiggum\services\db\Builder $query
	 * @param array $tables
     * 
	 * @return string
	 */
	protected function compileFrom(Builder $query, array $tables)
	{
		return 'from ' . implode(', ', $this->wrapArray($tables));
	}

	/**
	 * Compile the "join" portions of the query.
	 *
	 * @param \wiggum\services\db\Builder $query
	 * @param array $joins
	 * @return string
	 */
	protected function compileJoins(Builder $query, $joins)
	{
		$sql = [];

		$query->setBindings([], 'join');

		foreach ($joins as $join) {
			$table = $this->wrap($join->table);

			$clauses = [];

			foreach ($join->clauses as $clause) {
				$clauses[] = $this->compileJoinConstraint($clause);
			}

			foreach ($join->bindings as $binding) {
				$query->addBinding($binding, 'join');
			}

			$clauses[0] = $this->removeLeadingBoolean($clauses[0]);

			$clauses = implode(' ', $clauses);
			$type = $join->type;

			$sql[] = "$type join $table on $clauses";
		}

		return implode(' ', $sql);
	}

	/**
	 * Create a join clause constraint segment.
	 *
	 * @param array $clause
	 * @return string
	 */
	protected function compileJoinConstraint(array $clause)
	{
		$firstColumn = $this->wrap($clause['firstColumn']);

		if ($clause['where']) {
			if ($clause['operator'] === 'in' || $clause['operator'] === 'not in') {
				$secondColumn = '(' . implode(', ', array_fill(0, $clause['secondColumn'], '?')) . ')';
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
	 * @param \wiggum\services\db\Builder $query
	 * @return string
	 */
	protected function compileWheres(Builder $query)
	{
		$sql = [];

		if (is_null($query->wheres)) {
			return '';
		}

		foreach ($query->wheres as $where) {
			$method = "where{$where['type']}";
			$sql[] = $where['boolean'] . ' ' . $this->$method($query, $where);
		}

		if (count($sql) > 0) {
			$sql = implode(' ', $sql);
			return 'where ' . $this->removeLeadingBoolean($sql);
		}

		return '';
	}

	/**
	 * @param \wiggum\services\db\Builder $query
	 * @param array $where
	 * @return string
	 */
	protected function whereNested(Builder $query, $where)
	{
		$nested = $where['query'];
		return '(' . substr($this->compileWheres($nested), 6) . ')';
	}

	/**
	 * @param \wiggum\services\db\Builder $query
	 * @param array $where
	 * @return string
	 */
	protected function whereBasic(Builder $query, $where)
	{
		return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ?';
	}

	/**
	 * @param \wiggum\services\db\Builder $query
	 * @param array $where
	 * @return string
	 */
	protected function whereBetween(Builder $query, $where)
	{
		$between = $where['not'] ? 'not between' : 'between';
		return $this->wrap($where['column']) . ' ' . $between . ' ? and ?';
	}

	/**
	 * @param \wiggum\services\db\Builder $query
	 * @param array $where
	 * @return string
	 */
	protected function whereIn(Builder $query, $where)
	{
		$in = $where['not'] ? 'not in' : 'in';

		if (empty($where['values'])) {
			return $where['not'] ? '1 = 1' : '0 = 1';
		}

		$values = $this->parameterize($where['values']);

		return $this->wrap($where['column']) . ' ' . $in . ' (' . $values . ')';
	}

	/**
	 * @param \wiggum\services\db\Builder $query
	 * @param array $where
	 * @return string
	 */
	protected function whereNull(Builder $query, $where)
	{
		$null = $where['not'] ? 'not null' : 'null';
		return $this->wrap($where['column']) . ' is ' . $null;
	}

	protected function whereDate(Builder $query, $where)
	{
		return $this->dateBasedWhere('date', $query, $where);
	}

	protected function whereTime(Builder $query, $where)
	{
		return $this->dateBasedWhere('time', $query, $where);
	}

	protected function whereDay(Builder $query, $where)
	{
		return $this->dateBasedWhere('day', $query, $where);
	}

	protected function whereMonth(Builder $query, $where)
	{
		return $this->dateBasedWhere('month', $query, $where);
	}

	protected function whereYear(Builder $query, $where)
	{
		return $this->dateBasedWhere('year', $query, $where);
	}

	protected function dateBasedWhere($type, Builder $query, $where)
	{
		return $type . '(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ?';
	}

	/**
	 * SQLite doesn't have a direct json_contains() equivalent.
	 * This implementation supports JSON arrays via json_each().
	 */
	protected function whereJsonContains(Builder $query, $where)
	{
		$value = '?';
		$not = $where['not'] ? 'not ' : '';

		[$field, $path] = $this->wrapJsonFieldAndPath($where['column']);

		return $not . 'exists (select 1 from json_each(' . $field . $path . ') where json_each.value = ' . $value . ')';
	}

	protected function whereJsonLength(Builder $query, $where)
	{
		$value = '?';
		$operator = $where['operator'];

		[$field, $path] = $this->wrapJsonFieldAndPath($where['column']);

		return 'json_array_length(' . $field . $path . ') ' . $operator . ' ' . $value;
	}

	protected function compileGroups(Builder $query, $groups)
	{
		return 'group by ' . $this->columnize($groups);
	}

	protected function compileOrders(Builder $query, $orders)
	{
		return 'order by ' . implode(', ', array_map(function ($order) {
			return $this->wrap($order['column']) . ' ' . $order['direction'];
		}, $orders));
	}

	protected function compileLimit(Builder $query, $limit)
	{
		return 'limit ' . (int) $limit;
	}

	protected function compileOffset(Builder $query, $offset)
	{
		return 'offset ' . (int) $offset;
	}

	public function compileInsert(Builder $query) : string 
	{
		$table = $this->wrap($query->from[0]);
		$columns = $this->columnize(array_keys(reset($query->inserts)));
		$parameters = $this->parameterize(reset($query->inserts));

		$value = array_fill(0, count($query->inserts), "($parameters)");
		$parameters = implode(', ', $value);

		return "insert into $table ($columns) values $parameters";
	}

	public function compileUpdate(Builder $query) : string 
	{
		if (isset($query->joins)) {
			throw new \RuntimeException('SQLite grammar does not support UPDATE with JOIN clauses.');
		}

		$table = $this->wrap($query->from[0]);

		$columns = [];

		foreach ($query->updates as $key => $value) {
			if ($this->isJsonSelector($key)) {
				[$field, $path] = $this->wrapJsonFieldAndPath($key);
				$columns[] = "{$field} = json_set({$field}{$path}, ?)";
			} else {
				$columns[] = $this->wrap($key) . ' = ?';
			}
		}

		$columns = implode(', ', $columns);
		$where = $this->compileWheres($query);

		$sql = trim("update {$table} set {$columns} {$where}");

		if (isset($query->orders)) {
			$sql .= ' ' . $this->compileOrders($query, $query->orders);
		}
		if (isset($query->limit)) {
			$sql .= ' ' . $this->compileLimit($query, $query->limit);
		}

		return trim($sql);
	}

	public function compileDelete(Builder $query) : string 
	{
		if (isset($query->joins)) {
			throw new \RuntimeException('SQLite grammar does not support DELETE with JOIN clauses.');
		}

		$table = $this->wrap($query->from[0]);
		$where = $this->compileWheres($query);

		$sql = trim("delete from $table $where");

		if (isset($query->orders)) {
			$sql .= ' ' . $this->compileOrders($query, $query->orders);
		}

		if (isset($query->limit)) {
			$sql .= ' ' . $this->compileLimit($query, $query->limit);
		}

		return $sql;
	}

	/**
	 * Compile the query to determine the list of columns.
	 *
	 * NOTE: Kept for compatibility, but SQLite uses compileColumnListing() instead.
	 */
	public function compileColumnExists()
	{
		return 'select name from pragma_table_info(?)';
	}
	
	/**
	 * SQLite PRAGMA table_info does not reliably support bound parameters.
	 *
	 * @param string $table
	 * @return array{sql: string, bindings: array}
	 */
	public function compileColumnListing(string $table) : array
	{
		$table = str_replace("\0", '', $table);
		$table = str_replace("'", "''", $table);

		return [
			'sql' => "select name from pragma_table_info('{$table}')",
			'bindings' => [],
		];
	}

	/* helpers */

	protected function columnizeUnquote(array $columns)
	{
		return implode(', ', array_map(function ($value) {
			$value = str_replace('->', '->>', $value);
			return $this->wrap($value);
		}, $columns));
	}

	protected function wrapJsonSelector($value)
	{
		$delimiter = strpos($value, '->>') !== false ? '->>' : '->';

		[$field, $path] = $this->wrapJsonFieldAndPath($value, $delimiter);

		return 'json_extract(' . $field . $path . ')';
	}
}
