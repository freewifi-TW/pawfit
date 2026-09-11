<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 保險：測試只准跑在 sqlite in-memory，避免 RefreshDatabase 清掉開發資料庫
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('測試必須使用 sqlite（目前：'.DB::connection()->getDriverName().'）。請檢查 tests/bootstrap.php 與 phpunit.xml。');
        }
    }
}
