# Logging 與可觀測性

所有服務只印 stdout，Alloy 從 Docker 收進 Loki，Grafana 查詢。一個網址看所有層級的 log：

| 環境 | 網址 |
|---|---|
| 開發 | http://localhost:3001（免登入） |
| 正式 | `ssh -L 3001:127.0.0.1:3001 <vm>` 後開 http://localhost:3001，帳號 admin，密碼為 `.env` 的 `GRAFANA_ADMIN_PASSWORD` |

預設 dashboard：**Pawfit · Logs**（依 service / level / request_id / 文字篩選，另有 5xx、慢請求、對外呼叫、例外面板）。
進階查詢用左側 **Explore**。

```
瀏覽器 ──(錯誤回報 POST /_log/client)──▶ web ─┐
Caddy ── JSON access log ─────────────────────┤
web（Nuxt SSR）── JSON ───────────────────────┼─▶ 容器 stdout ─▶ Alloy ─▶ Loki ─▶ Grafana
api（Laravel）── JSON ────────────────────────┤
worker（queue）── JSON ───────────────────────┘
```

設定檔都在 `infra/observability/`：`loki/loki.yaml`（保留 30 天）、`alloy/config.alloy`（收集與正規化）、`grafana/`（datasource 與 dashboard provisioning）。

## 統一格式

每行一個 JSON，欄位順序固定：

```json
{
  "ts": "2026-09-11T13:05:53.123456+00:00",
  "level": "info",
  "service": "api",
  "event": "http.response",
  "message": "http response",
  "request_id": "6b1c2a5e-3f5a-4b7e-9c2d-1e2f3a4b5c6d",
  "user_id": "01a0906e-3452-72d4-bead-98012b24e794",
  "context": { "method": "GET", "path": "/api/me", "status": 200, "duration_ms": 12 },
  "exception": { "class": "...", "message": "...", "file": "...", "line": 1, "trace": ["..."] }
}
```

| 欄位 | 說明 |
|---|---|
| `ts` | RFC 3339，含毫秒或微秒 |
| `level` | `debug` / `info` / `warning` / `error` / `critical`（小寫） |
| `service` | `caddy` / `web` / `browser` / `api` / `worker` |
| `event` | 事件名稱（下表）。沒有指定的一般 log 為 `log`，非 JSON 的原始輸出為 `raw` |
| `message` | 人看的一句話 |
| `request_id` | 同一個 HTTP 請求在各層共用的 ID（見下節） |
| `user_id` | 已登入者的 user id，沒有則 `null` |
| `context` | 各 service / event 自訂欄位，全部放這裡 |
| `exception` | 只有例外類事件有：`class`、`message`、`file`、`line`、`trace`（前 15 層）、`previous` |

Loki 中 `service` 與 `level` 是 label（可用 `{service="api", level="error"}` 選），
`event`、`request_id`、`user_id`、`container` 是 structured metadata（可直接 `| event="http.response"` 過濾，不必先 `| json`）。
`context` 內的欄位要 `| json` 之後以 `context_<key>` 使用，例如 `| json | context_status >= 500`。

## 事件一覽

### 共同

| event | level | 誰 | context 欄位 |
|---|---|---|---|
| `http.request` | info | api, web | `method` `path` `ip` `user_agent` `referer` `query_keys`(api) `content_length`(api) |
| `http.response` | info / warning(4xx) / error(5xx) | api, web | `method` `path` `status` `duration_ms` `route`(api) `bytes`(api) |
| `http.outbound` | info / warning / error | api, worker, web | `client`(`http` `s3` `api`) `method` `host`(api) `path` `status` `duration_ms` `error` |
| `exception` | error | api, worker, web | api：`method` `path` `route` `userId`；web：`method` `path` `status` |
| `log` | 任意 | 任意 | 沒指定 event 的一般 `Log::info()` 等 |

### Caddy

| event | context |
|---|---|
| `http.access` | Caddy 原生 access log 整包：`request{method, uri, host, remote_ip, headers}` `status` `duration` `size` `resp_headers` |

### api / worker（Laravel）

| event | level | context |
|---|---|---|
| `job.processed` | info | `job` `queue` `connection` `attempts` `duration_ms` |
| `job.failed` | error | 同上 + `exception` |
| 自訂 | 任意 | `Log::warning('watermark failed', ['event' => 'media.watermark_failed', 'media' => $id])` |

