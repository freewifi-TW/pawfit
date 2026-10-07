<script setup lang="ts">
import type { Fursona, Media, PublicUser } from '~/types/api'

/**
 * 換獸頭貼圖工具（Phase 2 FR-B8）：
 * 1. 本機選一張照片（不上傳原照）→ 瀏覽器端 MediaPipe Face Detector 偵測臉框與五官關鍵點
 * 2. 每張臉指定一張自己（或好友）的獸頭貼圖素材，依臉框與雙眼連線自動定位／縮放／旋轉，可手動微調、手動加框
 * 3. canvas 合成 → 走既有 presign 直傳成為新 media（kind=photo, origin=head_sticker）→ 接到發文頁
 * 原照片從頭到尾只存在於瀏覽器記憶體；偵測模型與 WASM 自站提供，不呼叫任何第三方 API（FR-B8.5）。
 */
definePageMeta({ middleware: 'onboarded' })

const { t } = useI18n()
const api = useApi()
const notify = useNotify()
const { features } = useFeatures()
if (!features.value.head_sticker || !features.value.feed) {
  throw createError({ statusCode: 404, statusMessage: t('headsticker.disabled') })
}
useSeoMeta({ title: () => t('headsticker.title'), robots: 'noindex' })

interface StickerList { mine: Media[], friends: Array<{ user: PublicUser, media: Media[] }> }
interface Face {
  id: number
  // 臉框（原圖像素）
  x: number
  y: number
  w: number
  h: number
  // 雙眼連線角度（弧度），沒有關鍵點時為 0
  angle: number
  stickerId: string | null
  // 微調：相對臉框中心的位移（臉寬比例）、縮放倍率、額外旋轉（度）、翻轉
  dx: number
  dy: number
  scale: number
  rot: number
  flip: boolean
  manual: boolean
}

const { data: stickers } = await useAsyncData('stickers', () => api<StickerList>('/media/stickers'), { default: () => ({ mine: [], friends: [] }) })
const { data: fursonas } = await useAsyncData('hs-fursonas', () => api<Fursona[]>('/fursonas'), { default: () => [] })
const allStickers = computed<Media[]>(() => [...stickers.value.mine, ...stickers.value.friends.flatMap(f => f.media)])

const fileInput = ref<HTMLInputElement | null>(null)
const canvas = ref<HTMLCanvasElement | null>(null)
const photo = ref<HTMLImageElement | null>(null)
const faces = ref<Face[]>([])
const selected = ref<number | null>(null)
const detecting = ref(false)
const detectError = ref<string | null>(null)
const modelReady = ref(false)
const uploading = ref(false)
const targetFursona = ref<string>(fursonas.value.find(f => f.is_representative)?.id ?? fursonas.value[0]?.id ?? '')
const nsfw = ref(false)
const stickerImages = new Map<string, HTMLImageElement>()
let detector: import('@mediapipe/tasks-vision').FaceDetector | null = null
let nextId = 1

const current = computed(() => faces.value.find(f => f.id === selected.value) ?? null)

async function ensureDetector() {
  if (detector) return detector
  const { FaceDetector, FilesetResolver } = await import('@mediapipe/tasks-vision')
  const fileset = await FilesetResolver.forVisionTasks('/mediapipe')
  detector = await FaceDetector.createFromOptions(fileset, {
    baseOptions: { modelAssetPath: '/models/blaze_face_short_range.tflite' },
    runningMode: 'IMAGE',
    minDetectionConfidence: 0.5
  })
  modelReady.value = true
  return detector
}

function loadImage(src: string, cors = false): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image()
    if (cors) img.crossOrigin = 'anonymous'
    img.onload = () => resolve(img)
    img.onerror = () => reject(new Error('image load failed'))
    img.src = src
  })
}

async function onFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  detectError.value = null
  faces.value = []
  selected.value = null
  const url = URL.createObjectURL(file)
  try {
    const img = await loadImage(url)
    photo.value = img
    await nextTick()
    draw()
    await detect()
  } catch {
    notify.err(t('headsticker.notify.loadFailed'))
  } finally {
    URL.revokeObjectURL(url)
  }
}

async function detect() {
  if (!photo.value) return
  detecting.value = true
  detectError.value = null
  try {
    const det = await ensureDetector()
    const result = det.detect(photo.value)
    const found: Face[] = result.detections.map((d) => {
      const bb = d.boundingBox!
      const kp = d.keypoints ?? []
      // BlazeFace 關鍵點順序：0 右眼、1 左眼、2 鼻尖、3 嘴、4 右耳、5 左耳（normalized）
      const re = kp[0]
      const le = kp[1]
      const angle = re && le ? Math.atan2((le.y - re.y) * photo.value!.naturalHeight, (le.x - re.x) * photo.value!.naturalWidth) : 0
      return {
        id: nextId++, x: bb.originX, y: bb.originY, w: bb.width, h: bb.height, angle,
        stickerId: allStickers.value[0]?.id ?? null, dx: 0, dy: -0.12, scale: 1.75, rot: 0, flip: false, manual: false
      }
    })
    faces.value = found
    selected.value = found[0]?.id ?? null
    if (!found.length) detectError.value = t('headsticker.noFaces')
  } catch (e) {
    detectError.value = t('headsticker.detectFailed')
    console.error(e)
  } finally {
    detecting.value = false
    draw()
  }
}

