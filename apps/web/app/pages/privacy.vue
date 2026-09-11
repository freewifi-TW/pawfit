<script setup lang="ts">
useSeoMeta({ title: '隱私權政策', description: 'Pawfit 爪搭隱私權政策' })
</script>

<template>
  <article class="prose-page">
    <h1 class="disp">
      隱私權政策
    </h1>
    <p class="muted">
      版本 0.1（草稿）· 最後更新 2026-09-11
    </p>

    <h2 class="disp">
      我們收集什麼
    </h2>
    <ul>
      <li><b>帳號資料</b>：Google 帳號的 email、名稱與頭像（用於登入與顯示）。</li>
      <li><b>你提供的內容</b>：獸設資料、圖片、色票、標籤、分享設定。</li>
      <li><b>法遵紀錄</b>：同意服務條款與 18 歲自我聲明的時間戳。</li>
      <li><b>技術資料</b>：IP 位址與請求紀錄，用於防濫用與除錯，短期保留。</li>
    </ul>

    <h2 class="disp">
      圖片如何儲存與提供
    </h2>
    <ul>
      <li>所有圖片存放於私有的物件儲存空間（Cloudflare R2），不會有公開的永久網址。</li>
      <li>每次存取圖片都會經過權限判斷，再以短效簽名網址提供；私人與連結可見的內容不會被未授權者存取。</li>
      <li>我們會自動產生展示版與縮圖；開啟浮水印時另產生浮水印版。原檔完整保留。</li>
    </ul>

    <h2 class="disp">
      我們不會做的事
    </h2>
    <ul>
      <li>不販售或分享你的個人資料給第三方。</li>
      <li>不使用你上傳的內容訓練 AI 模型。</li>
      <li>不在你設為私人或連結可見的內容上放廣告或做公開索引。</li>
    </ul>

    <h2 class="disp">
      Cookie
    </h2>
    <p>本站使用 session cookie 維持登入狀態，以及 CSRF 防護 cookie。不使用第三方追蹤 cookie。</p>

    <h2 class="disp">
      你的權利
    </h2>
    <p>你可以隨時刪除個別圖片或整隻獸設；刪除帳號與資料匯出功能將於後續版本提供，目前可透過頁尾聯絡方式來信申請。</p>

    <h2 class="disp">
      資料保存與備份
    </h2>
    <p>資料庫每日備份至獨立的儲存空間。被站方下架的內容於申訴期間保留；紅線內容直接刪除但保留操作紀錄。</p>
  </article>
</template>
