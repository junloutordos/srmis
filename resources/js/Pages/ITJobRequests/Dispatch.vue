<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PaperAirplaneIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import { roleLabel } from '@/Composables/useRoleLabel'

const props = defineProps({
  items:        { type: Array,  default: () => [] },
  filters:      { type: Object, default: () => ({}) },
  categories:   { type: Array,  default: () => [] },
  misPersonnel: { type: Array,  default: () => [] },
})

// ── Filters ───────────────────────────────────────────────────────────────────
const search         = ref(props.filters?.search   ?? '')
const filterCategory = ref(props.filters?.category ?? '')
const isLoading      = ref(false)
let debounceTimer    = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('jobrequests.dispatch'), {
      search:   search.value   || undefined,
      category: filterCategory.value || undefined,
    }, {
      preserveState: true,
      replace: true,
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))
watch(filterCategory, () => applyFilters(true))

// ── Selected assignee per item (defaults to the suggested one) ───────────────
const selectedAssignee = reactive({})
// Triage/validation note — the Helpdesk (KID Secretary) step of the ITJR
// flow. Required before an item can leave "Pending Dispatch".
const triageNotes = reactive({})
props.items.forEach(item => {
  selectedAssignee[item.id] = item.suggested_assignee_id ?? ''
  triageNotes[item.id] = ''
})
watch(() => props.items, (items) => {
  items.forEach(item => {
    if (selectedAssignee[item.id] === undefined) {
      selectedAssignee[item.id] = item.suggested_assignee_id ?? ''
    }
    if (triageNotes[item.id] === undefined) {
      triageNotes[item.id] = ''
    }
  })
})

const dispatchingId = ref(null)

function dispatchRequest(item) {
  const assignedto = selectedAssignee[item.id]
  if (!assignedto) {
    Swal.fire({ icon: 'warning', title: 'Select an MIS personnel first' })
    return
  }
  const notes = (triageNotes[item.id] || '').trim()
  if (!notes) {
    Swal.fire({ icon: 'warning', title: 'Add a triage/validation note before dispatching' })
    return
  }

  dispatchingId.value = item.id
  router.post(route('jobrequests.dispatch.action', item.id), { assignedto, notes }, {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Dispatched', timer: 1200, showConfirmButton: false })
    },
    onError: (errs) => {
      const msg = Object.values(errs || {}).flat().join('\n') || 'Failed to dispatch request'
      Swal.fire({ icon: 'error', title: 'Failed to dispatch', text: msg })
    },
    onFinish: () => { dispatchingId.value = null },
  })
}

function timeWaiting(queuedAt) {
  if (!queuedAt) return '—'
  const diff = Date.now() - new Date(queuedAt).getTime()
  const mins  = Math.floor(diff / 60000)
  const hours = Math.floor(mins / 60)
  const days  = Math.floor(hours / 24)
  if (days > 0)  return `${days}d ${hours % 24}h`
  if (hours > 0) return `${hours}h ${mins % 60}m`
  return `${mins}m`
}

const PRIORITY_COLORS = {
  urgent: 'bg-red-100 text-red-700 ring-1 ring-red-300',
  high:   'bg-orange-100 text-orange-700 ring-1 ring-orange-300',
  normal: 'bg-blue-100 text-blue-700 ring-1 ring-blue-200',
  low:    'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
}
const PRIORITY_LABELS = { urgent: 'Urgent', high: 'High', normal: 'Normal', low: 'Low' }

const stats = computed(() => ({
  total:  props.items.length,
  urgent: props.items.filter(i => i.priority === 'urgent').length,
  high:   props.items.filter(i => i.priority === 'high').length,
}))
</script>

