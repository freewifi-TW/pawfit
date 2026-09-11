# apps/api — Laravel 13 API

本機**沒有** PHP 與 Composer，所有指令一律透過 Docker 在 `api` 容器內執行（於 repo 根目錄）：

```bash
docker compose exec api php artisan <cmd>
docker compose exec api php artisan test --compact
docker compose exec --user root api ./vendor/bin/pint          # 寫入檔案需 root（Windows bind mount）
docker compose exec --user root -e COMPOSER_ALLOW_SUPERUSER=1 api composer require <pkg>
```

不要嘗試在主機安裝 PHP／Composer／Laravel Boost。

## 專案慣例

- 所有內容可見性判斷只能寫在 `app/Services/Visibility.php`（SASD §0.1 原則 3），controller 與前端不得自行判斷。
- Migration 一律 additive；enum 類欄位用 string，不用 Postgres enum。
- 圖片只經 `GET /api/img/{media}` 出口（302 簽名 URL），不得直接回傳 R2/MinIO 網址。
- API Resource 不包 `data` 外層（`JsonResource::withoutWrapping()`）；分頁集合例外。
- 設定與 feature flag 放 `config/pawfit.php`，以環境變數控制。
- 測試用 sqlite in-memory（phpunit.xml），`Storage::fake('s3')`、`Queue::fake()`。
