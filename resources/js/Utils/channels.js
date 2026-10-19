import { usePage } from '@inertiajs/vue3'

// Mirrors App\Support\TenantChannel — ids are per-campus but Soketi is shared,
// so every private channel name carries the campus slug.
const prefix = () => `tenant.${usePage().props.campus?.id ?? 'none'}.`

export const userChannel = (userId) => `${prefix()}user.${userId}`
export const conversationChannel = (conversationId) => `${prefix()}conversation.${conversationId}`
