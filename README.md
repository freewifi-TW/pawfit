# Pawfit（爪搭）

獸圈用的獸設檔案庫：多視角圖庫、精準色票、標籤，一個連結分享完整設定。

- 需求與設計文件：`Pawfit (爪搭).md`、`SASD-Phase*.md`
- 視覺走向與可點 demo：`design/pawfit-demo.html`（走向 C：溫暖社群）

## 架構

```
瀏覽器 → Caddy(:8080)
           ├─ /api/*、/sanctum/*  → apps/api   Laravel 13 / PHP 8.4（Nginx + PHP-FPM）
           └─ 其餘                → apps/web   Nuxt 4 SSR
        Postgres 16 · Redis 7 · MinIO（本機模擬 Cloudflare R2）
```

Same-domain 路由，前後端共用 cookie，沒有 CORS 問題。

## 開發環境

需求：Docker Desktop（WSL2 後端）、Node 20、pnpm。

```bash
cp .env.example .env
cp apps/api/.env.example apps/api/.env      # 再執行 docker compose exec api php artisan key:generate
docker compose up -d                        # 第一次會建 api 映像，需數分鐘
docker compose logs -f web api              # 看啟動狀況
```

| 服務 | 網址 |
|---|---|
| 網站 | http://localhost:8080 |
| API 健康檢查 | http://localhost:8080/up |
| MinIO 管理介面 | http://localhost:9001（帳密見 .env） |
| Postgres | localhost:54329 |

常用指令：

```bash
docker compose exec api php artisan migrate
docker compose exec api php artisan tinker
docker compose exec --user root api composer require <package>   # 見下方「Windows 注意事項」
docker compose exec web pnpm add <package>
docker compose down -v                      # 連資料一起清掉
```

### Windows 注意事項

- Windows 的 bind mount 在容器內固定為 `root:root 0755`，PHP 以 `www-data` 執行時無法寫入。因此 `storage/` 與 `bootstrap/cache` 掛的是 named volume（`api_storage`、`api_bootstrap_cache`），不會出現在本機資料夾。
- Laravel log 走 `LOG_CHANNEL=stderr`，用 `docker compose logs -f api worker` 看。
- 會寫入 `vendor/`、`composer.lock` 的指令（`composer require`、`composer update`）要加 `--user root`。
- `AWS_ENDPOINT` 指向 `host.docker.internal:9000`，讓 Laravel 產生的簽名 URL 在容器內與瀏覽器都能使用。

## 正式部署（GCP VM）

```bash
# VM 上：裝 Docker + compose plugin，clone 專案，填 .env 與 apps/api/.env
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
```

Caddy 會自動申請 Let's Encrypt 憑證；圖片改指向 Cloudflare R2，不啟動 MinIO。細節見 `compose.prod.yaml` 註解。

## 目錄

```
apps/web     Nuxt 4 + Nuxt UI（前端，SSR）
apps/api     Laravel 13（API）
infra/       Caddyfile、Dockerfile
design/      設計原型
compose.yaml            開發環境
compose.prod.yaml       正式環境覆蓋
```
