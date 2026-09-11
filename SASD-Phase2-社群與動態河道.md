# SASD — Phase 2：社群與動態河道

> PawFit（爪搭）系統分析與設計文件
> 版本 0.2（草稿）｜2026-09-11｜狀態：規劃中
> 上游文件：`Pawfit (爪搭).md`｜上一份：`SASD-Phase1-獸設檔案庫與分享包.md`｜下一份：`SASD-Phase3-2D紙娃娃衣櫥與商城.md`
> 依賴：**僅 Phase 1**（帳號、圖庫、NSFW 治理、`canView()`、檢舉與管理後台皆沿用）；不依賴任何 AI
> 變更紀錄：0.2 由舊版 Phase 3 的 B 線（社群）獨立成篇並提前為 Phase 2；AI 的 A 線移至 `SASD-Phase5-AI生成引擎.md`。

---

## 0. 定位與調整說明

**目標**：讓 Phase 1 的「個人檔案庫」變成「有人在的社群」——好友、發文、雙軌河道，讓獸設與圖庫有地方展示、有人互動。

**為何提前到 Phase 2**：

1. 技術風險最低——全是常規 CRUD 與查詢，沒有任何 go/no-go gate。
2. 只依賴 Phase 1，Phase 1 上線後即可開工，不必等美術產能（Phase 3 素體）或 AI 評估（Phase 5）。
3. 河道是留存與擴散的引擎：先有人，商城（Phase 3）才有創作者與買家，AI（Phase 5）才有觀眾與回饋來源。

**與後續 phase 的接點**：發文的圖片來源在本 phase 為 Phase 1 圖庫圖；Phase 3 上線後，衣櫥匯出的穿搭圖自然成為第二種來源，不需改動本 phase 的資料模型（`post_media` 只認 `media_id`）。

**上線閘門**：功能以 `FEATURE_FRIENDS`／`FEATURE_FEED` flag 分別啟用（好友可先於河道啟用）。全案獨立上線原則見 Phase 1 §0.1。

---

## 1. 系統分析（SA）

### 1.1 對應 User Stories

| # | User Story | 對應 FR |
|---|---|---|
| US-2 | 無縫雙棲身分 | Phase 1 已滿足，本 phase 不新增帳號類型 |
| US-17 | 專屬標籤與圖檔隱私（含限好友） | FR-B1、FR-B2 |
| US-18 | NSFW 河道顯示三段設定 | FR-B4 |
| US-19 | 標籤連動與動態河道 | FR-B3、FR-B5 |

### 1.2 範圍

**In scope**

- 好友：邀請／接受／拒絕／解除；封鎖／解除封鎖
- 啟用 `friends` 隱私值（獸設與單圖）
- 發文（圖＋文字）、母標籤繼承、credit 自動沿用
- 雙軌河道：好友河道＋探索河道（標籤篩選）
- 互動最小集：讚＋單層留言
- 治理強化：檢舉自動優先排序、同目標多檢舉自動降能見度

**Out of scope（列 backlog 或後續 phase）**

- 推薦演算法（初期用戶量撐不起也不需要）
- 巢狀留言、轉發、私訊
- 通知中心（先以「河道內標示新互動」的最簡形式處理，完整通知列 backlog）
- 衣櫥穿搭圖發文（Phase 3 上線後自動接上）
- 第三方圖像審核 API（本 phase 先評估成本，於 M4 決定是否導入）

### 1.3 角色

沿用 Phase 1 三角色。新增語意：

| 角色 | 本 phase 新增能力 |
|---|---|
| 訪客 | 可看探索河道的 SFW 公開貼文；不可互動 |
| 註冊用戶 | 好友、發文、讚、留言、檢舉 |
| 管理員 | 貼文／留言下架、檢舉佇列含貼文與留言 |

### 1.4 功能需求（FR）

**FR-B1 好友與封鎖**

- FR-B1.1 以 Pawfit ID 或個人主頁送出好友邀請，接受制；雙方皆可解除。
- FR-B1.2 封鎖：被封鎖者與封鎖者互不可見（主頁、獸設、貼文、留言、搜尋），封鎖自動解除既有好友關係。
- FR-B1.3 好友列表與待處理邀請頁 `/friends`。

**FR-B2 限好友隱私**

- FR-B2.1 獸設 `visibility` 與單圖 `visibility_override` 增列 `friends` 值（Phase 1 資料模型已預留，migration 為 additive）。
- FR-B2.2 分享頁（`/s/:slug`）對 `friends` 內容的處理：登入且為好友才顯示，訪客與非好友視同 `private`。
- FR-B2.3 Phase 1 既有內容的隱私值不自動變更。

