# SASD — Phase 2：社群與動態河道

> PawFit（爪搭）系統分析與設計文件
> 版本 0.3（草稿）｜2026-10-07｜狀態：規劃中
> 上游文件：`Pawfit (爪搭).md`｜上一份：`SASD-Phase1-獸設檔案庫與分享包.md`｜下一份：`SASD-Phase3-2D紙娃娃衣櫥與商城.md`
> 依賴：**僅 Phase 1**（帳號、圖庫、NSFW 治理、`canView()`、檢舉與管理後台皆沿用）；不依賴任何 AI（換獸頭貼圖的人臉偵測為瀏覽器端模型，不經伺服器、無推論成本）
> 變更紀錄：0.2 由舊版 Phase 3 的 B 線（社群）獨立成篇並提前為 Phase 2；AI 的 A 線移至 `SASD-Phase5-AI生成引擎.md`。
> 0.3 新增 **FR-B8 換獸頭貼圖工具**（M5）：瀏覽器端人臉偵測＋貼上自己的獸頭圖，作為發文工具；生成式換頭移至 Phase 5 A5。

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
- 換獸頭貼圖工具（M5，獨立 flag）：照片在瀏覽器端偵測人臉 → 貼上自己圖庫的獸頭圖 → 手動微調 → 直接發文

**Out of scope（列 backlog 或後續 phase）**

- 推薦演算法（初期用戶量撐不起也不需要）
- 巢狀留言、轉發、私訊
- 通知中心（先以「河道內標示新互動」的最簡形式處理，完整通知列 backlog）
- 衣櫥穿搭圖發文（Phase 3 上線後自動接上）
- 第三方圖像審核 API（本 phase 先評估成本，於 M4 決定是否導入）
- 生成式換獸頭（把獸頭自然「畫進」照片）→ Phase 5 A5；本 phase 只做貼圖版
- 人臉**辨識**（比對身分）：永不做，見 FR-B8.5
- 貼文嵌入與 oEmbed（沿用 Phase 1 M6 的 `/embed` 機制增列 post 類型）→ backlog，河道穩定後再加

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

**FR-B8 換獸頭貼圖工具（M5；`FEATURE_HEAD_STICKER`）**

目的：合照、聚會照「換上自己的獸頭」是圈內常見的社群玩法，也是河道的擴散點。做法是**貼圖**而非生成：偵測臉的位置，把用戶自己的獸頭圖疊上去，使用者可微調。

- FR-B8.1 入口：發文頁「換獸頭」工具。使用者從本機選一張照片（**不上傳原照**），瀏覽器端以 MediaPipe Face Detection 類模型（WASM／WebGPU，模型檔自站 serve）偵測所有人臉的框與五官關鍵點。
- FR-B8.2 獸頭素材：圖庫中 `kind=art2d` 且使用者指定為「頭像貼圖」的 PNG（需去背、SFW）；每隻獸設可指定多張（正面／側面）。Phase 5 A1 去背上線後可一鍵從設定圖產生貼圖素材，但本 phase 要求使用者自備去背 PNG。
- FR-B8.3 編輯：每張偵測到的臉可各自指定貼圖（多人合照各貼各的，好友的獸頭需對方為好友且該素材為 `friends` 以上可見）、或不貼；依臉框與雙眼連線自動定位、縮放、旋轉，使用者可拖曳、縮放、旋轉、翻轉、調整層序微調；未偵測到的臉可手動加框。
- FR-B8.4 輸出：canvas 合成為一張圖（長邊 ≤2048，webp），走 Phase 1 presign 直傳成為新 media（`kind=photo`、`origin=head_sticker`），之後與一般圖一樣發文、設隱私與 NSFW。原照片從頭到尾只存在於瀏覽器記憶體。
- FR-B8.5 隱私紅線：只做人臉**偵測**（位置），不做人臉**辨識**（比對身分）；不儲存、不上傳任何臉部關鍵點或特徵向量；工具頁明示「照片不會離開你的裝置」。偵測模型以靜態檔案自站提供，不呼叫任何第三方 API。
- FR-B8.6 對他人肖像的責任由發文者承擔，ToS 增補條款；被拍者可依 Phase 1 檢舉機制以 `harassment` 檢舉，處理流程沿用。
- FR-B8.7 不做：影片、即時相機濾鏡、自動配對「這張臉是誰」。

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
- `media.origin`：enum: upload/head_sticker，預設 upload（FR-B8.4；Phase 5 再增列 `ai_*` 值）。
- `media.is_head_sticker`：boolean，預設 false（FR-B8.2 的素材標記；只允許 `kind=art2d`、`is_nsfw=false`）。

