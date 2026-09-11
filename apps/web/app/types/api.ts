// 與 apps/api 的 Resources 對齊

export type Visibility = 'public' | 'unlisted' | 'private'
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
}

export interface ProfilePage {
  user: PublicUser
  is_owner: boolean
  representative: Fursona | null
  fursonas: Fursona[]
  stats: { public_count: number, total_count: number | null }
}

export type ReportTargetType = 'media' | 'fursona' | 'profile'
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
    status?: MediaStatus
    thumb_url?: string
    visibility?: Visibility
    removed_at?: string | null
    is_banned?: boolean
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
