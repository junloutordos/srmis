<script setup>
import { computed } from 'vue'

const props = defineProps({
  oldValues: { type: Object, default: null },
  newValues: { type: Object, default: null },
})

function formatValue(v) {
  if (v === null || v === undefined || v === '') return '—'
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

function humanize(key) {
  return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

// created: only new_values (full attributes) — nothing to diff against.
// deleted: only old_values (full attributes) — nothing to diff against.
// updated: both present, same keys — show only the fields that actually changed.
const rows = computed(() => {
  const old = props.oldValues || {}
  const fresh = props.newValues || {}

  if (props.oldValues && props.newValues) {
    const keys = [...new Set([...Object.keys(old), ...Object.keys(fresh)])]
    return keys
      .filter((k) => JSON.stringify(old[k]) !== JSON.stringify(fresh[k]))
      .map((k) => ({ key: k, old: old[k], new: fresh[k], mode: 'change' }))
  }

  if (props.newValues) {
    return Object.keys(fresh).map((k) => ({ key: k, new: fresh[k], mode: 'add' }))
  }

  if (props.oldValues) {
    return Object.keys(old).map((k) => ({ key: k, old: old[k], mode: 'remove' }))
  }

  return []
})
</script>

<template>
  <div v-if="rows.length" class="space-y-1">
    <div v-for="row in rows" :key="row.key" class="text-xs">
      <span class="font-medium text-slate-600">{{ humanize(row.key) }}:</span>
      <template v-if="row.mode === 'change'">
        <span class="text-red-500 line-through decoration-red-300">{{ formatValue(row.old) }}</span>
        <span class="text-slate-400 mx-1">→</span>
        <span class="text-emerald-700 font-medium">{{ formatValue(row.new) }}</span>
      </template>
      <template v-else-if="row.mode === 'add'">
        <span class="text-emerald-700">{{ formatValue(row.new) }}</span>
      </template>
      <template v-else>
        <span class="text-red-500 line-through decoration-red-300">{{ formatValue(row.old) }}</span>
      </template>
    </div>
  </div>
  <span v-else class="text-xs text-slate-400">No field-level changes recorded.</span>
</template>
