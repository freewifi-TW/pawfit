/** 統一的 toast 包裝：成功／失敗訊息用同一種語氣與顏色。 */
export function useNotify() {
  const toast = useToast()

  return {
    ok(title: string, description?: string) {
      toast.add({ title, description, color: 'success', icon: 'i-lucide-check' })
    },
    err(title: string, description?: string) {
      toast.add({ title, description, color: 'error', icon: 'i-lucide-alert-triangle' })
    },
    info(title: string, description?: string) {
      toast.add({ title, description, color: 'neutral', icon: 'i-lucide-info' })
    }
  }
}
