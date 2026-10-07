# Pawfit（爪搭）

獸圈用的獸設檔案庫：多視角圖庫、精準色票、標籤，一個連結分享完整設定。

- 文件都在 `docs/`：需求 `Pawfit (爪搭).md`、各階段設計 `SASD-Phase*.md`、log 與觀測 `logging.md`

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
| M6 開放與嵌入 | oEmbed、SVG 色票卡、iframe 卡片、公開 JSON API v1；用戶 opt-in 預設關閉（見 SASD Phase 1 FR-7） | ✅ |
| M7 委託需求單 | 模板雙語描述文字 + 參考圖與色票合成圖，`/c/:slug` 分享給繪師（不含 AI，見 SASD Phase 1 FR-6） | ✅ |

尚未拍板／未做：Pawfit ID 更名（R-2，目前鎖定不可改）、帳號刪除與資料匯出、Postgres 自動備份排程（SASD §2.5）、條款文字定稿（R-5）。

## Phase 2 功能狀態

| 里程碑 | 內容 | flag | 狀態 |
|---|---|---|---|
| M1 好友 | 邀請／接受／解除、封鎖（雙向互不可見）、`friends` 隱私值（獸設與單圖）、分享頁對 friends 的處理、`/friends` 頁與主頁按鈕 | `FEATURE_FRIENDS` | ✅ |
| M2 發文 | 貼文 CRUD（1–10 張圖庫圖＋文字＋標籤）、母標籤繼承、NSFW 鎖定、圖片經 `/api/img?p=` 出口 | `FEATURE_FEED` | ✅ |
| M3 河道與互動 | 好友河道 `/feed`、探索河道 `/feed/explore?tags=`（AND 篩選）、(created_at,id) cursor、讚、單層留言、主頁貼文 | `FEATURE_FEED` | ✅ |
| M4 治理強化 | 檢舉增列 post／comment、同目標 ≥3 位檢舉者自動降能見度（suppressed）、後台下架／恢復貼文與留言 | `FEATURE_FEED` | ✅ |
| M5 換獸頭貼圖 | `/post/new/head-sticker`：MediaPipe Face Detector 在瀏覽器偵測（模型 `public/models/`、WASM 由 Nitro 從 node_modules 提供，不打第三方）、每張臉貼自己或好友的「頭像貼圖素材」、canvas 合成後走 presign 成為 `origin=head_sticker` 的 media 並接到發文頁；原照不離開裝置 | `FEATURE_HEAD_STICKER` | ✅ |

flag 全部在 `apps/api/.env`（程式先部署、功能再啟用）；前端由 `GET /api/features` 取得，關閉時相關頁面與按鈕不出現、API 回 404。封鎖與好友的可見性判斷只在 `Visibility::relation()`（同一請求內快取在 Request attributes 上）。

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

### 開放與嵌入（M6）

全部 opt-in：使用者在「設定 → 開放與嵌入」打開總開關（`users.allow_embed_api`），每隻獸設可單獨覆寫（`fursonas.allow_embed_api`，null＝跟隨）。只輸出「公開 + SFW + 已建立分享連結」的獸設，觀看者永遠視同訪客（`Visibility::embeddable()`）。

| 端點 | 說明 |
|---|---|
| `GET /api/v1/public/users/:pawfitId` | 用戶摘要與可嵌入的公開獸設清單（含分享 slug） |
| `GET /api/v1/public/fursonas/:slug` | 獸設資料（色票、標籤、credit、封面）；`:slug` 為分享連結 slug |
| `GET /api/v1/public/fursonas/:slug/media?cursor=` | 公開 SFW 圖，每頁 24 張，圖片網址為 `/api/img` 路徑 |
| `GET /embed/:slug/palette.svg?theme=light\|dark&layout=row\|grid` | 靜態 SVG 色票卡（Nuxt 代理到 API，快取 10 分鐘） |
| `GET /embed/:slug?theme=auto\|light\|dark&size=sm\|md\|lg` | iframe 卡片頁（無導覽、無 cookie、無登入狀態） |
| `GET /api/oembed?url=&format=json` | oEmbed；分享頁與個人主頁在可嵌入時帶 discovery link |

公開 API：匿名、GET only、CORS `*`、每 IP 每分鐘 60 次（`EMBED_RATE_PER_MINUTE`）、`Cache-Control: public, max-age=300`、一律帶 `X-Robots-Tag: noai, noimageai` 與 `X-Pawfit-Terms`。Caddy 只對 `/embed/*` 放開 `frame-ancestors *`，其餘路由維持 `'self'`；`apps/web/public/robots.txt` 封鎖已知 AI 爬蟲。嵌入程式碼產生器在設定頁。

### 委託需求單（M7）

