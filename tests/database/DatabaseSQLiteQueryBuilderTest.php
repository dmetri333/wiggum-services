<?php
namespace wiggum\tests\database;

use PHPUnit\Framework\TestCase;
use wiggum\services\db\DB;

class DatabaseSQLiteQueryBuilderTest extends TestCase
{
	private ?string $dbFile = null;
	private ?DB $db = null;

	private function normalizeSql(string $sql): string
	{
		return preg_replace('/\s+/', ' ', trim($sql));
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->dbFile = sys_get_temp_dir() . '/wiggum-services-' . uniqid('', true) . '.sqlite';

		$this->db = new DB([
			'connection' => '\wiggum\services\db\connections\Sqlite',
			'url' => $this->dbFile,
		]);
        
		// Minimal schema for exercising the query builder.
		$this->db->getConnection()->exec('create table users (id integer primary key autoincrement, name text)');
		$this->db->getConnection()->exec("insert into users (name) values ('Taylor'), ('Jordan')");
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		$this->db = null;

		if ($this->dbFile && file_exists($this->dbFile)) {
			@unlink($this->dbFile);
		}

		$this->dbFile = null;
	}

	public function testSelectWhereFetchRow()
	{
		$user = $this->db->table('users')->where('id', '=', 1)->fetchRow();

		$this->assertIsObject($user);
		$this->assertEquals(1, $user->id);
		$this->assertEquals('Taylor', $user->name);
	}

	/* WRAPPING */
	public function testSqliteWrapping()
	{
		$builder = $this->db->table('users')->select('*');
		$this->assertEquals('select * from `users`', $builder->toSql());
	}

	public function testBasicTableWrappingProtectsQuotationMarks()
	{
		$builder = $this->db->table('some"table')->select('*');
		$this->assertEquals('select * from `some"table`', $builder->toSql());
	}

	public function testWrappingProtectsBackticks()
	{
		$builder = $this->db->table('some`table')->select('*');
		$this->assertEquals('select * from `sometable`', $builder->toSql());
	}

	public function testAliasWrappingAsWholeConstant()
	{
		$builder = $this->db->table('baz')->select('x.y as foo.bar');
		$this->assertEquals('select `x`.`y` as `foo.bar` from `baz`', $builder->toSql());
	}

	public function testBasicTableWrappingWithSchemaLikePrefix()
	{
		$builder = $this->db->table('public.users')->select('*');
		$this->assertEquals('select * from `public`.`users`', $builder->toSql());
	}

	/* BOOLEANS */
	public function testLeadingBooleansAreRemoved()
	{
		$builder = $this->db->table('users')->select('*')->where('name', '=', 'Taylor', 'AND');
		$this->assertEquals('select * from `users` where `name` = ?', $builder->toSql());

		$builder = $this->db->table('users')->select('*')->where('name', '=', 'Taylor', 'and');
		$this->assertEquals('select * from `users` where `name` = ?', $builder->toSql());

		$builder = $this->db->table('users')->select('*')->where('name', '=', 'Taylor', 'And');
		$this->assertEquals('select * from `users` where `name` = ?', $builder->toSql());
	}

	/* DISTINCT */
	public function testBasicSelectDistinct()
	{
		$builder = $this->db->table('users')->distinct()->select(['foo', 'bar']);
		$this->assertEquals('select distinct `foo`, `bar` from `users`', $builder->toSql());
	}

	/* WHERE */
	public function testWheresBasicAndBindings()
	{
		$builder = $this->db->table('users')->select('*')->where('id', '=', 1);
		$this->assertEquals('select * from `users` where `id` = ?', $builder->toSql());
		$this->assertEquals([0 => 1], $builder->getBindings());

		$builder = $this->db->table('users')->select('*')->where('id', '=', 1)->orWhere('email', '=', 'foo');
		$this->assertEquals('select * from `users` where `id` = ? or `email` = ?', $builder->toSql());
		$this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
	}

