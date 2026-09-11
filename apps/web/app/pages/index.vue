<script setup lang="ts">
const { me } = useAuth()
const config = useRuntimeConfig()
const route = useRoute()
const notify = useNotify()

useSeoMeta({
  title: '把你的獸設，整理成一個連結',
  description: '三視圖、精準色票、繪師 credit、委託成品，全部收在一頁。Pawfit 爪搭：獸圈用的獸設檔案庫。'
})

const demoSlug = config.public.demoSlug
const startTo = computed(() => (me.value ? (me.value.is_onboarded ? '/dashboard' : '/onboarding') : '/login'))

onMounted(() => {
  if (route.query.banned) notify.err('此帳號已被停權', '如有疑問請聯絡站方。')
})
</script>

<template>
  <section class="wrap">
    <div class="hero">
      <div>
        <h1 class="disp">
          把你的獸設，<br>整理成<mark>一個連結</mark>。
        </h1>
        <p>三視圖、精準色票、繪師 credit、委託成品，全部收在一頁。傳給繪師、毛裝工作室或朋友，不用再翻聊天紀錄找設定圖。</p>
        <div class="cta">
          <NuxtLink
            class="btn primary lg"
            :to="startTo"
          >
            {{ me ? '進入我的獸設' : '用 Google 開始' }}
          </NuxtLink>
          <NuxtLink
            v-if="demoSlug"
            class="btn lg"
            :to="`/s/${demoSlug}`"
          >
            看示範分享頁
          </NuxtLink>
        </div>
      </div>
      <div class="peek">
        <span class="sticker">示範獸設</span>
        <div class="card">
          <div class="row">
            <BlobAvatar
              :size="56"
              :border="3"
              alt=""
            />
            <div>
              <b
                class="disp"
                style="font-size:22px"
              >阿燼 Ember</b>
              <div
                class="sub"
                style="font-size:13px"
              >
                赤狐 · 成年 · 標準體型
              </div>
            </div>
          </div>
          <div class="tags">
            <span
              class="tag mint"
              style="--r:-2deg"
            >犬科</span><span
              class="tag"
              style="--r:1.5deg"
            >赤狐</span><span
              class="tag butter"
              style="--r:-1deg"
            >標準體型</span>
          </div>
          <div class="swatch-strip">
            <i style="background:#D9642A" /><i style="background:#F4E7D3" /><i style="background:#2B2420" /><i style="background:#7FB7A3" /><i style="background:#4A2E2A" /><i style="background:#F1EEE8" />
          </div>
          <div
            class="row"
            style="font-size:12px;color:var(--ink-3)"
          >
            <span>5 張設定圖</span><span>·</span><span>4 位繪師 credit</span><span class="sp" /><span class="mono">pawfit.app/s/demo…</span>
          </div>
        </div>
      </div>
    </div>

    <div class="feats">
      <div class="card feat">
        <div
          class="ic"
          style="background:var(--mint)"
        >
          🗂️
        </div>
        <h3 class="disp">
          設定資料集中
        </h3>
        <p>多隻獸設、多視角圖庫，強制區分 2D 平面、3D 模型與實體照片，委託成品也能收進私密收藏。</p>
      </div>
      <div class="card feat">
        <div
          class="ic"
          style="background:var(--butter)"
        >
          🎨
        </div>
        <h3 class="disp">
          色票精準到位
        </h3>
        <p>每個顏色都有名稱、部位備註與 hex 色碼，繪師一鍵複製，不再靠截圖取色。</p>
      </div>
      <div class="card feat">
        <div
          class="ic"
          style="background:var(--sky)"
        >
          🔗
        </div>
        <h3 class="disp">
          一鍵分享包
        </h3>
        <p>產生分享連結，貼到 Discord 或 Twitter 會展開預覽卡；可開浮水印、可隨時停用。</p>
      </div>
    </div>

    <div class="card notice">
      <span style="font-size:24px">🔞</span>
      <div><b>成人內容第一天就支援，但預設看不到。</b> 上傳時必須標記 SFW 或 NSFW；瀏覽者要完成 18 歲聲明並自行切換顯示方式，訪客永遠看不到 NSFW 內容。</div>
    </div>
  </section>
</template>