獸設編輯器「委託」分頁：勾選 1–10 張參考圖、填本次要求 → `POST /api/commission-kits` 建立 **快照**（`commission_kits.snapshot`：角色資料、色票、參考圖 caption／credit、要求）並以模板產生繁中＋英文描述文字（`lang/<locale>/commission.php`，`App\Services\CommissionBrief`），queue 以 Imagick 合成「參考圖＋色票」一頁總覽圖（`App\Services\CommissionSheet`，webp 2048 寬；映像內已裝 `font-noto-cjk`，字型路徑由 `COMMISSION_FONT` 控制）。

- 公開頁 `/c/:slug`（SSR + OG，固定 unlisted、`noindex`）：描述文字可切語言與複製、總覽圖、參考圖原尺寸。NSFW 需求單對訪客整份 404。
- 參考圖經 `GET /api/img/:id?k=<slug>` 出口：在需求單內的圖不再另判隱私（擁有者挑選的快照），但下架與 NSFW 矩陣仍生效；浮水印沿用該獸設分享連結的設定。
- 重新生成＝換 slug（舊連結立即失效）並重跑合成圖；停用＝連結失效、資料保留。之後修改獸設不影響既有需求單。
- 不使用任何 AI 生圖；LLM 潤飾文字（FR-6.7）只預留 `FEATURE_BRIEF_LLM` flag，尚未接供應商。

### 多語系

目前支援繁體中文（預設）與英文。前端用 `@nuxtjs/i18n`（語言檔 `apps/web/i18n/locales/<code>/*.json`，網址不帶語言前綴，記在 cookie），登入者的偏好存在 `users.locale`；後端依 `users.locale` → `Accept-Language` 回對應語言的訊息（`apps/api/lang/<code>/messages.php`）。
新增語言的步驟、語言檔結構與寫法見 `docs/i18n.md`。

### Log

所有容器只印 stdout（統一 JSON 格式：`ts / level / service / event / message / request_id / user_id / context / exception`），Alloy 收進 Loki，開 **http://localhost:3001** 用 Grafana 查。
Caddy 對每個請求產生 `X-Request-Id` 貫穿 web、api、worker 與瀏覽器端錯誤回報，貼上 ID 就能看到同一請求在所有服務的紀錄。
事件一覽、LogQL 範例、各層怎麼寫 log 見 `docs/logging.md`。

### 測試

- API：`tests/Feature/VisibilityTest.php`（隱私 × NSFW 矩陣）、`tests/Feature/Phase1FlowTest.php`（onboarding → 上傳 → 分享 → 檢舉 → 後台）、`tests/Feature/EmbedApiTest.php`（M6：opt-in 開關、公開 API、oEmbed、SVG、標頭）、`tests/Feature/CommissionKitTest.php`（M7：快照、模板文字、NSFW、k= 圖片出口、重新生成／停用、合成圖 job 實際渲染）。
- 測試強制使用 sqlite in-memory：`tests/bootstrap.php` 會覆蓋 Docker 注入的 `DB_*` 環境變數，`tests/TestCase.php` 若偵測到非 sqlite 會直接中止，避免 `RefreshDatabase` 清掉開發資料庫。

## 正式部署（GCP VM）

```bash
# VM 上：裝 Docker + compose plugin，clone 專案，填 .env 與 apps/api/.env
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
```

Caddy 會自動申請 Let's Encrypt 憑證；圖片改指向 Cloudflare R2，不啟動 MinIO。細節見 `compose.prod.yaml` 註解。

上線前務必：

- `apps/api/.env`：`FEATURE_DEV_LOGIN=false`、`APP_ENV=production`、`SANCTUM_STATEFUL_DOMAINS` 與 `SESSION_DOMAIN` 改成正式網域、`AWS_*` 改成 R2 端點與私有 bucket、`ADMIN_EMAILS` 填站方帳號。
- R2 bucket 要設定 CORS，允許正式網域對 `PUT` 直傳（`AllowedMethods: PUT`、`AllowedHeaders: Content-Type`）；換獸頭工具要把貼圖畫進 canvas 再匯出，還需允許 `GET`（`AllowedMethods: GET, PUT`），否則 canvas 會被汙染無法存檔。
- Google OAuth 用戶端加入正式網域的 callback。
- `.env`：設定 `GRAFANA_ADMIN_PASSWORD`（正式環境 Grafana 關閉匿名登入，且只綁 127.0.0.1，從外部用 SSH tunnel 連）。

## 目錄

```
apps/web     Nuxt 4 + Nuxt UI（前端，SSR）
apps/api     Laravel 13（API）— 見 apps/api/CLAUDE.md 的開發慣例
infra/       Caddyfile、Dockerfile、observability/（Loki、Alloy、Grafana 設定）
docs/        需求（Pawfit (爪搭).md）、各階段設計（SASD-Phase*.md）、logging.md、i18n.md
compose.yaml            開發環境
compose.prod.yaml       正式環境覆蓋
```
