// 與 apps/api 的 Resources 對齊

export type Visibility = 'public' | 'unlisted' | 'private' | 'friends'
export type NsfwPref = 'hide' | 'blur' | 'show'
export type MediaKind = 'art2d' | 'model3d' | 'photo'
export type MediaStatus = 'processing' | 'active' | 'removed' | 'failed'
export type ViewState = 'show' | 'blur'

export interface Quota {
  storage_used: number
  storage_limit: number
  uploads_today: number
  daily_limit: number
  max_file_bytes: number
}

export interface Me {
  id: string
  email: string
  pawfit_id: string | null
  display_name: string | null
  avatar_url: string | null
  nsfw_pref: NsfwPref
  effective_nsfw_pref: NsfwPref
  adult_confirmed_at: string | null
  tos_accepted_at: string | null
  is_onboarded: boolean
  is_admin: boolean
  is_banned: boolean
  locale: string | null
  /** 嵌入與公開 API 總開關（FR-7.1），預設 false */
  allow_embed_api: boolean
  quota: Quota
  created_at: string
}

export interface PublicUser {
  id: string
  pawfit_id: string
  display_name: string
  avatar_url: string | null
  joined_at: string
}

export interface PaletteEntry {
  hex: string
  name: string
  note: string
  sort?: number
}

export interface Media {
  id: string
  fursona_id: string
  kind: MediaKind
  caption: string | null
  credit_name: string | null
  credit_url: string | null
  is_nsfw: boolean
  visibility_override: Visibility | null
  sort_order: number
  status: MediaStatus
  status_note?: string | null
  width: number | null
  height: number | null
  bytes?: number
  state: ViewState
  urls: { thumb: string, display: string, original?: string }
  created_at: string
}

export interface ShareLink {
  id: string
  slug: string
  url: string
  watermark: boolean
  created_at: string
}

export interface Fursona {
  id: string
  name: string
  species: string | null
  bio: string | null
  tags: string[]
  palette: PaletteEntry[]
  visibility: Visibility
  is_nsfw: boolean
  is_representative: boolean
  avatar_media_id: string | null
  avatar_url: string | null
  cover_url: string | null
  media_count: number | null
  palette_count: number
  removed_at?: string | null
  /** 擁有者才有：null＝繼承用戶總開關；embed_enabled 為實際生效值 */
  allow_embed_api?: boolean | null
  embed_enabled?: boolean
  share_link?: ShareLink | null
  media?: Media[]
  owner?: PublicUser
  created_at: string
  updated_at: string
}

export interface SharePage {
  fursona: Fursona
  owner: PublicUser
  state: ViewState
  hidden_nsfw_count: number
  is_owner: boolean
  share: { slug: string, url: string, watermark: boolean }
  og_image_url: string | null
  /** 可嵌入時分享頁輸出 oEmbed discovery link */
  embed_enabled: boolean
}

export interface ProfilePage {
  user: PublicUser
  is_owner: boolean
  representative: Fursona | null
  fursonas: Fursona[]
  stats: { public_count: number, total_count: number | null }
  embed_enabled: boolean
  /** Phase 2 M1：登入者與這位用戶的關係；好友功能關閉、訪客或本人為 null */
  relation: { status: 'none' | 'friends' | 'pending_out' | 'pending_in', friendship_id: string | null, blocked_by_me: boolean } | null
}

/* ---- 貼文與河道（Phase 2 FR-B3–B6） ---- */

export type PostVisibility = 'public' | 'friends'
export type PostStatus = 'active' | 'removed' | 'suppressed'

export interface PostMedia {
  id: string
  kind: MediaKind
  caption: string | null
  credit_name: string | null
  credit_url: string | null
  is_nsfw: boolean
  width: number | null
  height: number | null
  state: ViewState
  urls: { thumb: string, display: string }
}

export interface Post {
  id: string
  url: string
  author: PublicUser
  fursona: { id: string, name: string, species: string | null, avatar_url: string | null } | null
  body: string
  tags: string[]
  is_nsfw: boolean
  visibility: PostVisibility
  status: PostStatus
  status_note?: string | null
  like_count: number
  comment_count: number
  liked_by_me: boolean
  state: ViewState
  media: PostMedia[]
  can_edit: boolean
  created_at: string
  updated_at: string
}

export interface FeedPage {
  data: Post[]
  next_cursor: string | null
  tags?: string[]
}