	public function testWhereWithArray()
	{
		$builder = $this->db->table('users')->select('*')->where(['foo' => 1, 'bar' => 2]);
		$this->assertEquals('select * from `users` where `foo` = ? and `bar` = ?', $builder->toSql());
		$this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
	}

	public function testWheresNestedSql()
	{
		$builder = $this->db->table('users')->select('*')->where('email', '=', 'foo')->orWhere(function ($q) {
			$q->where('name', '=', 'bar')->where('age', '=', 25);
		});

		$this->assertEquals('select * from `users` where `email` = ? or (`name` = ? and `age` = ?)', $builder->toSql());
		$this->assertEquals([0 => 'foo', 1 => 'bar', 2 => 25], $builder->getBindings());
	}

	public function testWhereInEmptyAndNotInEmpty()
	{
		$builder = $this->db->table('users')->select('*')->whereIn('id', []);
		$this->assertEquals('select * from `users` where 0 = 1', $builder->toSql());

		$builder = $this->db->table('users')->select('*')->whereNotIn('id', []);
		$this->assertEquals('select * from `users` where 1 = 1', $builder->toSql());
	}

	/* GROUP/ORDER */
	public function testGroupByOrderByLimitOffsetSql()
	{
		$builder = $this->db->table('users')->select('*')->groupBy('name')->orderBy('id', 'desc')->limit(10)->offset(20);
		$this->assertEquals('select * from `users` group by `name` order by `id` desc limit 10 offset 20', $builder->toSql());
	}

	/* JOINS */
	public function testJoinsBasicSql()
	{
		$builder = $this->db->table('services')->select('*')->join('translations AS t', 't.item_id', '=', 'services.id');
		$this->assertEquals('select * from `services` inner join `translations` as `t` on `t`.`item_id` = `services`.`id`', $builder->toSql());
	}

	/* AGGREGATE SQL (no execution) */
	public function testAggregateCountSql()
	{
		$builder = $this->db->table('users');
		$builder->aggregate = ['function' => 'count', 'columns' => ['*']];
		$this->assertEquals('select count(*) as aggregate from `users`', $this->normalizeSql($builder->toSql()));
	}

	public function testSqlGenerationLimitOffset()
	{
		$builder = $this->db->table('users')->select('*')->limit(1)->offset(1);

		$this->assertEquals('select * from `users` limit 1 offset 1', $builder->toSql());
		$rows = $builder->fetchRows();
		$this->assertCount(1, $rows);
		$this->assertEquals('Jordan', $rows[0]->name);
	}

	public function testInsertUpdateDeleteExecute()
	{
		$newId = $this->db->table('users')->insert(['name' => 'Sam'])->execute(true);
		$this->assertIsNumeric($newId);

		$this->db->table('users')->where('id', '=', (int) $newId)->update(['name' => 'Samuel'])->execute(false);
		$updated = $this->db->table('users')->where('id', '=', (int) $newId)->fetchRow();
		$this->assertEquals('Samuel', $updated->name);

		$this->db->table('users')->where('id', '=', (int) $newId)->delete()->execute(false);
		$deleted = $this->db->table('users')->where('id', '=', (int) $newId)->fetchRow();
		$this->assertNull($deleted);
	}

	public function testGetColumnListing()
	{
		$columns = $this->db->table('users')->getColumnListing();

		$this->assertIsArray($columns);
		$this->assertContains('id', $columns);
		$this->assertContains('name', $columns);
	}

	public function testAggregateCount()
	{
		$this->assertEquals(2, $this->db->table('users')->count());
		$this->assertEquals(1, $this->db->table('users')->where('id', '=', 1)->count());
	}

	public function testUpdateWithJoinThrows()
	{
		$this->expectException(\RuntimeException::class);
		$this->db->table('users')
			->join('users as u2', 'u2.id', '=', 'users.id')
			->update(['name' => 'X'])
			->execute(false);
	}

	public function testDeleteWithJoinThrows()
	{
		$this->expectException(\RuntimeException::class);
		$this->db->table('users')
			->join('users as u2', 'u2.id', '=', 'users.id')
			->delete()
			->execute(false);
	}
}