**FR-B3 發文**

- FR-B3.1 貼文＝1–10 張圖（來自圖庫，需為發文者所有）＋文字（≤2000 字）＋標籤。
- FR-B3.2 母標籤繼承：選定獸設後自動帶入其 `tags`，單次發文可增刪，不回寫獸設。
- FR-B3.3 credit 自動沿用圖檔的繪師標記，顯示於貼文詳情，不可移除（只可補充）。
- FR-B3.4 貼文隱私二段：`公開`／`限好友`（不提供 unlisted 與 private，私人收藏走圖庫即可）。
- FR-B3.5 貼文可編輯文字與標籤、可刪除；圖片組成不可事後變更（避免留言脈絡錯亂）。

**FR-B4 NSFW**

- FR-B4.1 發文強制標記 SFW／NSFW；若任一來源圖已標 NSFW，貼文鎖定為 NSFW 不可改回。
- FR-B4.2 河道嚴格執行 Phase 1 FR-5.4 顯示矩陣；模糊模式下縮圖模糊、點擊解鎖後該貼文本次瀏覽內維持顯示。
- FR-B4.3 探索河道對訪客一律隱藏 NSFW（訪客無從完成年齡聲明）。

**FR-B5 雙軌河道**

- FR-B5.1 好友河道 `/feed`：好友的公開與限好友貼文，時間序，cursor 分頁。
- FR-B5.2 探索河道 `/feed/explore`：全站公開貼文，時間序；可依一或多個標籤篩選（AND）。
- FR-B5.3 不做推薦演算法，不做「熱門」排序；標籤篩選＋時間序即為 v1。
- FR-B5.4 河道項目顯示：首圖縮圖、作者、獸設名、標籤、讚數、留言數、NSFW 標記。

**FR-B6 互動**

- FR-B6.1 讚：可讚可收回，每人每貼文一次。
- FR-B6.2 留言：單層、≤500 字、作者與留言者可刪除；不做巢狀回覆。
- FR-B6.3 檢舉沿用 Phase 1 機制，`reports.target_type` 增列 `post`／`comment`。

**FR-B7 治理強化**

- FR-B7.1 檢舉自動優先排序：同一目標累積 ≥3 筆未處理檢舉時，自動在河道暫時降能見度（僅擁有者與管理員可見），並置頂管理佇列。
- FR-B7.2 管理後台新增貼文與留言的下架、恢復操作，全部寫入 `admin_actions`。
- FR-B7.3 評估第三方圖像審核 API 掃描紅線內容：本 phase 產出成本估算與供應商 ToS 確認，不強制導入。

### 1.5 非功能需求

- 河道查詢 p95 < 300ms（萬級貼文、直白 SQL 即可達成）；每頁 20 筆。
- 好友河道首屏 SSR，探索河道可 CSR。
- 所有可見性判斷（friends、封鎖、NSFW、降能見度）一律經 `canView()`，前端不得自行過濾。

---

## 2. 系統設計（SD）

### 2.1 資料模型（新增表，全部 additive）

**friendships**

| 欄位 | 型別 | 說明 |
|---|---|---|
| user_a / user_b | uuid FK→profiles | 正規化為 `user_a < user_b`，複合 UNIQUE |
| status | enum: pending/accepted | |
| requested_by | uuid FK | 誰發出邀請 |
| created_at / accepted_at | timestamptz | |

**blocks**

| 欄位 | 型別 |
|---|---|
| blocker_id / blocked_id | uuid FK，複合 UNIQUE |
| created_at | timestamptz |

**posts**

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | uuid PK | |
| author_id | uuid FK→profiles | |
| fursona_id | uuid FK→fursonas，可空 | 母標籤來源 |
| body | text | |
| tags | text[] | GIN index |
| is_nsfw | boolean | |
| visibility | enum: public/friends | |
| status | enum: active/removed/suppressed | suppressed＝FR-B7.1 自動降能見度 |
| like_count / comment_count | int | 反正規化計數 |
| created_at / updated_at | timestamptz | |

**post_media**

| 欄位 | 型別 |
|---|---|
| post_id / media_id | uuid FK，複合 PK |
| sort | int |

**post_likes**：`post_id, user_id`（複合 PK）, `created_at`。
**post_comments**：`id, post_id, author_id, body, status(active/removed), created_at`。

**既有表擴充**