### web（Nuxt SSR）

| event | level | 說明 |
|---|---|---|
| `http.error` | warning | SSR 端 `createError()` 的 4xx（例如非管理員進 /admin 的 404） |

### browser（瀏覽器端，經 `/_log/client`）

| event | 說明 |
|---|---|
| `client.vue_error` | Vue 元件錯誤（`vue:error`），`context.info` 為 Vue 的錯誤資訊 |
| `client.app_error` | Nuxt `app:error` |
| `client.window_error` | 全域未攔截例外，`context.source/line/column` |
| `client.unhandled_rejection` | 未處理的 Promise rejection |
| `client.api_error` | API 5xx 或斷線；`context.api_request_id` 是那次 API 呼叫的 request_id |
| `client.<自訂>` | `useNuxtApp().$clientLog('event_name', 'error', 'message', { ... }, err)` |

browser 事件的 `request_id` 是**渲染這一頁的 SSR 請求**的 ID，`context.url` 為當時網址。
端點有每 IP 每分鐘 60 筆、單筆 16KB、白名單欄位等限制。

## request_id 如何貫穿

1. Caddy 對每個進來的請求產生 uuid，以 `X-Request-Id` 往後傳給 web 或 api，也回給瀏覽器（response header）。
2. web（Nitro）沿用該 ID 記 `http.request/response`；SSR 期間呼叫 Laravel 時再以 `X-Request-Id` 帶過去。
3. api（Laravel）沿用該 ID 放進 `Context`，這次請求內所有 log 自動帶上；派發的 queue job 也會帶著（Laravel Context 會隨 job 序列化），所以 worker 的 `job.processed` 能對回原請求。
4. 瀏覽器端錯誤回報帶 `page_request_id`，連回渲染這一頁的請求。

在 Grafana 點任一筆 log 的 `request_id` 旁的連結，或在 dashboard 的 request_id 欄位貼上 ID，就能看到同一請求在所有服務的紀錄。

## 常用查詢（Explore）

```logql
# 某個請求的完整軌跡
{service=~".+"} | request_id="6b1c2a5e-..."

# api 5xx
{service="api"} | event="http.response" | json | context_status >= 500

# 超過 1 秒的請求
{service=~"api|web"} | event="http.response" | json | context_duration_ms > 1000

# 對 S3 / MinIO 的呼叫失敗
{service=~"api|worker"} | event="http.outbound" | json | context_client="s3" | context_status >= 400

# 某位使用者最近的所有活動
{service=~".+"} | user_id="01a0906e-..."

# 瀏覽器端錯誤
{service="browser"}

# 每分鐘錯誤數（依 service）
sum by (service) (count_over_time({level=~"error|critical"}[1m]))
```

## 各層怎麼寫 log

**Laravel**：照常用 `Log::info()`，在 context 放 `event` 就會變成該事件；其餘 key 進 `context`。
Throwable 放在任何 key（慣例 `exception`）會被抽成 `exception` 區塊。

```php
Log::warning('watermark failed', ['event' => 'media.watermark_failed', 'media' => $media->id, 'exception' => $e]);
```

**Nuxt server**：`logServer(level, event, message, { request_id, user_id, context, error })`（`#shared/utils/log`）。
**Nuxt browser**：`useNuxtApp().$clientLog(event, level, message, context, error)`。

## 保留與容量

- 容器 stdout：Docker json-file 每容器 20MB × 5 檔（`compose.yaml` 的 `x-logging`）。
- Loki：保留 30 天（`loki.yaml` 的 `retention_period: 720h`），資料在 `loki_data` volume。
- Grafana：設定與自建 dashboard 在 `grafana_data` volume；provisioning 的 dashboard 改檔案即生效（重啟 grafana）。

## 沒收進來的

- Nginx（api 容器內）的 access log 已關閉（`NGINX_ACCESS_LOG=/dev/null`），由 Caddy 與 Laravel 負責。
- Postgres / Redis / MinIO 的 stdout 會照原文收（`event=raw`），不做解析。
- Loki / Alloy / Grafana 自己的 log 不收。
