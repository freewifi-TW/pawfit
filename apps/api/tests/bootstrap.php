<?php

/*
 |------------------------------------------------------------------
 | 測試 bootstrap
 |------------------------------------------------------------------
 | 容器（Docker）會把 DB_CONNECTION=pgsql 等變數注入成真實環境變數，
 | 而 Laravel 的 env() 先讀 $_SERVER；phpunit.xml 的 <env> 只寫入 $_ENV 與 putenv，
 | 於是測試會連到開發資料庫並被 RefreshDatabase 清空。
 | 這裡把 phpunit.xml 設定的值同步進 $_SERVER，並拔掉只對 Postgres 有意義的連線變數。
 */

require __DIR__.'/../vendor/autoload.php';

foreach ([
    'APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL',
    'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'MAIL_MAILER', 'BROADCAST_CONNECTION', 'LOG_CHANNEL',
] as $key) {
    if (array_key_exists($key, $_ENV)) {
        $_SERVER[$key] = $_ENV[$key];
        putenv("{$key}={$_ENV[$key]}");
    }
}

foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'REDIS_HOST'] as $key) {
    unset($_SERVER[$key], $_ENV[$key]);
    putenv($key);
}