function addManualFace() {
  if (!photo.value) return
  const w = photo.value.naturalWidth
  const h = photo.value.naturalHeight
  const size = Math.round(Math.min(w, h) * 0.25)
  const f: Face = { id: nextId++, x: (w - size) / 2, y: (h - size) / 2, w: size, h: size, angle: 0, stickerId: allStickers.value[0]?.id ?? null, dx: 0, dy: -0.12, scale: 1.75, rot: 0, flip: false, manual: true }
  faces.value.push(f)
  selected.value = f.id
  draw()
}

function removeFace(id: number) {
  faces.value = faces.value.filter(f => f.id !== id)
  if (selected.value === id) selected.value = faces.value[0]?.id ?? null
  draw()
}

async function stickerImage(id: string): Promise<HTMLImageElement | null> {
  if (stickerImages.has(id)) return stickerImages.get(id)!
  const m = allStickers.value.find(s => s.id === id)
  if (!m) return null
  try {
    // 走 /api/img → 302 簽名 URL；需 crossOrigin 才能畫進 canvas 後匯出（R2／MinIO 需允許 GET 的 CORS）
    const img = await loadImage(m.urls.display, true)
    stickerImages.set(id, img)
    return img
  } catch {
    notify.err(t('headsticker.notify.stickerLoadFailed'))
    return null
  }
}

let drawing = false
async function draw() {
  if (!canvas.value || !photo.value || drawing) return
  drawing = true
  try {
    const c = canvas.value
    const img = photo.value
    c.width = img.naturalWidth
    c.height = img.naturalHeight
    const ctx = c.getContext('2d')!
    ctx.drawImage(img, 0, 0)
    for (const f of faces.value) {
      if (!f.stickerId) continue
      const s = await stickerImage(f.stickerId)
      if (!s) continue
      const cx = f.x + f.w / 2 + f.dx * f.w
      const cy = f.y + f.h / 2 + f.dy * f.h
      const targetW = f.w * f.scale
      const ratio = s.naturalHeight / s.naturalWidth
      ctx.save()
      ctx.translate(cx, cy)
      ctx.rotate(f.angle + (f.rot * Math.PI) / 180)
      if (f.flip) ctx.scale(-1, 1)
      ctx.drawImage(s, -targetW / 2, -(targetW * ratio) / 2, targetW, targetW * ratio)
      ctx.restore()
    }
    // 選取框（只在預覽畫，匯出時另畫一次不含框）
    const sel = current.value
    if (sel) {
      ctx.save()
      ctx.strokeStyle = '#ff7a59'
      ctx.lineWidth = Math.max(2, c.width / 400)
      ctx.setLineDash([8, 6])
      ctx.strokeRect(sel.x, sel.y, sel.w, sel.h)
      ctx.restore()
    }
  } finally {
    drawing = false
  }
}
watch([faces, selected], () => draw(), { deep: true })

// 點 canvas 選臉；拖曳移動手動框
function canvasPoint(e: MouseEvent | Touch): { x: number, y: number } {
  const c = canvas.value!
  const r = c.getBoundingClientRect()
  return { x: ((e.clientX - r.left) / r.width) * c.width, y: ((e.clientY - r.top) / r.height) * c.height }
}
let dragging: { id: number, ox: number, oy: number } | null = null
function onDown(e: MouseEvent) {
  if (!canvas.value) return
  const p = canvasPoint(e)
  const hit = [...faces.value].reverse().find(f => p.x >= f.x - f.w * 0.5 && p.x <= f.x + f.w * 1.5 && p.y >= f.y - f.h * 0.7 && p.y <= f.y + f.h * 1.2)
  if (hit) {
    selected.value = hit.id
    dragging = { id: hit.id, ox: p.x - hit.x, oy: p.y - hit.y }
  }
}
function onMove(e: MouseEvent) {
  if (!dragging) return
  const p = canvasPoint(e)
  const f = faces.value.find(x => x.id === dragging!.id)
  if (f) {
    f.x = p.x - dragging.ox
    f.y = p.y - dragging.oy
  }
}
function onUp() {
  dragging = null
}

