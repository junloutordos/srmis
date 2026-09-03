import { usePage } from "@inertiajs/vue3"

/**
 * Rewrites a display string's internal role names (e.g. "OCD") to this
 * tenant's label for that role (e.g. OED's "KID Chief"). Only touches
 * rendered text — never call this on values used for `status ===` checks,
 * route params, or anything else compared against the stored DB value.
 */
export function roleLabel(text) {
  if (!text) return text
  const overrides = usePage().props?.roleLabels || {}
  let result = text
  for (const [name, label] of Object.entries(overrides)) {
    if (!label || label === name) continue
    result = result.replace(new RegExp(`\\b${name}\\b`, "g"), label)
  }
  return result
}
