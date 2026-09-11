# Pawfit（爪搭）

獸圈用的獸設檔案庫：多視角圖庫、精準色票、標籤，一個連結分享完整設定。

- 需求與設計文件：`Pawfit (爪搭).md`、`SASD-Phase*.md`
- 視覺走向與可點 demo：`design/pawfit-demo.html`（走向 C：溫暖社群）

## 架構

```
瀏覽器 → Caddy(:8080)
           ├─ /api/*、/sanctum/*  → apps/api   Laravel 13 / PHP 8.4（Nginx + PHP-FPM）
           └─ 其餘                → apps/web   Nuxt 4 SSR（Node 22）
        Postgres 16 · Redis 7（queue / cache / session）· MinIO（本機模擬 Cloudflare R2）
        Loki + Alloy + Grafana(:3001)：所有容器 stdout 統一 JSON 格式集中查詢（docs/logging.md）
```

Same-domain 路由，前後端共用 cookie（Sanctum stateful），沒有 CORS 問題。圖片一律經 `GET /api/img/:id` 做可見性判斷後 302 到短效簽名 URL；所有可見性邏輯集中在 `apps/api/app/Services/Visibility.php`。

## Phase 1 功能狀態

| 里程碑 | 內容 | 狀態 |
|---|---|---|
| M1 骨架 | Docker Compose、Google 登入、onboarding（Pawfit ID + 條款） | ✅ |
| M2 獸設核心 | 多獸設 CRUD、色票、標籤、三段隱私、代表獸設、個人主頁 `/u/:id` | ✅ |
| M3 圖庫 | presigned PUT 直傳 → queue 產展示版/縮圖、分類、credit、單圖隱私與 NSFW、拖曳排序、ACL 圖片路由 | ✅ |
| M4 分享包 | `/s/:slug` SSR + OG meta、重新生成/停用連結、浮水印版圖檔 | ✅ |
| M5 治理 | 18+ 聲明、NSFW 顯示矩陣全站生效、檢舉、管理後台 `/admin`、條款/守則/隱私頁 | ✅ |

尚未拍板／未做：Pawfit ID 更名（R-2，目前鎖定不可改）、帳號刪除與資料匯出、Postgres 自動備份排程（SASD §2.5）、條款文字定稿（R-5）。

## 開發環境

需求：Docker Desktop（WSL2 後端）。本機不需安裝 Node、PHP 或 Composer。

```bash
cp .env.example .env
cp apps/api/.env.example apps/api/.env
docker compose up -d --build                # 第一次會建 api 映像，需數分鐘
docker compose exec api php artisan key:generate
docker compose exec api php artisan db:seed --class=DemoSeeder   # 示範帳號與獸設（可略）
docker compose logs -f web api              # 看啟動狀況
```

| 服務 | 網址 |
|---|---|
| 網站 | http://localhost:8080 |
| 示範分享頁 | http://localhost:8080/s/demoEmber1（需先跑 DemoSeeder） |
| API 健康檢查 | http://localhost:8080/up |
| MinIO 管理介面 | http://localhost:9001（帳密見 .env） |
| Grafana（所有服務的 log） | http://localhost:3001（開發環境免登入；用法見 `docs/logging.md`） |
| Postgres | localhost:54329 |

### 本機登入

沒有 Google 憑證時，登入頁下方有「開發模式」區塊可用 email 直接登入（`FEATURE_DEV_LOGIN=true` 且 `APP_ENV=local`）：

- `demo@pawfit.local`：DemoSeeder 建立的示範帳號（@firefox_ash）
- `admin@pawfit.local`：`ADMIN_EMAILS` 內的管理員，可進 `/admin`

要用真的 Google 登入：到 GCP Console 建 OAuth 2.0 用戶端，已授權的重新導向 URI 填 `http://localhost:8080/api/auth/google/callback`，把 client id/secret 填進 `apps/api/.env` 的 `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`。

### 常用指令

