<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * 保險：測試只准跑在 sqlite in-memory，避免 RefreshDatabase 清掉開發資料庫。
     *
     * 一定要在 setUpTraits() 之前檢查：RefreshDatabase 的 migrate:fresh 就是在 setUpTraits() 裡跑的，
     * 放在 setUp() 的 parent::setUp() 之後會來不及（config 被 cache 住、DB_CONNECTION 變 pgsql 時就會清掉開發資料庫）。
     */
    protected function setUpTraits(): array
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('測試必須使用 sqlite（目前：'.DB::connection()->getDriverName().'）。請檢查 tests/bootstrap.php、phpunit.xml，以及 bootstrap/cache 是否殘留 config 快取（php artisan config:clear）。');
        }

        return parent::setUpTraits();
    }
}