export interface PostComment {
  id: string
  post_id: string
  author: PublicUser
  body: string
  status: 'active' | 'removed'
  can_delete: boolean
  created_at: string
}

export interface PostCommentPage {
  data: PostComment[]
  next_after: string | null
  total: number
}

/* ---- 好友（Phase 2 FR-B1） ---- */

export interface FriendEntry {
  friendship_id: string
  user: PublicUser
  created_at: string
  accepted_at?: string
}

export interface FriendsPage {
  friends: FriendEntry[]
  incoming: FriendEntry[]
  outgoing: FriendEntry[]
  blocked: Array<{ user: PublicUser, created_at: string }>
}

/* ---- 委託需求單（FR-6，M7） ---- */

export type CommissionKitStatus = 'processing' | 'active' | 'failed' | 'revoked'
export type CommissionRequestField = 'composition' | 'scene' | 'size' | 'usage' | 'budget' | 'deadline' | 'notes'

export interface CommissionSnapshot {
  fursona: { name: string, species: string | null, bio: string | null, tags: string[], palette: PaletteEntry[] }
  owner: { pawfit_id: string, display_name: string }
  media: Array<{ id: string, kind: MediaKind, caption: string | null, credit_name: string | null, credit_url: string | null, is_nsfw: boolean, width: number | null, height: number | null }>
  request: Partial<Record<CommissionRequestField, string | null>>
  share_url: string | null
}

export interface CommissionKit {
  id: string
  slug: string
  url: string
  kind: 'art2d' | 'fursuit'
  status: CommissionKitStatus
  is_nsfw: boolean
  fursona_id: string
  snapshot: CommissionSnapshot
  /** locale（BCP 47）→ 模板文字 */
  brief_text: Record<string, string>
  brief_source: 'template' | 'llm'
  media_count: number
  sheet_url: string | null
  media_ids?: string[]
  revoked_at?: string | null
  created_at: string
  updated_at: string
}

export interface CommissionPage {
  kit: CommissionKit
  media: Media[]
  owner: PublicUser
  fursona: { id: string, name: string, share_url: string | null }
  state: ViewState
  is_owner: boolean
  og_image_url: string | null
}

/* ---- 公開 JSON API v1（/api/v1/public，FR-7.5）：嵌入卡片用 ---- */

export interface PublicFursona {
  slug: string
  name: string
  species: string | null
  bio: string | null
  tags: string[]
  palette: Array<{ hex: string, name: string, note: string }>
  is_nsfw: boolean
  avatar_url: string | null
  cover_url: string | null
  media_count: number
  credits: Array<{ name: string, url: string | null }>
  owner: { pawfit_id: string, display_name: string, profile_url: string }
  share_url: string
  embed: { card_url: string, palette_svg_url: string, media_url: string }
  updated_at: string
}

export interface PublicMedia {
  id: string
  kind: MediaKind
  caption: string | null
  credit_name: string | null
  credit_url: string | null
  width: number | null
  height: number | null
  url: string
  thumb_url: string
  created_at: string
}

export interface PublicMediaPage {
  data: PublicMedia[]
  next_cursor: string | null
  total: number
}

export type ReportTargetType = 'media' | 'fursona' | 'profile' | 'post' | 'comment'
export type ReportReason = 'illegal' | 'untagged_nsfw' | 'copyright' | 'harassment' | 'other'
export type ReportStatus = 'open' | 'resolved' | 'dismissed'

export interface Report {
  id: string
  target_type: ReportTargetType
  target_id: string
  target: {
    label: string
    fursona_name?: string | null
    fursona_id?: string
    owner_pawfit_id?: string | null
    is_nsfw?: boolean
    status?: MediaStatus | PostStatus
    thumb_url?: string
    visibility?: Visibility
    removed_at?: string | null
    is_banned?: boolean
    post_id?: string
  } | null
  reason_code: ReportReason
  detail: string | null
  status: ReportStatus
  reporter: PublicUser | null
  resolved_by: PublicUser | null
  resolved_at: string | null
  created_at: string
}

export interface AdminStats {
  open: number
  open_illegal: number
  resolved_this_week: number
  banned_users: number
  recent_actions: Array<{
    id: string
    admin: string | null
    action: string
    target_type: string
    target_id: string
    note: string | null
    created_at: string
  }>
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number, last_page: number, total: number }
}

export interface ApiError {
  message: string
  code?: string
  errors?: Record<string, string[]>
}