async function exportAndContinue() {
  if (!canvas.value || !photo.value) return
  if (!targetFursona.value) return notify.err(t('headsticker.notify.pickFursona'))
  uploading.value = true
  try {
    // 匯出：不含選取框、長邊 ≤ 2048、webp
    const prev = selected.value
    selected.value = null
    await draw()
    const src = canvas.value
    const scale = Math.min(1, 2048 / Math.max(src.width, src.height))
    const out = document.createElement('canvas')
    out.width = Math.round(src.width * scale)
    out.height = Math.round(src.height * scale)
    out.getContext('2d')!.drawImage(src, 0, 0, out.width, out.height)
    selected.value = prev
    const blob = await new Promise<Blob | null>(resolve => out.toBlob(resolve, 'image/webp', 0.9))
    if (!blob) throw new Error('export failed')

    const presign = await api<{ storage_key: string, upload_url: string, headers: Record<string, string> }>('/media/presign', {
      method: 'POST', body: { fursona_id: targetFursona.value, content_type: 'image/webp', bytes: blob.size }
    })
    const put = await fetch(presign.upload_url, { method: 'PUT', headers: { ...(presign.headers ?? {}), 'Content-Type': 'image/webp' }, body: blob })
    if (!put.ok) throw new Error(`upload ${put.status}`)
    const media = await api<Media>('/media/confirm', {
      method: 'POST',
      body: { fursona_id: targetFursona.value, storage_key: presign.storage_key, kind: 'photo', is_nsfw: nsfw.value, origin: 'head_sticker', caption: t('headsticker.defaultCaption') }
    })
    notify.ok(t('headsticker.notify.saved'))
    await navigateTo({ path: '/post/new', query: { fursona: targetFursona.value, media: media.id } })
  } catch (e) {
    notify.err(t('headsticker.notify.failed'), apiError(e).message)
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <section
    class="wrap"
    style="padding-bottom:48px"
  >
    <div class="pagehd">
      <div>
        <h1 class="disp">
          {{ t('headsticker.title') }}
        </h1>
        <p>{{ t('headsticker.subtitle') }}</p>
      </div>
    </div>

    <div
      class="visitor-note"
      style="margin-bottom:16px"
    >
      {{ t('headsticker.privacy') }}
    </div>

    <div class="hs-grid">
      <div class="stack">
        <div class="card">
          <div
            class="bd stack"
            style="gap:12px"
          >
            <div class="row">
              <input
                ref="fileInput"
                type="file"
                accept="image/*"
                style="display:none"
                @change="onFile"
              >
              <button
                class="btn primary"
                type="button"
                @click="fileInput?.click()"
              >
                {{ photo ? t('headsticker.changePhoto') : t('headsticker.pickPhoto') }}
              </button>
              <button
                v-if="photo"
                class="btn"
                type="button"
                :disabled="detecting"
                @click="detect"
              >
                {{ detecting ? t('headsticker.detecting') : t('headsticker.redetect') }}
              </button>
              <button
                v-if="photo"
                class="btn ghost"
                type="button"
                @click="addManualFace"
              >
                {{ t('headsticker.addFace') }}
              </button>
              <span class="sp" />
              <span
                v-if="photo"
                class="muted"
                style="font-size:12px"
              >{{ t('headsticker.facesFound', faces.length) }}</span>
            </div>
            <p
              v-if="detectError"
              class="sub"
              style="margin:0;font-size:13px"
            >
              {{ detectError }}
            </p>
            <div
              v-if="!photo"
              class="empty"
            >
              {{ t('headsticker.empty') }}
            </div>
            <canvas
              v-show="photo"
              ref="canvas"
              class="hs-canvas"
              @mousedown="onDown"
              @mousemove="onMove"
              @mouseup="onUp"
              @mouseleave="onUp"
            />
            <p
              v-if="photo"
              class="muted"
              style="font-size:12px;margin:0"
            >
              {{ t('headsticker.canvasHint') }}
            </p>
          </div>
        </div>
      </div>

      <aside class="stack">
        <div class="card">
          <h2 class="disp">
            {{ t('headsticker.stickers.title') }}
          </h2>
          <div
            class="bd stack"
            style="gap:10px"
          >
            <p
              v-if="!allStickers.length"
              class="sub"
              style="margin:0;font-size:13px"
            >
              {{ t('headsticker.stickers.empty') }}
            </p>
            <template v-else>
              <div
                v-if="!current"
                class="muted"
                style="font-size:12px"
              >
                {{ t('headsticker.stickers.selectFace') }}
              </div>
              <div
                v-if="stickers.mine.length"
                class="muted"
                style="font-size:12px"
              >
                {{ t('headsticker.stickers.mine') }}
              </div>
              <div class="pick">
                <button
                  v-for="m in stickers.mine"
                  :key="m.id"
                  type="button"
                  class="pick-item"
                  :class="{ on: current?.stickerId === m.id }"
                  :disabled="!current"
                  @click="current && (current.stickerId = m.id)"
                >
                  <img
                    :src="m.urls.thumb"
                    :alt="m.caption || ''"
                  >
                </button>
              </div>
              <template
                v-for="g in stickers.friends"
                :key="g.user.id"
              >
                <div
                  class="muted"
                  style="font-size:12px"
                >
                  @{{ g.user.pawfit_id }}
                </div>
                <div class="pick">
                  <button
                    v-for="m in g.media"
                    :key="m.id"
                    type="button"
                    class="pick-item"
                    :class="{ on: current?.stickerId === m.id }"
                    :disabled="!current"
                    @click="current && (current.stickerId = m.id)"
                  >
                    <img
                      :src="m.urls.thumb"
                      :alt="m.caption || ''"
                    >
                  </button>
                </div>
              </template>
              <button
                v-if="current"
                class="btn sm ghost"
                type="button"
                @click="current.stickerId = null"
              >
                {{ t('headsticker.stickers.none') }}
              </button>
            </template>
          </div>
        </div>

        <div
          v-if="current"
          class="card"
        >
          <h2 class="disp">
            {{ t('headsticker.adjust.title') }}
          </h2>
          <div
            class="bd stack"
            style="gap:10px"
          >
            <label class="slider"><span>{{ t('headsticker.adjust.scale') }}</span><input
              v-model.number="current.scale"
              type="range"
              min="0.6"
              max="3.5"
              step="0.05"
            ></label>
            <label class="slider"><span>{{ t('headsticker.adjust.rotate') }}</span><input
              v-model.number="current.rot"
              type="range"
              min="-90"
              max="90"
              step="1"
            ></label>
            <label class="slider"><span>{{ t('headsticker.adjust.dx') }}</span><input
              v-model.number="current.dx"
              type="range"
              min="-1"
              max="1"
              step="0.02"
            ></label>
            <label class="slider"><span>{{ t('headsticker.adjust.dy') }}</span><input
              v-model.number="current.dy"
              type="range"
              min="-1"
              max="1"
              step="0.02"
            ></label>
            <div class="row">
              <label class="check"><input
                v-model="current.flip"
                type="checkbox"
              > {{ t('headsticker.adjust.flip') }}</label>
              <span class="sp" />
              <button
                class="btn sm ghost"
                type="button"
                style="color:var(--danger)"
                @click="removeFace(current.id)"
              >
                {{ t('headsticker.adjust.remove') }}
              </button>
            </div>
            <div
              v-if="faces.length > 1"
              class="row"
            >
              <button
                v-for="(f, i) in faces"
                :key="f.id"
                class="btn sm"
                :class="{ primary: f.id === selected }"
                type="button"
                @click="selected = f.id"
              >
                {{ t('headsticker.adjust.face', { n: i + 1 }) }}
              </button>
            </div>
          </div>
        </div>

        <div class="card">
          <h2 class="disp">
            {{ t('headsticker.export.title') }}
          </h2>
          <div
            class="bd stack"
            style="gap:10px"
          >
            <label
              class="field"
              style="margin:0"
            >
              <span class="lbl">{{ t('headsticker.export.fursona') }}</span>
              <select
                v-model="targetFursona"
                class="input"
              >
                <option
                  v-for="f in fursonas"
                  :key="f.id"
                  :value="f.id"
                >
                  {{ f.name }}
                </option>
              </select>
            </label>
            <label class="check"><input
              v-model="nsfw"
              type="checkbox"
            > {{ t('headsticker.export.nsfw') }}</label>
            <button
              class="btn primary block"
              type="button"
              :disabled="!photo || uploading || !faces.some(f => f.stickerId)"
              @click="exportAndContinue"
            >
              {{ uploading ? t('headsticker.export.uploading') : t('headsticker.export.submit') }}
            </button>
            <p
              class="muted"
              style="font-size:12px;margin:0"
            >
              {{ t('headsticker.export.note') }}
            </p>
          </div>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.hs-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
@media (max-width: 860px) { .hs-grid { grid-template-columns: 1fr; } }
.hs-canvas { width: 100%; height: auto; display: block; border-radius: var(--r-in); border: var(--border) solid var(--line); background: var(--paper-2); cursor: grab; touch-action: none; }
.pick { display: grid; grid-template-columns: repeat(auto-fill, minmax(64px, 1fr)); gap: 6px; }
.pick-item { aspect-ratio: 1; border-radius: 12px; overflow: hidden; border: var(--border) solid var(--line); background: var(--paper-2); padding: 0; }
.pick-item img { width: 100%; height: 100%; object-fit: contain; display: block; }
.pick-item.on { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.pick-item:disabled { opacity: .5; cursor: not-allowed; }
.slider { display: grid; grid-template-columns: 64px 1fr; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; }
.slider input { width: 100%; accent-color: var(--accent); }
</style>