<template>
  <Head title="IT Job Request Dispatch" />
  <AdminLayout title="IT Job Request Dispatch">
    <div>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-2">
          <PaperAirplaneIcon class="w-6 h-6 text-primary-600" />
          <h1 class="text-xl font-semibold text-slate-800">Dispatch Queue</h1>
        </div>
        <a
          :href="route('jobrequests.index')"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
        >
          All Requests
        </a>
      </div>

      <p class="text-sm text-slate-500 mb-5">
        These requests have been approved by {{ roleLabel('OCD') }} and are awaiting assignment to an MIS personnel.
        A suggested assignee (load-balanced) is pre-selected — pick a different one if needed, then dispatch.
      </p>

      <!-- Stats row -->
      <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3">
          <div class="text-xs text-slate-500 uppercase tracking-wide">Awaiting Dispatch</div>
          <div class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.total }}</div>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-100 shadow-sm px-4 py-3">
          <div class="text-xs text-red-500 uppercase tracking-wide">Urgent</div>
          <div class="text-2xl font-bold text-red-700 mt-0.5">{{ stats.urgent }}</div>
        </div>
        <div class="bg-orange-50 rounded-xl border border-orange-100 shadow-sm px-4 py-3">
          <div class="text-xs text-orange-500 uppercase tracking-wide">High</div>
          <div class="text-2xl font-bold text-orange-700 mt-0.5">{{ stats.high }}</div>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4">
        <div class="flex flex-wrap items-center gap-3">
          <div class="relative flex-1 sm:w-64 sm:flex-none">
            <input
              v-model="search"
              type="text"
              placeholder="Search..."
              @keydown.enter.prevent="applyFilters(true)"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400 w-full"
            />
          </div>
          <select
            v-model="filterCategory"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400"
          >
            <option value="">All Categories</option>
            <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
          </select>
          <button
            @click="applyFilters(true)"
            :disabled="isLoading"
            class="inline-flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50"
          >
            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
            Refresh
          </button>
        </div>
      </div>

      <!-- Dispatch table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="hidden sm:block overflow-x-auto" :class="{ 'opacity-50 pointer-events-none': isLoading }">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Priority</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">ITJR #</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Waiting</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Assign To</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="item in props.items"
                :key="item.id"
                class="hover:bg-slate-50/60 transition-colors"
                :class="item.priority === 'urgent' ? 'bg-red-50/40' : item.priority === 'high' ? 'bg-orange-50/30' : ''"
              >
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="PRIORITY_COLORS[item.priority] ?? PRIORITY_COLORS.normal"
                  >
                    {{ PRIORITY_LABELS[item.priority] ?? item.priority }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-700 font-mono text-xs">{{ item.itjr_no }}</td>
                <td class="px-4 py-3 text-slate-800 max-w-xs">
                  <div class="truncate font-medium">{{ item.title }}</div>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ item.user?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600">{{ item.category }}</td>
                <td class="px-4 py-3 text-slate-500 text-xs">{{ timeWaiting(item.queued_at) }}</td>
                <td class="px-4 py-3">
                  <select
                    v-model="selectedAssignee[item.id]"
                    class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary-500 w-44"
                  >
                    <option value="">Select MIS personnel…</option>
                    <option v-for="p in props.misPersonnel" :key="p.id" :value="p.id">
                      {{ p.name }}{{ p.id === item.suggested_assignee_id ? ' (suggested)' : '' }}
                    </option>
                  </select>
                  <textarea
                    v-model="triageNotes[item.id]"
                    rows="2"
                    placeholder="Triage / validation notes…"
                    class="mt-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 w-44"
                  ></textarea>
                </td>
                <td class="px-4 py-3 text-center">
                  <button
                    @click="dispatchRequest(item)"
                    :disabled="dispatchingId === item.id"
                    class="inline-flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm disabled:opacity-50"
                  >
                    <PaperAirplaneIcon class="w-3.5 h-3.5" />
                    Dispatch
                  </button>
                </td>
              </tr>

              <tr v-if="props.items.length === 0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">
                  No requests currently awaiting dispatch.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden p-4 space-y-3" :class="{ 'opacity-50 pointer-events-none': isLoading }">
          <div
            v-for="item in props.items"
            :key="item.id"
            class="border rounded-xl p-4"
            :class="item.priority === 'urgent' ? 'border-red-200 bg-red-50/30' : item.priority === 'high' ? 'border-orange-200 bg-orange-50/20' : 'border-slate-100'"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="font-semibold text-slate-800 leading-snug">{{ item.title }}</div>
                <div class="text-xs text-slate-400 font-mono mt-0.5">{{ item.itjr_no }}</div>
              </div>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold flex-shrink-0"
                :class="PRIORITY_COLORS[item.priority] ?? PRIORITY_COLORS.normal"
              >
                {{ PRIORITY_LABELS[item.priority] ?? item.priority }}
              </span>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-y-1 text-sm text-slate-600">
              <div><span class="text-slate-400">Requestor:</span> {{ item.user?.name ?? '—' }}</div>
              <div><span class="text-slate-400">Category:</span> {{ item.category }}</div>
              <div class="col-span-2"><span class="text-slate-400">Waiting:</span> {{ timeWaiting(item.queued_at) }}</div>
            </div>

            <div class="mt-3 space-y-2">
              <select
                v-model="selectedAssignee[item.id]"
                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                <option value="">Select MIS personnel…</option>
                <option v-for="p in props.misPersonnel" :key="p.id" :value="p.id">
                  {{ p.name }}{{ p.id === item.suggested_assignee_id ? ' (suggested)' : '' }}
                </option>
              </select>
              <textarea
                v-model="triageNotes[item.id]"
                rows="2"
                placeholder="Triage / validation notes…"
                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
              ></textarea>
              <button
                @click="dispatchRequest(item)"
                :disabled="dispatchingId === item.id"
                class="inline-flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm disabled:opacity-50 w-full justify-center"
              >
                <PaperAirplaneIcon class="w-3.5 h-3.5" />
                Dispatch
              </button>
            </div>
          </div>

          <div v-if="props.items.length === 0" class="py-16 text-center text-slate-400 text-sm">
            No requests currently awaiting dispatch.
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
