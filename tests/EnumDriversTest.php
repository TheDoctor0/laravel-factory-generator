<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests;

use Mockery;
use Illuminate\Database\Connection;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use TheDoctor0\LaravelFactoryGenerator\Database\EnumMysql;
use TheDoctor0\LaravelFactoryGenerator\Database\EnumPgsql;
use TheDoctor0\LaravelFactoryGenerator\Database\EnumSqlite;
use TheDoctor0\LaravelFactoryGenerator\Database\EnumValues;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\Customer;

class EnumDriversTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->enum('status', ['active', 'inactive']);
        });
    }

    #[Test]
    public function sqlite_driver_extracts_enum_values_from_check_constraints(): void
    {
        $this->assertSame(
            ['active', 'inactive'],
            (new EnumSqlite(new Customer(), 'status'))->values()
        );
    }

    #[Test]
    public function sqlite_driver_returns_null_for_columns_without_check_constraints(): void
    {
        $this->assertNull((new EnumSqlite(new Customer(), 'name'))->values());
    }

    #[Test]
    public function enum_values_uses_the_sqlite_driver_for_sqlite_connections(): void
    {
        $this->assertSame(['active', 'inactive'], EnumValues::get(new Customer(), 'status'));
    }

    #[Test]
    public function enum_values_returns_null_for_unsupported_drivers(): void
    {
        $this->assertNull(EnumValues::get($this->modelOnDriver('sqlsrv'), 'status'));
    }

    #[Test]
    public function mysql_driver_parses_enum_values_from_the_column_definition(): void
    {
        $rows = [(object) ['Type' => "enum('active','inactive')"]];

        $this->fakeConnection($rows, function ($sql): void {
            $this->assertStringContainsString('SHOW COLUMNS FROM `customers`', $sql);
            $this->assertStringContainsString("Field = 'status'", $sql);
        });

        $this->assertSame(
            ['active', 'inactive'],
            (new EnumMysql(new Customer(), 'status'))->values()
        );
    }

    #[Test]
    public function enum_values_uses_the_mysql_driver_for_mysql_connections(): void
    {
        $this->fakeConnection([(object) ['Type' => "enum('a','b')"]]);

        $this->assertSame(['a', 'b'], EnumValues::get($this->modelOnDriver('mysql'), 'status'));
    }

    #[Test]
    public function pgsql_driver_reads_enum_values_from_check_constraints(): void
    {
        $rows = [(object) ['matches' => 'active'], (object) ['matches' => 'inactive']];

        $this->fakeConnection($rows, function ($sql): void {
            $this->assertStringContainsString("conname = 'customers_status_check'", $sql);
            $this->assertStringContainsString("conrelid = 'public.customers'::regclass", $sql);
        });

        $this->assertSame(
            ['active', 'inactive'],
            (new EnumPgsql(new Customer(), 'status'))->values()
        );
    }

    #[Test]
    public function pgsql_driver_returns_null_when_no_check_constraint_matches(): void
    {
        $this->fakeConnection([]);

        $this->assertNull((new EnumPgsql(new Customer(), 'status'))->values());
    }

    #[Test]
    public function enum_values_uses_the_pgsql_driver_for_pgsql_connections(): void
    {
        $this->fakeConnection([(object) ['matches' => 'a']]);

        $this->assertSame(['a'], EnumValues::get($this->modelOnDriver('pgsql'), 'status'));
    }

    protected function fakeConnection(array $rows, ?callable $inspectSql = null): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn(Mockery::mock(Grammar::class));
        $connection->shouldReceive('select')->andReturnUsing(function ($sql) use ($rows, $inspectSql) {
            if ($inspectSql) {
                $inspectSql($sql);
            }

            return $rows;
        });

        DB::shouldReceive('raw')->andReturnUsing(fn ($sql) => new Expression($sql));
        DB::shouldReceive('connection')->andReturn($connection);
    }

    protected function modelOnDriver(string $driver): Customer
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn($driver);
        $connection->shouldReceive('getTablePrefix')->andReturn('');

        $model = Mockery::mock(Customer::class)->makePartial();
        $model->shouldReceive('getConnection')->andReturn($connection);
        $model->shouldReceive('getConnectionName')->andReturn(null);

        return $model;
    }
}