換獸頭工具**不新增任何資料表**：偵測結果不落地，輸出圖走既有 `media`。

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

頁面路由：`/feed`、`/feed/explore`、`/post/:id`、`/post/new`、`/post/new/head-sticker`（FR-B8 工具，純前端）、`/friends`；個人主頁 `/u/:pawfitId` 新增「貼文」分頁與「加好友／封鎖」按鈕。

FR-B8 不新增 API：素材清單走 `GET /api/media?is_head_sticker=1`（含好友可見素材），輸出圖走 Phase 1 `presign`／`confirm`（confirm 增收 `origin`）。

### 2.5 關鍵設計決策

1. **貼文不提供 unlisted／private**：私密收藏由圖庫承擔，避免河道與圖庫語意重疊。
2. **credit 不可移除**：貼文層只能補充不能刪除來源圖的繪師標記，保護創作者歸屬（呼應上游 US-5）。
3. **降能見度而非自動下架**：多檢舉觸發 `suppressed` 由管理員最終裁決，避免惡意集體檢舉造成誤殺。
4. **換獸頭全程在瀏覽器端**：人臉是生物特徵，照片常含第三人；原照與偵測結果不經伺服器是最簡單也最可信的個資對策，順帶零推論成本。代價是無法做「自然融合」的效果——那是 Phase 5 A5 的事，且要過 gate。

---

## 3. 里程碑與驗收

- **M1 好友**：邀請／接受／解除、封鎖、`friends` 隱私值啟用、`canView()` 擴充、分享頁對 friends 內容的處理。
- **M2 發文**：貼文 CRUD、母標籤繼承、credit 沿用、NSFW 鎖定規則。
- **M3 河道與互動**：雙軌河道、標籤篩選、讚、留言、個人主頁貼文分頁。
- **M4 治理強化**：檢舉擴充、自動降能見度、後台貼文操作、圖像審核 API 成本評估報告。
- **M5 換獸頭貼圖工具**（`FEATURE_HEAD_STICKER`，可與 M3 平行）：瀏覽器端偵測、貼圖素材標記、編輯畫布、輸出成 media 並發文、ToS 增補。驗收：三人合照各貼上不同獸頭並發文，過程中 network 面板沒有任何含原照或臉部資料的請求。

**Phase 2 完成定義（DoD）**：用戶 A 加 B 為好友並發一篇限好友的 NSFW 貼文（母標籤自動帶入、含繪師 credit）；B（偏好＝模糊）在好友河道看到模糊縮圖並點擊解鎖；非好友 C 與訪客在探索河道完全看不到該貼文；C 檢舉一篇公開貼文後，管理後台佇列即出現該檢舉。

## 4. 風險

| # | 風險 | 對策 |
|---|---|---|
| R-1 | 河道放大 NSFW 能見度，治理負擔上升 | FR-B7 自動降能見度＋檢舉優先排序；M4 評估圖像審核 API |
| R-2 | 冷啟動：沒有好友就沒有河道 | 探索河道開站即有內容（Phase 1 公開圖庫可作為「無貼文時」的補位來源）；邀請 Phase 1 封測用戶首批發文 |
| R-3 | 惡意集體檢舉造成誤殺 | 降能見度而非自動下架，管理員最終裁決；同一檢舉者對同目標只計一次 |
| R-4 | 封鎖邏輯散落各處造成漏洞 | 封鎖判斷只存在於 `canView()`，查詢層統一經 scope 套用，禁止在前端或個別 controller 補判斷 |
| R-5 | 換獸頭工具涉及第三人肖像與生物特徵（個資法） | 只偵測不辨識、不落地任何臉部資料、原照不離開裝置（FR-B8.5）；肖像責任歸發文者並提供檢舉管道（FR-B8.6） |
| R-6 | 瀏覽器端偵測在低階手機太慢或不支援 WebGPU | 以 WASM 為基線、WebGPU 為加速；偵測失敗時仍可手動加框完成流程 |