```bash
docker compose exec api php artisan migrate
docker compose exec api php artisan test --compact           # 一律跑在 sqlite in-memory，不會動到開發資料庫
docker compose exec api php artisan tinker
docker compose exec --user root api composer require <pkg>   # 見「Windows 注意事項」；裝完要 docker compose restart worker
docker compose exec --user root api ./vendor/bin/pint
docker compose exec web pnpm lint
docker compose exec web pnpm typecheck
docker compose exec web pnpm add <package>
docker compose down -v                                        # 連資料一起清掉
```

### Windows 注意事項

- Windows 的 bind mount 在容器內固定為 `root:root 0755`，PHP 以 `www-data` 執行時無法寫入。因此 `storage/` 與 `bootstrap/cache` 掛的是 named volume（`api_storage`、`api_bootstrap_cache`），不會出現在本機資料夾。
- Laravel log 走 `LOG_CHANNEL=stderr`（統一 JSON 格式），集中在 Grafana 看；臨時要看原始輸出用 `docker compose logs -f api worker`。
- 會寫入 `vendor/`、`composer.lock` 的指令（`composer require`、`composer update`、`pint`）要加 `--user root`。
- `AWS_ENDPOINT` 指向 `host.docker.internal:9000`，讓 Laravel 產生的簽名 URL 在容器內與瀏覽器都能使用。
- worker 是長駐程序：`composer require` 之後要 `docker compose restart worker`，否則新類別找不到。

### Log

所有容器只印 stdout（統一 JSON 格式：`ts / level / service / event / message / request_id / user_id / context / exception`），Alloy 收進 Loki，開 **http://localhost:3001** 用 Grafana 查。
Caddy 對每個請求產生 `X-Request-Id` 貫穿 web、api、worker 與瀏覽器端錯誤回報，貼上 ID 就能看到同一請求在所有服務的紀錄。
事件一覽、LogQL 範例、各層怎麼寫 log 見 `docs/logging.md`。

### 測試

- API：`tests/Feature/VisibilityTest.php`（隱私 × NSFW 矩陣）、`tests/Feature/Phase1FlowTest.php`（onboarding → 上傳 → 分享 → 檢舉 → 後台）。
- 測試強制使用 sqlite in-memory：`tests/bootstrap.php` 會覆蓋 Docker 注入的 `DB_*` 環境變數，`tests/TestCase.php` 若偵測到非 sqlite 會直接中止，避免 `RefreshDatabase` 清掉開發資料庫。

## 正式部署（GCP VM）

```bash
# VM 上：裝 Docker + compose plugin，clone 專案，填 .env 與 apps/api/.env
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
```

Caddy 會自動申請 Let's Encrypt 憑證；圖片改指向 Cloudflare R2，不啟動 MinIO。細節見 `compose.prod.yaml` 註解。

上線前務必：

- `apps/api/.env`：`FEATURE_DEV_LOGIN=false`、`APP_ENV=production`、`SANCTUM_STATEFUL_DOMAINS` 與 `SESSION_DOMAIN` 改成正式網域、`AWS_*` 改成 R2 端點與私有 bucket、`ADMIN_EMAILS` 填站方帳號。
- R2 bucket 要設定 CORS，允許正式網域對 `PUT` 直傳（`AllowedMethods: PUT`、`AllowedHeaders: Content-Type`）。
- Google OAuth 用戶端加入正式網域的 callback。
- `.env`：設定 `GRAFANA_ADMIN_PASSWORD`（正式環境 Grafana 關閉匿名登入，且只綁 127.0.0.1，從外部用 SSH tunnel 連）。

## 目錄

```
apps/web     Nuxt 4 + Nuxt UI（前端，SSR）
apps/api     Laravel 13（API）— 見 apps/api/CLAUDE.md 的開發慣例
infra/       Caddyfile、Dockerfile、observability/（Loki、Alloy、Grafana 設定）
docs/        logging.md（log 格式、事件、查詢方式）
design/      設計原型
compose.yaml            開發環境
compose.prod.yaml       正式環境覆蓋
```
