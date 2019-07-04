<?php

namespace wiggum\tests\database;

use stdClass;
use RuntimeException;
use BadMethodCallException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use wiggum\services\db\Builder;
use wiggum\services\db\Grammar;
use wiggum\services\db\DB;

class DatabaseQueryBuilderTest extends TestCase
{
    protected function getBuilder()
    {
        $grammar = new Grammar();
        $db = new DB([]);
        
        return new Builder($db, $grammar);
    }
    
    public function testBasicSelect()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from `users`', $builder->toSql());
    }
    
 /*
    public function testBasicSelectWithGetColumns()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()->shouldReceive('processSelect');
        $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
            $this->assertEquals('select * from "users"', $sql);
        });
            $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
                $this->assertEquals('select "foo", "bar" from "users"', $sql);
            });
                $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
                    $this->assertEquals('select "baz" from "users"', $sql);
                });
                    
                    $builder->from('users')->get();
                    $this->assertNull($builder->columns);
                    
                    $builder->from('users')->get(['foo', 'bar']);
                    $this->assertNull($builder->columns);
                    
                    $builder->from('users')->get('baz');
                    $this->assertNull($builder->columns);
                    
                    $this->assertEquals('select * from "users"', $builder->toSql());
                    $this->assertNull($builder->columns);
    }
 
 */
    
    public function testBasicTableWrappingProtectsQuotationMarks()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('some"table');
        $this->assertEquals('select * from `some"table`', $builder->toSql());
    }
    
    public function testAliasWrappingAsWholeConstant()
    {
        $builder = $this->getBuilder();
        $builder->select('x.y as foo.bar')->from('baz');
        $this->assertEquals('select `x`.`y` as `foo.bar` from `baz`', $builder->toSql());
    }
  
    public function testAddingSelects()
    {
        $builder = $this->getBuilder();
        $builder->select('foo')->addSelect('bar')->addSelect(['baz', 'boom'])->from('users');
        $this->assertEquals('select `foo`, `bar`, `baz`, `boom` from `users`', $builder->toSql());
    }
    
    public function testBasicSelectDistinct()
    {
        $builder = $this->getBuilder();
        $builder->distinct()->select(['foo', 'bar'])->from('users');
        $this->assertEquals('select distinct `foo`, `bar` from `users`', $builder->toSql());
    }
    
    public function testBasicAlias()
    {
        $builder = $this->getBuilder();
        $builder->select(['foo as bar'])->from('users');
        $this->assertEquals('select `foo` as `bar` from `users`', $builder->toSql());
    }
    
    public function testAliasWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users as people');
        $this->assertEquals('select * from `users` as `people`', $builder->toSql());
    }
    
    public function testJoinAliases()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('services')->join('translations AS t', 't.item_id', '=', 'services.id');
        $this->assertEquals('select * from `services` inner join `translations` as `t` on `t`.`item_id` = `services`.`id`', $builder->toSql());
    }
    
    public function testBasicTableWrapping()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('public.users');
        $this->assertEquals('select * from `public`.`users`', $builder->toSql());
    }
    
    public function testBasicWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $this->assertEquals('select * from `users` where `id` = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }
    
    public function testWrappingProtectsQuotationMarks()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->From('some`table');
        $this->assertEquals('select * from `sometable`', $builder->toSql());
    }
    
    public function testDateBasedWheresAccepts()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', 1);
        $this->assertEquals('select * from `users` where date(`created_at`) = ?', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertEquals('select * from `users` where day(`created_at`) = ?', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 1);
        $this->assertEquals('select * from `users` where month(`created_at`) = ?', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 1);
        $this->assertEquals('select * from `users` where year(`created_at`) = ?', $builder->toSql());
    }
    
    public function testDateBasedOrWheresAcceptsTwoArguments()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereDate('created_at', '=', 1, 'or');
        $this->assertEquals('select * from `users` where `id` = ? or date(`created_at`) = ?', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereDay('created_at', '=', 1, 'or');
        $this->assertEquals('select * from `users` where `id` = ? or day(`created_at`) = ?', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereMonth('created_at', '=', 1, 'or');
        $this->assertEquals('select * from `users` where `id` = ? or month(`created_at`) = ?', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereYear('created_at', '=', 1, 'or');
        $this->assertEquals('select * from `users` where `id` = ? or year(`created_at`) = ?', $builder->toSql());
    }
   
    public function testWhereDate()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
        $this->assertEquals('select * from `users` where date(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());
    }
    
    public function testWhereDay()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertEquals('select * from `users` where day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }
    
    public function testOrWhereDay()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->whereDay('created_at', '=', 2, 'or');
        $this->assertEquals('select * from `users` where day(`created_at`) = ? or day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }
    
    public function testWhereMonth()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
        $this->assertEquals('select * from `users` where month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 5], $builder->getBindings());
    }
    
    public function testOrWhereMonth()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->whereMonth('created_at', '=', 6, 'or');
        $this->assertEquals('select * from `users` where month(`created_at`) = ? or month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
    }
    
    public function testWhereYear()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
        $this->assertEquals('select * from `users` where year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014], $builder->getBindings());
    }
    
    public function testOrWhereYear()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->whereYear('created_at', '=', 2015, 'or');
        $this->assertEquals('select * from `users` where year(`created_at`) = ? or year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
    }
    
    public function testWhereTime()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
        $this->assertEquals('select * from `users` where time(`created_at`) >= ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }
    
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
    
    public function testBasicOrWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->where('email', '=', 'foo', 'or');
        $this->assertEquals('select * from `users` where `id` = ? or `email` = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }
    
  
    
    public function testBasicWhereIns()
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
    
    public function testBasicWhereNotIns()
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
    
  
    
    public function testEmptyWhereIns()
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
    
    public function testEmptyWhereNotIns()
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
    
    public function testBasicWhereNulls()
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
    
    public function testBasicWhereNotNulls()
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
    
    public function testOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
        $this->assertEquals('select * from `users` order by `email` asc, `age` desc', $builder->toSql());
    }
    
    public function testLimitsAndOffsets()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->offset(5)->limit(10);
        $this->assertEquals('select * from `users` limit 10 offset 5', $builder->toSql());
    }
    
    public function testWhereWithArrayConditions()
    { 
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where(['foo' => 1, 'bar' => 2]);
        $this->assertEquals('select * from `users` where `foo` = ? and `bar` = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }
    
    public function testNestedWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere(function ($q) {
            $q->where('name', '=', 'bar')->where('age', '=', 25);
        });
        
        $this->assertEquals('select * from `users` where `email` = ? or (`name` = ? and `age` = ?)', $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar', 2 => 25], $builder->getBindings());
    }
    
    public function testBasicJoins()
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
    
    public function testComplexJoin()
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
  
    
    public function testAggregateFunctions()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
            $results = $builder->from('users')->count();
            $this->assertEquals(1, $results);
            
            $builder = $this->getBuilder();
            $builder->getConnection()->shouldReceive('select')->once()->with('select exists(select * from "users") as "exists"', [], true)->andReturn([['exists' => 1]]);
            $results = $builder->from('users')->exists();
            $this->assertTrue($results);
            
            $builder = $this->getBuilder();
            $builder->getConnection()->shouldReceive('select')->once()->with('select exists(select * from "users") as "exists"', [], true)->andReturn([['exists' => 0]]);
            $results = $builder->from('users')->doesntExist();
            $this->assertTrue($results);
            
            $builder = $this->getBuilder();
            $builder->getConnection()->shouldReceive('select')->once()->with('select max("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
            $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
                return $results;
            });
                $results = $builder->from('users')->max('id');
                $this->assertEquals(1, $results);
                
                $builder = $this->getBuilder();
                $builder->getConnection()->shouldReceive('select')->once()->with('select min("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
                $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
                    return $results;
                });
                    $results = $builder->from('users')->min('id');
                    $this->assertEquals(1, $results);
                    
                    $builder = $this->getBuilder();
                    $builder->getConnection()->shouldReceive('select')->once()->with('select sum("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
                    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
                        return $results;
                    });
                        $results = $builder->from('users')->sum('id');
                        $this->assertEquals(1, $results);
    }
   
    public function testInsertMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('insert')->once()->with('insert into "users" ("email") values (?)', ['foo'])->andReturn(true);
        $result = $builder->from('users')->insert(['email' => 'foo']);
        $this->assertTrue($result);
    }
    
 
 
    
    public function testUpdateMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
        
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('update')->once()->with('update `users` set `email` = ?, `name` = ? where `id` = ? order by `foo` desc limit 5', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->orderBy('foo', 'desc')->limit(5)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
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
    
  
  
   
    
    
    
    
    public function testDeleteMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "email" = ?', ['foo'])->andReturn(1);
        $result = $builder->from('users')->where('email', '=', 'foo')->delete();
        $this->assertEquals(1, $result);
        
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "users"."id" = ?', [1])->andReturn(1);
        $result = $builder->from('users')->delete(1);
        $this->assertEquals(1, $result);
        
        $builder = $this->getSqliteBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "rowid" in (select "users"."rowid" from "users" where "email" = ? order by "id" asc limit 1)', ['foo'])->andReturn(1);
        $result = $builder->from('users')->where('email', '=', 'foo')->orderBy('id')->take(1)->delete();
        $this->assertEquals(1, $result);
        
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from `users` where `email` = ? order by `id` asc limit 1', ['foo'])->andReturn(1);
        $result = $builder->from('users')->where('email', '=', 'foo')->orderBy('id')->take(1)->delete();
        $this->assertEquals(1, $result);
        
        $builder = $this->getSqlServerBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from [users] where [email] = ?', ['foo'])->andReturn(1);
        $result = $builder->from('users')->where('email', '=', 'foo')->delete();
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
    
  
    
    public function testMySqlWrapping()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from `users`', $builder->toSql());
    }
    
    public function testMySqlUpdateWrappingJson()
    {
        $grammar = new MySqlGrammar;
        $processor = m::mock(Processor::class);
        
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
        ->method('update')
        ->with(
            'update `users` set `name` = json_set(`name`, \'$."first_name"\', ?), `name` = json_set(`name`, \'$."last_name"\', ?) where `active` = ?',
            ['John', 'Doe', 1]
            );
        
        $builder = new Builder($connection, $grammar, $processor);
        
        $builder->from('users')->where('active', '=', 1)->update(['name->first_name' => 'John', 'name->last_name' => 'Doe']);
    }
    
    public function testMySqlUpdateWrappingNestedJson()
    {
        $grammar = new MySqlGrammar;
        $processor = m::mock(Processor::class);
        
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
        ->method('update')
        ->with(
            'update `users` set `meta` = json_set(`meta`, \'$."name"."first_name"\', ?), `meta` = json_set(`meta`, \'$."name"."last_name"\', ?) where `active` = ?',
            ['John', 'Doe', 1]
            );
        
        $builder = new Builder($connection, $grammar, $processor);
        
        $builder->from('users')->where('active', '=', 1)->update(['meta->name->first_name' => 'John', 'meta->name->last_name' => 'Doe']);
    }
    
    public function testMySqlUpdateWithJsonPreparesBindingsCorrectly()
    {
        $grammar = new MySqlGrammar;
        $processor = m::mock(Processor::class);
        
        $connection = m::mock(ConnectionInterface::class);
        $connection->shouldReceive('update')
        ->once()
        ->with(
            'update `users` set `options` = json_set(`options`, \'$."enable"\', false), `updated_at` = ? where `id` = ?',
            ['2015-05-26 22:02:06', 0]
            );
        $builder = new Builder($connection, $grammar, $processor);
        $builder->from('users')->where('id', '=', 0)->update(['options->enable' => false, 'updated_at' => '2015-05-26 22:02:06']);
        
        $connection->shouldReceive('update')
        ->once()
        ->with(
            'update `users` set `options` = json_set(`options`, \'$."size"\', ?), `updated_at` = ? where `id` = ?',
            [45, '2015-05-26 22:02:06', 0]
            );
        $builder = new Builder($connection, $grammar, $processor);
        $builder->from('users')->where('id', '=', 0)->update(['options->size' => 45, 'updated_at' => '2015-05-26 22:02:06']);
        
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('update')->once()->with('update `users` set `options` = json_set(`options`, \'$."size"\', ?)', [null]);
        $builder->from('users')->update(['options->size' => null]);
        
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('update')->once()->with('update `users` set `options` = json_set(`options`, \'$."size"\', 45)', []);
        $builder->from('users')->update(['options->size' => new Raw('45')]);
    }
    
   
    public function testMySqlWrappingJsonWithString()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->sku', '=', 'foo-bar');
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."sku"\')) = ?', $builder->toSql());
        $this->assertCount(1, $builder->getRawBindings()['where']);
        $this->assertEquals('foo-bar', $builder->getRawBindings()['where'][0]);
    }
    
    public function testMySqlWrappingJsonWithInteger()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price', '=', 1);
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."price"\')) = ?', $builder->toSql());
    }
    
    public function testMySqlWrappingJsonWithDouble()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price', '=', 1.5);
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."price"\')) = ?', $builder->toSql());
    }
    
    public function testMySqlWrappingJsonWithBoolean()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."available"\') = true', $builder->toSql());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where(new Raw("items->'$.available'"), '=', true);
        $this->assertEquals("select * from `users` where items->'$.available' = true", $builder->toSql());
    }
    
    public function testMySqlWrappingJsonWithBooleanAndIntegerThatLooksLikeOne()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true)->where('items->active', '=', false)->where('items->number_available', '=', 0);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."available"\') = true and json_extract(`items`, \'$."active"\') = false and json_unquote(json_extract(`items`, \'$."number_available"\')) = ?', $builder->toSql());
    }
    
    public function testJsonPathEscaping()
    {
        $expectedWithJsonEscaped = <<<SQL
select json_unquote(json_extract(`json`, '$."\'))#"'))
SQL;
        
        $builder = $this->getMySqlBuilder();
        $builder->select("json->'))#");
        $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());
        
        $builder = $this->getMySqlBuilder();
        $builder->select("json->\'))#");
        $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());
        
        $builder = $this->getMySqlBuilder();
        $builder->select("json->\\'))#");
        $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());
        
        $builder = $this->getMySqlBuilder();
        $builder->select("json->\\\'))#");
        $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());
    }
    
    public function testMySqlWrappingJson()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereRaw('items->\'$."price"\' = 1');
        $this->assertEquals('select * from `users` where items->\'$."price"\' = 1', $builder->toSql());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
        $this->assertEquals('select json_unquote(json_extract(`items`, \'$."price"\')) from `users` where json_unquote(json_extract(`users`.`items`, \'$."price"\')) = ? order by json_unquote(json_extract(`items`, \'$."price"\')) asc', $builder->toSql());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."price"."in_usd"\')) = ?', $builder->toSql());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."price"."in_usd"\')) = ? and json_unquote(json_extract(`items`, \'$."age"\')) = ?', $builder->toSql());
    }
    
   
    
    public function testMySqlSoundsLikeOperator()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('name', 'sounds like', 'John Doe');
        $this->assertEquals('select * from `users` where `name` sounds like ?', $builder->toSql());
        $this->assertEquals(['John Doe'], $builder->getBindings());
    }
    
  
    
    public function testProvidingNullWithOperatorsBuildsCorrectly()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', null);
        $this->assertEquals('select * from "users" where "foo" is null', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '=', null);
        $this->assertEquals('select * from "users" where "foo" is null', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '!=', null);
        $this->assertEquals('select * from "users" where "foo" is not null', $builder->toSql());
        
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '<>', null);
        $this->assertEquals('select * from "users" where "foo" is not null', $builder->toSql());
    }
   
   
  
    
   
    public function testUppercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'AND');
        $this->assertEquals('select * from "users" where "name" = ?', $builder->toSql());
    }
    
    public function testLowercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'and');
        $this->assertEquals('select * from "users" where "name" = ?', $builder->toSql());
    }
    
    public function testCaseInsensitiveLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'And');
        $this->assertEquals('select * from "users" where "name" = ?', $builder->toSql());
    }
    
  
    
    public function testWhereJsonContainsMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonContains('options', ['en']);
        $this->assertEquals('select * from `users` where json_contains(`options`, ?)', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonContains('users.options->languages', ['en']);
        $this->assertEquals('select * from `users` where json_contains(`users`.`options`, ?, \'$."languages"\')', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContains('options->languages', new Raw("'[\"en\"]'"));
        $this->assertEquals('select * from `users` where `id` = ? or json_contains(`options`, \'["en"]\', \'$."languages"\')', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }
    
   
    public function testWhereJsonDoesntContainMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonDoesntContain('options->languages', ['en']);
        $this->assertEquals('select * from `users` where not json_contains(`options`, ?, \'$."languages"\')', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContain('options->languages', new Raw("'[\"en\"]'"));
        $this->assertEquals('select * from `users` where `id` = ? or not json_contains(`options`, \'["en"]\', \'$."languages"\')', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }
    
 
    public function testWhereJsonLengthMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonLength('options', 0);
        $this->assertEquals('select * from `users` where json_length(`options`) = ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonLength('users.options->languages', '>', 0);
        $this->assertEquals('select * from `users` where json_length(`users`.`options`, \'$."languages"\') > ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', new Raw('0'));
        $this->assertEquals('select * from `users` where `id` = ? or json_length(`options`, \'$."languages"\') = 0', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
        
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', '>', new Raw('0'));
        $this->assertEquals('select * from `users` where `id` = ? or json_length(`options`, \'$."languages"\') > 0', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }
    
   
    */
}