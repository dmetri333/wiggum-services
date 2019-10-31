<?php
namespace wiggum\tests\database;

use PHPUnit\Framework\TestCase;
use wiggum\services\db\Builder;
use wiggum\services\db\DB;
use wiggum\services\db\grammers\MySqlGrammar;

class DatabaseMySQLQueryBuilderTest extends TestCase
{
    protected function getBuilder()
    {
        $grammar = new MySqlGrammar();
        $db = new DB([]);
        
        return new Builder($db, $grammar);
    }
    
    /* WRAPPING */
    public function testMySqlWrapping()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from `users`', $builder->toSql());
    }
    
    public function testBasicTableWrappingProtectsQuotationMarks()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('some"table');
        $this->assertEquals('select * from `some"table`', $builder->toSql());
    }
    
    public function testWrappingProtectsQuotationMarks()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->From('some`table');
        $this->assertEquals('select * from `sometable`', $builder->toSql());
    }
    
    public function testAliasWrappingAsWholeConstant()
    {
        $builder = $this->getBuilder();
        $builder->select('x.y as foo.bar')->from('baz');
        $this->assertEquals('select `x`.`y` as `foo.bar` from `baz`', $builder->toSql());
    }
  
    public function testBasicTableWrapping()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('public.users');
        $this->assertEquals('select * from `public`.`users`', $builder->toSql());
    }
    
    /* BOOLEANS */
    public function testUppercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'AND');
        $this->assertEquals('select * from `users` where `name` = ?', $builder->toSql());
    }
    
    public function testLowercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'and');
        $this->assertEquals('select * from `users` where `name` = ?', $builder->toSql());
    }
    
    public function testCaseInsensitiveLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'And');
        $this->assertEquals('select * from `users` where `name` = ?', $builder->toSql());
    }
    
    
    /* ALIAS */
    public function testAlias()
    {
        $builder = $this->getBuilder();
        $builder->select(['foo as bar'])->from('users');
        $this->assertEquals('select `foo` as `bar` from `users`', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('services')->join('translations AS t', 't.item_id', '=', 'services.id');
        $this->assertEquals('select * from `services` inner join `translations` as `t` on `t`.`item_id` = `services`.`id`', $builder->toSql());
    }
    
    /* SELECTS - BASIC */
    public function testBasicSelect()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from `users`', $builder->toSql());
    }
    
    public function testAddingSelects()
    {
        $builder = $this->getBuilder();
        $builder->select('foo')->addSelect('bar')->addSelect(['baz', 'boom'])->from('users');
        $this->assertEquals('select `foo`, `bar`, `baz`, `boom` from `users`', $builder->toSql());
    }
    
    /* DISTINCT */
    public function testBasicSelectDistinct()
    {
        $builder = $this->getBuilder();
        $builder->distinct()->select(['foo', 'bar'])->from('users');
        $this->assertEquals('select distinct `foo`, `bar` from `users`', $builder->toSql());
    }
   
  
    /* WHERE - BASIC */
    public function testWheresBasic()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $this->assertEquals('select * from `users` where `id` = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhere('email', '=', 'foo');
        $this->assertEquals('select * from `users` where `id` = ? or `email` = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }
    
    public function testWhereSoundsLikeOperator()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', 'sounds like', 'John Doe');
        $this->assertEquals('select * from `users` where `name` sounds like ?', $builder->toSql());
        $this->assertEquals(['John Doe'], $builder->getBindings());
    }
    
    public function testWhereWithArray()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where(['foo' => 1, 'bar' => 2]);
        $this->assertEquals('select * from `users` where `foo` = ? and `bar` = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }
    
    public function testWheresNested()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere(function ($q) {
            $q->where('name', '=', 'bar')->where('age', '=', 25);
        });
        
        $this->assertEquals('select * from `users` where `email` = ? or (`name` = ? and `age` = ?)', $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar', 2 => 25], $builder->getBindings());
    }
    
    public function testWheresNullOperators()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', null);
        $this->assertEquals('select * from `users` where `foo` is null', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '=', null);
        $this->assertEquals('select * from `users` where `foo` is null', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '!=', null);
        $this->assertEquals('select * from `users` where `foo` is not null', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '<>', null);
        $this->assertEquals('select * from `users` where `foo` is not null', $builder->toSql());
    }
    
    /* WHERE - DATE/TIME */
    public function testWheresDate()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', 1);
        $this->assertEquals('select * from `users` where date(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereDate('created_at', '=', 1, 'or');
        $this->assertEquals('select * from `users` where `id` = ? or date(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
        $this->assertEquals('select * from `users` where date(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());
    }
    
    public function testWheresDay()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertEquals('select * from `users` where day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereDay('created_at', '=', 1, 'or');
        $this->assertEquals('select * from `users` where `id` = ? or day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->whereDay('created_at', '=', 2, 'or');
        $this->assertEquals('select * from `users` where day(`created_at`) = ? or day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
 
    }
    
    public function testWheresMonth()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 1);
        $this->assertEquals('select * from `users` where month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereMonth('created_at', '=', 1, 'or');
        $this->assertEquals('select * from `users` where `id` = ? or month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->whereMonth('created_at', '=', 6, 'or');
        $this->assertEquals('select * from `users` where month(`created_at`) = ? or month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
        
    }
    
    public function testWheresYear()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
        $this->assertEquals('select * from `users` where year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014], $builder->getBindings());
   
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereYear('created_at', '=', 2014, 'or');
        $this->assertEquals('select * from `users` where `id` = ? or year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2014], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->whereYear('created_at', '=', 2015, 'or');
        $this->assertEquals('select * from `users` where year(`created_at`) = ? or year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
     
    }

    public function testWheresTime()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
        $this->assertEquals('select * from `users` where time(`created_at`) >= ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }
    
    /* WHERE - BETWEENS */
    public function testWhereBetweens()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetween('id', [1, 2]);
        $this->assertEquals('select * from `users` where `id` between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotBetween('id', [1, 2]);
        $this->assertEquals('select * from `users` where `id` not between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }
    
  
    /* WHERE - IN */
    public function testWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [1, 2, 3]);
        $this->assertEquals('select * from `users` where `id` in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [1, 2, 3]);
        $this->assertEquals('select * from `users` where `id` = ? or `id` in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }
    
    public function testWhereNotIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', [1, 2, 3]);
        $this->assertEquals('select * from `users` where `id` not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', [1, 2, 3]);
        $this->assertEquals('select * from `users` where `id` = ? or `id` not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }
  
    public function testWhereInsEmpty()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', []);
        $this->assertEquals('select * from `users` where 0 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', []);
        $this->assertEquals('select * from `users` where `id` = ? or 0 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }
    
    public function testWhereNotInsEmpty()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', []);
        $this->assertEquals('select * from `users` where 1 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', []);
        $this->assertEquals('select * from `users` where `id` = ? or 1 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }
    
    /* WHERE - NULL */
    public function testWhereNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull('id');
        $this->assertEquals('select * from `users` where `id` is null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull('id');
        $this->assertEquals('select * from `users` where `id` = ? or `id` is null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }
    
    public function testWhereNotNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotNull('id');
        $this->assertEquals('select * from `users` where `id` is not null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull('id');
        $this->assertEquals('select * from `users` where `id` > ? or `id` is not null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }
    
    
    /* GROUP BY */
    public function testGroupBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('email');
        $this->assertEquals('select * from `users` group by `email`', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('id', 'email');
        $this->assertEquals('select * from `users` group by `id`, `email`', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy(['id', 'email']);
        $this->assertEquals('select * from `users` group by `id`, `email`', $builder->toSql());
    }
    
    /* ORDER BY */
    public function testOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
        $this->assertEquals('select * from `users` order by `email` asc, `age` desc', $builder->toSql());
    }
    
    /* OFFSET/LIMIT */
    public function testLimitsAndOffsets()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->offset(5)->limit(10);
        $this->assertEquals('select * from `users` limit 10 offset 5', $builder->toSql());
    }
   
    /* JOINS */
    public function testJoinsBasic()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', 'users.id', '=', 'contacts.id');
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id`', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->leftJoin('photos', 'users.id', '=', 'photos.id');
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` left join `photos` on `users`.`id` = `photos`.`id`', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoinWhere('photos', 'users.id', '=', 'bar')->joinWhere('photos', 'users.id', '=', 'foo');
        $this->assertEquals('select * from `users` left join `photos` on `users`.`id` = ? inner join `photos` on `users`.`id` = ?', $builder->toSql());
        $this->assertEquals(['bar', 'foo'], $builder->getBindings());
    }
    
    public function testJoinComplex()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orOn('users.name', '=', 'contacts.name');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `users`.`name` = `contacts`.`name`', $builder->toSql());
            
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->where('users.id', '=', 'foo')->where('users.name', '=', 'bar', 'or');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = ? or `users`.`name` = ?', $builder->toSql());
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
            
        // Run the assertions again
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = ? or `users`.`name` = ?', $builder->toSql());
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }
  
    public function testJoinWhereNull()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` and `contacts`.`deleted_at` is null', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at', 'or');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `contacts`.`deleted_at` is null', $builder->toSql());
    }
    
    public function testJoinWhereNotNull()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` and `contacts`.`deleted_at` is not null', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotNull('contacts.deleted_at', 'or');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `contacts`.`deleted_at` is not null', $builder->toSql());
    }
    
    public function testJoinWhereIn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` and `contacts`.`name` in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null], 'or');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `contacts`.`name` in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }
    
    public function testJoinWhereNotIn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` and `contacts`.`name` not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `contacts`.`name` not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }
    
    /* Aggregate */
    
   // public function testAggregateFunctions()
   // {
        
        //$builder = $this->getBuilder();
        //$builder->from('users')->count();
        //$this->assertEquals('select * from `users` limit 10 offset 5', $builder->toSql());
        
        
    //}
    
    /* INSERT */
    public function testInsertMethod()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->insert(['email' => 'foo']);
        $this->assertEquals('insert into `users` (`email`) values (?)', $builder->toSql());
        $this->assertEquals(['foo'], $builder->getBindings());
    }
    
    /* UPDATE */
    public function testUpdateMethod()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->where('id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
        $this->assertEquals('update `users` set `email` = ?, `name` = ? where `id` = ?', $builder->toSql());
        $this->assertEquals(['foo', 'bar', 1], $bindings);
        
        $builder = $this->getBuilder();
        $builder->from('users')->where('id', '=', 1)->orderBy('foo', 'desc')->limit(5)->update(['email' => 'foo', 'name' => 'bar']);
        $bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
        $this->assertEquals('update `users` set `email` = ?, `name` = ? where `id` = ? order by `foo` desc limit 5', $builder->toSql());
        $this->assertEquals(['foo', 'bar', 1], $bindings);

    }
    
    /* DELETE */
    public function testDeleteMethod()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->where('email', '=', 'foo')->delete();
        $this->assertEquals('delete from `users` where `email` = ?', $builder->toSql());
        $this->assertEquals(['foo'], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->from('users')->delete(1);
        $this->assertEquals('delete from `users` where `id` = ?', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    
    }
 
    /* JSON */
    public function testJsonPathEscaping()
    {
        $expected = <<<SQL
select json_unquote(json_extract(`json`, '$."\'))#"'))
SQL;
        
        $builder = $this->getBuilder();
        $builder->select("json->'))#");
        $this->assertEquals($expected, $builder->toSql());
       
        $builder = $this->getBuilder();
        $builder->select("json->\'))#");
        $this->assertEquals($expected, $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select("json->\\'))#");
        $this->assertEquals($expected, $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select("json->\\\'))#");
        $this->assertEquals($expected, $builder->toSql());
   
    }
    
    public function testWrappingJson()
    {
     
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('items->price', '=', 1);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."price"\') = ?', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
        $this->assertEquals('select json_unquote(json_extract(`items`, \'$."price"\')) from `users` where json_extract(`users`.`items`, \'$."price"\') = ? order by json_extract(`items`, \'$."price"\') asc', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."price"."in_usd"\') = ?', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."price"."in_usd"\') = ? and json_extract(`items`, \'$."age"\') = ?', $builder->toSql());
    }
    
    public function testWrappingJsonWithString()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('items->sku', '=', 'foo-bar');
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."sku"\') = ?', $builder->toSql());
        $this->assertEquals(['foo-bar'], $builder->getBindings());
    }
    
    public function testWrappingJsonWithInteger()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('items->price', '=', 1);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."price"\') = ?', $builder->toSql());
    }
    
    public function testWrappingJsonWithDouble()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('items->price', '=', 1.5);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."price"\') = ?', $builder->toSql());
    }
    
    public function testWrappingJsonWithBoolean()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."available"\') = ?', $builder->toSql());
    }
    
    public function testWrappingJsonWithBooleanAndIntegerThatLooksLikeOne()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true)->where('items->active', '=', false)->where('items->number_available', '=', 0);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."available"\') = ? and json_extract(`items`, \'$."active"\') = ? and json_extract(`items`, \'$."number_available"\') = ?', $builder->toSql());
    }
    
    public function testUpdateWrappingJson()
    {
        
        $builder = $this->getBuilder();
        $builder->from('users')->where('active', '=', 1)->update(['name->first_name' => 'John', 'name->last_name' => 'Doe']);
        $bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
        $this->assertEquals('update `users` set `name` = json_set(`name`, \'$."first_name"\', ?), `name` = json_set(`name`, \'$."last_name"\', ?) where `active` = ?', $builder->toSql());
        $this->assertEquals(['John', 'Doe', 1], $bindings);
       
    }
    
    public function testUpdateWrappingNestedJson()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->where('active', '=', 1)->update(['meta->name->first_name' => 'John', 'meta->name->last_name' => 'Doe']);
        $bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
        $this->assertEquals('update `users` set `meta` = json_set(`meta`, \'$."name"."first_name"\', ?), `meta` = json_set(`meta`, \'$."name"."last_name"\', ?) where `active` = ?', $builder->toSql());
        $this->assertEquals(['John', 'Doe', 1], $bindings);
        
     }
    
    public function testUpdateWithJsonPreparesBindingsCorrectly()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->where('id', '=', 0)->update(['options->enable' => false, 'updated_at' => '2015-05-26 22:02:06']);
        $bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
        $this->assertEquals( 'update `users` set `options` = json_set(`options`, \'$."enable"\', ?), `updated_at` = ? where `id` = ?', $builder->toSql());
        $this->assertEquals([false, '2015-05-26 22:02:06', 0], $bindings);
        
        $builder = $this->getBuilder();
        $builder->from('users')->where('id', '=', 0)->update(['options->size' => 45, 'updated_at' => '2015-05-26 22:02:06']);
        $bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
        $this->assertEquals('update `users` set `options` = json_set(`options`, \'$."size"\', ?), `updated_at` = ? where `id` = ?', $builder->toSql());
        $this->assertEquals([45, '2015-05-26 22:02:06', 0], $bindings);
        
        $builder = $this->getBuilder();
        $builder->from('users')->update(['options->size' => null]);
        $bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
        $this->assertEquals('update `users` set `options` = json_set(`options`, \'$."size"\', ?)', $builder->toSql());
        $this->assertEquals([null], $bindings);
        
    }
    
    public function testWhereJsonContains()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereJsonContains('options', ['en']);
        $this->assertEquals('select * from `users` where json_contains(`options`, ?)', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereJsonContains('users.options->languages', ['en']);
        $this->assertEquals('select * from `users` where json_contains(`users`.`options`, ?, \'$."languages"\')', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());
    }
    
    public function testWhereJsonDoesntContain()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereJsonDoesntContain('options->languages', ['en']);
        $this->assertEquals('select * from `users` where not json_contains(`options`, ?, \'$."languages"\')', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());
    }

    public function testWhereJsonLength()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereJsonLength('options', '=', 0);
        $this->assertEquals('select * from `users` where json_length(`options`) = ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereJsonLength('users.options->languages', '>', 0);
        $this->assertEquals('select * from `users` where json_length(`users`.`options`, \'$."languages"\') > ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());
        
    }

    /*
     public function testJoinsWithNestedConditions()
     {
     $builder = $this->getBuilder();
     $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
     $j->on('users.id', '=', 'contacts.id')->where('contacts.country', '=', 'US')->orWhere('contacts.is_partner', '=', 1);
     });
     $this->assertEquals('select * from `users` left join `contacts` on `users`.`id` = `contacts`.`id` and (`contacts`.`country` = ? or `contacts`.`is_partner` = ?)', $builder->toSql());
     $this->assertEquals(['US', 1], $builder->getBindings());
     
     $builder = $this->getBuilder();
     $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
     $j->on('users.id', '=', 'contacts.id')->where('contacts.is_active', '=', 1)->orOn(function ($j) {
     $j->orWhere(function ($j) {
     $j->where('contacts.country', '=', 'UK')->orOn('contacts.type', '=', 'users.type');
     })->where(function ($j) {
     $j->where('contacts.country', '=', 'US')->orWhereNull('contacts.is_partner');
     });
     });
     });
     $this->assertEquals('select * from `users` left join `contacts` on `users`.`id` = `contacts`.`id` and `contacts`.`is_active` = ? or ((`contacts`.`country` = ? or `contacts`.`type` = `users`.`type`) and (`contacts`.`country` = ? or `contacts`.`is_partner` is null))', $builder->toSql());
     $this->assertEquals([1, 'UK', 'US'], $builder->getBindings());
     }
     
     public function testJoinsWithNestedJoins()
     {
     $builder = $this->getBuilder();
     $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function ($j) {
     $j->on('users.id', '=', 'contacts.id')->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id');
     });
     $this->assertEquals('select "users"."id", "contacts"."id", "contact_types"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id") on "users"."id" = "contacts"."id"', $builder->toSql());
     }
     
     public function testJoinsWithMultipleNestedJoins()
     {
     $builder = $this->getBuilder();
     $builder->select('users.id', 'contacts.id', 'contact_types.id', 'countrys.id', 'planets.id')->from('users')->leftJoin('contacts', function ($j) {
     $j->on('users.id', 'contacts.id')
     ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
     ->leftJoin('countrys', function ($q) {
     $q->on('contacts.country', '=', 'countrys.country')
     ->join('planets', function ($q) {
     $q->on('countrys.planet_id', '=', 'planet.id')
     ->where('planet.is_settled', '=', 1)
     ->where('planet.population', '>=', 10000);
     });
     });
     });
     $this->assertEquals('select "users"."id", "contacts"."id", "contact_types"."id", "countrys"."id", "planets"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id" left join ("countrys" inner join "planets" on "countrys"."planet_id" = "planet"."id" and "planet"."is_settled" = ? and "planet"."population" >= ?) on "contacts"."country" = "countrys"."country") on "users"."id" = "contacts"."id"', $builder->toSql());
     $this->assertEquals(['1', 10000], $builder->getBindings());
     }
     
     public function testUpdateMethodWithJoins()
     {
     $builder = $this->getBuilder();
     $builder->getConnection()->shouldReceive('update')->once()->with('update "users" inner join "orders" on "users"."id" = "orders"."user_id" set "email" = ?, "name" = ? where "users"."id" = ?', ['foo', 'bar', 1])->andReturn(1);
     $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
     $this->assertEquals(1, $result);
     
     $builder = $this->getBuilder();
     $builder->getConnection()->shouldReceive('update')->once()->with('update "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ? set "email" = ?, "name" = ?', [1, 'foo', 'bar'])->andReturn(1);
     $result = $builder->from('users')->join('orders', function ($join) {
     $join->on('users.id', '=', 'orders.user_id')
     ->where('users.id', '=', 1);
     })->update(['email' => 'foo', 'name' => 'bar']);
     $this->assertEquals(1, $result);
     }
     
     
     public function testUpdateMethodWithJoinsOnMySql()
     {
     $builder = $this->getMySqlBuilder();
     $builder->getConnection()->shouldReceive('update')->once()->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` set `email` = ?, `name` = ? where `users`.`id` = ?', ['foo', 'bar', 1])->andReturn(1);
     $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
     $this->assertEquals(1, $result);
     
     $builder = $this->getMySqlBuilder();
     $builder->getConnection()->shouldReceive('update')->once()->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` and `users`.`id` = ? set `email` = ?, `name` = ?', [1, 'foo', 'bar'])->andReturn(1);
     $result = $builder->from('users')->join('orders', function ($join) {
     $join->on('users.id', '=', 'orders.user_id')
     ->where('users.id', '=', 1);
     })->update(['email' => 'foo', 'name' => 'bar']);
     $this->assertEquals(1, $result);
     }
     
     public function testDeleteWithJoinMethod()
     {
     $builder = $this->getSqliteBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "rowid" in (select "users"."rowid" from "users" inner join "contacts" on "users"."id" = "contacts"."id" where "users"."email" = ? order by "users"."id" asc limit 1)', ['foo'])->andReturn(1);
     $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('users.email', '=', 'foo')->orderBy('users.id')->limit(1)->delete();
     $this->assertEquals(1, $result);
     
     $builder = $this->getMySqlBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `email` = ?', ['foo'])->andReturn(1);
     $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
     $this->assertEquals(1, $result);
     
     $builder = $this->getMySqlBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete `a` from `users` as `a` inner join `users` as `b` on `a`.`id` = `b`.`user_id` where `email` = ?', ['foo'])->andReturn(1);
     $result = $builder->from('users AS a')->join('users AS b', 'a.id', '=', 'b.user_id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
     $this->assertEquals(1, $result);
     
     $builder = $this->getMySqlBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `users`.`id` = ?', [1])->andReturn(1);
     $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->orderBy('id')->take(1)->delete(1);
     $this->assertEquals(1, $result);
     
     $builder = $this->getSqlServerBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete [users] from [users] inner join [contacts] on [users].[id] = [contacts].[id] where [email] = ?', ['foo'])->andReturn(1);
     $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('email', '=', 'foo')->delete();
     $this->assertEquals(1, $result);
     
     $builder = $this->getSqlServerBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete [a] from [users] as [a] inner join [users] as [b] on [a].[id] = [b].[user_id] where [email] = ?', ['foo'])->andReturn(1);
     $result = $builder->from('users AS a')->join('users AS b', 'a.id', '=', 'b.user_id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
     $this->assertEquals(1, $result);
     
     $builder = $this->getSqlServerBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete [users] from [users] inner join [contacts] on [users].[id] = [contacts].[id] where [users].[id] = ?', [1])->andReturn(1);
     $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->delete(1);
     $this->assertEquals(1, $result);
     
     $builder = $this->getPostgresBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" USING "contacts" where "users"."email" = ? and "users"."id" = "contacts"."id"', ['foo'])->andReturn(1);
     $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('users.email', '=', 'foo')->delete();
     $this->assertEquals(1, $result);
     
     $builder = $this->getPostgresBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" as "a" USING "users" as "b" where "email" = ? and "a"."id" = "b"."user_id"', ['foo'])->andReturn(1);
     $result = $builder->from('users AS a')->join('users AS b', 'a.id', '=', 'b.user_id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
     $this->assertEquals(1, $result);
     
     $builder = $this->getPostgresBuilder();
     $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" USING "contacts" where "users"."id" = ? and "users"."id" = "contacts"."id"', [1])->andReturn(1);
     $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->orderBy('id')->take(1)->delete(1);
     $this->assertEquals(1, $result);
     }
     
     
     */
    
}