- `fursonas.visibility`、`media.visibility_override`：enum 增列 `friends`。
- `reports.target_type`：enum 增列 `post`、`comment`。

### 2.2 `canView()` 擴充

判斷順序（任一不通過即不可見）：

1. 封鎖：瀏覽者與擁有者任一方封鎖另一方 → 不可見。
2. 狀態：`removed` 僅管理員與擁有者可見；`suppressed` 同。
3. 隱私：`friends` 需 `friendships.status=accepted`；其餘沿用 Phase 1。
4. NSFW：沿用 Phase 1 FR-5.4 矩陣。

擁有者對自有內容永遠可見（沿用 Phase 1）。

### 2.3 河道查詢

- 好友河道：`posts` join `friendships`（accepted，含雙向）過濾 `author_id`，`visibility in (public, friends)`，排除封鎖，`status=active`，`(created_at, id)` cursor 分頁。
- 探索河道：`visibility=public and status=active`，標籤篩選以 `tags @> ARRAY[...]` 走 GIN index，排除封鎖與依瀏覽者 NSFW 偏好過濾。
- 用戶量到萬級前不做 fan-out／時間線快取；**先用最直白的查詢**，加索引即可。

### 2.4 API 設計

| Method + Path | 說明 |
|---|---|
| `POST /api/friends/requests`、`POST /api/friends/requests/:id/accept`、`DELETE /api/friends/:userId` | 邀請／接受／解除 |
| `POST/DELETE /api/blocks/:userId` | 封鎖／解除 |
| `GET/POST /api/posts`、`GET/PATCH/DELETE /api/posts/:id` | 貼文 CRUD |
| `GET /api/feed/friends?cursor=`、`GET /api/feed/explore?tags=&cursor=` | 雙軌河道 |
| `POST/DELETE /api/posts/:id/like` | 讚 |
| `GET/POST /api/posts/:id/comments`、`DELETE /api/comments/:id` | 留言 |
| `GET /api/admin/reports`（擴充 post/comment）、`POST /api/admin/actions` | 治理 |

頁面路由：`/feed`、`/feed/explore`、`/post/:id`、`/post/new`、`/friends`；個人主頁 `/u/:pawfitId` 新增「貼文」分頁與「加好友／封鎖」按鈕。

### 2.5 關鍵設計決策

1. **貼文不提供 unlisted／private**：私密收藏由圖庫承擔，避免河道與圖庫語意重疊。
2. **credit 不可移除**：貼文層只能補充不能刪除來源圖的繪師標記，保護創作者歸屬（呼應上游 US-5）。
3. **降能見度而非自動下架**：多檢舉觸發 `suppressed` 由管理員最終裁決，避免惡意集體檢舉造成誤殺。

---

## 3. 里程碑與驗收

- **M1 好友**：邀請／接受／解除、封鎖、`friends` 隱私值啟用、`canView()` 擴充、分享頁對 friends 內容的處理。
- **M2 發文**：貼文 CRUD、母標籤繼承、credit 沿用、NSFW 鎖定規則。
- **M3 河道與互動**：雙軌河道、標籤篩選、讚、留言、個人主頁貼文分頁。
- **M4 治理強化**：檢舉擴充、自動降能見度、後台貼文操作、圖像審核 API 成本評估報告。

**Phase 2 完成定義（DoD）**：用戶 A 加 B 為好友並發一篇限好友的 NSFW 貼文（母標籤自動帶入、含繪師 credit）；B（偏好＝模糊）在好友河道看到模糊縮圖並點擊解鎖；非好友 C 與訪客在探索河道完全看不到該貼文；C 檢舉一篇公開貼文後，管理後台佇列即出現該檢舉。

## 4. 風險

| # | 風險 | 對策 |
|---|---|---|
| R-1 | 河道放大 NSFW 能見度，治理負擔上升 | FR-B7 自動降能見度＋檢舉優先排序；M4 評估圖像審核 API |
| R-2 | 冷啟動：沒有好友就沒有河道 | 探索河道開站即有內容（Phase 1 公開圖庫可作為「無貼文時」的補位來源）；邀請 Phase 1 封測用戶首批發文 |
| R-3 | 惡意集體檢舉造成誤殺 | 降能見度而非自動下架，管理員最終裁決；同一檢舉者對同目標只計一次 |
| R-4 | 封鎖邏輯散落各處造成漏洞 | 封鎖判斷只存在於 `canView()`，查詢層統一經 scope 套用，禁止在前端或個別 controller 補判斷 |
