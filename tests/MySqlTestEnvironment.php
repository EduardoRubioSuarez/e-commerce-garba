<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MySqlTestEnvironment
{
    public static function configure(): void
    {
        if (! app()->environment('testing') || ! filter_var(env('MYSQL_CONCURRENCY_TESTS', false), FILTER_VALIDATE_BOOL)
            || env('MYSQL_TEST_DATABASE') !== 'garba_coupon_tests' || ! is_string(env('MYSQL_TEST_USERNAME'))
            || env('MYSQL_TEST_USERNAME') === '') {
            throw new RuntimeException('Concurrencia bloqueada: configura MYSQL_TEST_* para la BD exclusiva garba_coupon_tests y usa phpunit.mysql.xml.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.url' => null,
            'database.connections.mysql.host' => env('MYSQL_TEST_HOST', '127.0.0.1'),
            'database.connections.mysql.port' => env('MYSQL_TEST_PORT', '3306'),
            'database.connections.mysql.database' => 'garba_coupon_tests',
            'database.connections.mysql.username' => env('MYSQL_TEST_USERNAME'),
            'database.connections.mysql.password' => env('MYSQL_TEST_PASSWORD', ''),
            'database.connections.mysql.engine' => 'InnoDB', 'mail.default' => 'array',
            'services.stripe.enabled' => false, 'services.stripe.livemode' => false,
            'services.stripe.account_id' => 'acct_fixture', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('mysql');
        try {
            $actual = DB::selectOne('SELECT DATABASE() AS database_name, VERSION() AS version');
            if ($actual->database_name !== 'garba_coupon_tests') {
                throw new RuntimeException('Unsafe test database.');
            }
        } catch (\Throwable) {
            throw new RuntimeException('Concurrencia bloqueada: no hay acceso MySQL a garba_coupon_tests. No se ejecutaron migraciones.');
        }
    }
}
