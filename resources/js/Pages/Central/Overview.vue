<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
  ArrowPathIcon,
  ExclamationTriangleIcon,
  BuildingOffice2Icon,
  UserGroupIcon,
  ComputerDesktopIcon,
  TruckIcon,
  WrenchScrewdriverIcon,
  CalendarDaysIcon,
  ChevronUpDownIcon,
  ChevronUpIcon,
  ChevronDownIcon,
  CheckCircleIcon,
  XCircleIcon,
  InboxStackIcon,
  ChartBarIcon,
  ChatBubbleLeftRightIcon,
  PencilSquareIcon,
  Squares2X2Icon,
  CircleStackIcon,
  ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  stats:        { type: Array,  default: () => [] },
  moduleLabels: { type: Object, default: () => ({}) },
})

const page    = usePage()
const flash   = computed(() => page.props.flash || {})
const loading = ref(false)

const provisioned   = computed(() => props.stats.filter(s => s.provisioned))
const unprovisioned = computed(() => props.stats.filter(s => !s.provisioned))

// ── Sorting ────────────────────────────────────────────────────────────────────
const sortBy  = ref('name')
const sortDir = ref('asc')

function sort(col) {
  if (sortBy.value === col) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value  = col
    sortDir.value = col === 'name' ? 'asc' : 'desc'
  }
}

const sortedProvisioned = computed(() => {
  return [...provisioned.value].sort((a, b) => {
    const av = a[sortBy.value] ?? 0
    const bv = b[sortBy.value] ?? 0
    if (sortBy.value === 'name') {
      return sortDir.value === 'asc' ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av))
    }
    return sortDir.value === 'asc' ? av - bv : bv - av
  })
})

// ── Aggregate stats ────────────────────────────────────────────────────────────
const totalUsers        = computed(() => provisioned.value.reduce((a, s) => a + (s.active_users ?? 0), 0))
const totalItOpen       = computed(() => provisioned.value.reduce((a, s) => a + (s.it_open ?? 0), 0))
const totalVehPending   = computed(() => provisioned.value.reduce((a, s) => a + (s.vehicle_pending ?? 0), 0))
const totalGsPending    = computed(() => provisioned.value.reduce((a, s) => a + (s.gs_pending ?? 0), 0))
const totalThisMonth    = computed(() => provisioned.value.reduce((a, s) => a + (s.requests_this_month ?? 0), 0))
const busiestCampus     = computed(() => {
  if (!provisioned.value.length) return null
  return [...provisioned.value].sort((a, b) => (b.requests_this_month ?? 0) - (a.requests_this_month ?? 0))[0]
})

// High-load campuses (>15 total pending)
const highLoad = computed(() => provisioned.value.filter(s => totalPending(s) > 15))

function totalPending(s) {
  return (s.it_open ?? 0) + (s.vehicle_pending ?? 0) + (s.gs_pending ?? 0)
}

function isModuleEnabled(modules, key) {
  return !(key in modules) || !!modules[key]
}

const MODULE_ICONS = {
  'mis':               ComputerDesktopIcon,
  'general-services':  TruckIcon,
  'data-management':   CircleStackIcon,
  'approval-inbox':    InboxStackIcon,
  'reports':           ChartBarIcon,
  'chat':              ChatBubbleLeftRightIcon,
  'digital-signature': PencilSquareIcon,
}

function refresh() {
  loading.value = true
  router.post(route('central.overview.refresh'), {}, {
    preserveScroll: true,
    onFinish: () => { loading.value = false },
  })
}

const COLS = [
  { key: 'name',                 label: 'Campus' },
  { key: 'active_users',        label: 'Users' },
  { key: 'it_open',             label: 'IT Open' },
  { key: 'it_completed_month',  label: 'IT Done/Mo' },
  { key: 'vehicle_pending',     label: 'Veh Pending' },
  { key: 'gs_pending',          label: 'GS Pending' },
  { key: 'requests_this_month', label: 'Total/Mo' },
]
</script>

<template>
  <Head title="System Overview" />

  <div class="min-h-screen bg-slate-50">

    <!-- Header -->
    <div class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between sticky top-0 z-10">
      <div class="flex items-center gap-3">
        <BuildingOffice2Icon class="h-6 w-6 text-primary-500" />
        <div>
          <h1 class="text-lg font-semibold text-slate-800">System Overview</h1>
          <p class="text-xs text-slate-400">Cross-campus analytics · data cached 5 min</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a :href="route('central.admin')" class="text-sm text-slate-500 hover:text-slate-700">← Admin</a>
        <button @click="refresh" :disabled="loading"
          class="inline-flex items-center gap-2 text-sm px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg disabled:opacity-60 transition-colors"
        >
          <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-6 space-y-6">

      <!-- Flash -->
      <div v-if="flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
        {{ flash.success }}
      </div>

      <!-- High-load alert -->
      <div v-if="highLoad.length" class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-start gap-3">
        <ExclamationTriangleIcon class="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
        <div class="text-sm text-amber-800">
          <span class="font-semibold">High backlog detected</span> on
          <span v-for="(c, i) in highLoad" :key="c.id">
            <a :href="route('central.overview.campus', c.id)" class="underline font-semibold hover:text-amber-900">{{ c.name }}</a>
            <span v-if="i < highLoad.length - 1">, </span>
          </span>
          — {{ highLoad.length === 1 ? 'this campus has' : 'these campuses have' }} more than 15 pending requests.
        </div>
      </div>

      <!-- System-wide stat cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <BuildingOffice2Icon class="h-5 w-5 text-primary-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-primary-600">{{ provisioned.length }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Campuses</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <UserGroupIcon class="h-5 w-5 text-slate-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-slate-700">{{ totalUsers.toLocaleString() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Active Users</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <ComputerDesktopIcon class="h-5 w-5 text-blue-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-blue-600">{{ totalItOpen.toLocaleString() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">IT Open</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <TruckIcon class="h-5 w-5 text-violet-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-violet-600">{{ totalVehPending.toLocaleString() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Veh Pending</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <WrenchScrewdriverIcon class="h-5 w-5 text-orange-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-orange-600">{{ totalGsPending.toLocaleString() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">GS Pending</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <CalendarDaysIcon class="h-5 w-5 text-emerald-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-emerald-600">{{ totalThisMonth.toLocaleString() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Requests/Mo</p>
        </div>
      </div>

      <!-- Busiest campus callout -->
      <div v-if="busiestCampus && busiestCampus.requests_this_month > 0" class="bg-primary-50 border border-primary-100 rounded-xl px-5 py-3 flex items-center gap-3">
        <ChartBarIcon class="h-5 w-5 text-primary-400 shrink-0" />
        <p class="text-sm text-primary-800">
          <span class="font-semibold">{{ busiestCampus.name }}</span> is the most active campus this month with
          <span class="font-semibold">{{ busiestCampus.requests_this_month }}</span> requests.
        </p>
        <a :href="route('central.overview.campus', busiestCampus.id)"
          class="ml-auto shrink-0 text-xs text-primary-600 hover:text-primary-800 font-medium flex items-center gap-1">
          View <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" />
        </a>
      </div>

      <!-- Comparison table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <Squares2X2Icon class="h-4 w-4 text-slate-400" />
            Campus Comparison
          </h2>
          <p class="text-xs text-slate-400">Click column headers to sort</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
              <tr>
                <th
                  v-for="col in COLS"
                  :key="col.key"
                  @click="sort(col.key)"
                  class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer hover:bg-slate-100 select-none whitespace-nowrap"
                >
                  <span class="flex items-center gap-1">
                    {{ col.label }}
                    <ChevronUpIcon v-if="sortBy === col.key && sortDir === 'asc'" class="h-3 w-3 text-primary-500" />
                    <ChevronDownIcon v-else-if="sortBy === col.key && sortDir === 'desc'" class="h-3 w-3 text-primary-500" />
                    <ChevronUpDownIcon v-else class="h-3 w-3 text-slate-300" />
                  </span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Modules</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr
                v-for="s in sortedProvisioned"
                :key="s.id"
                class="hover:bg-primary-50/40 transition-colors"
                :class="{ 'bg-amber-50/40': totalPending(s) > 15 }"
              >
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800 text-sm">{{ s.name }}</div>
                  <div class="text-xs text-slate-400 font-mono">{{ s.campus_code || s.id }}</div>
                </td>
                <td class="px-4 py-3 text-slate-700 tabular-nums">{{ s.active_users ?? '—' }}</td>
                <td class="px-4 py-3 tabular-nums">
                  <span :class="(s.it_open ?? 0) > 0 ? 'text-blue-600 font-semibold' : 'text-slate-400'">
                    {{ s.it_open ?? '—' }}
                  </span>
                </td>
                <td class="px-4 py-3 tabular-nums">
                  <span class="text-emerald-600">{{ s.it_completed_month ?? '—' }}</span>
                </td>
                <td class="px-4 py-3 tabular-nums">
                  <span :class="(s.vehicle_pending ?? 0) > 0 ? 'text-violet-600 font-semibold' : 'text-slate-400'">
                    {{ s.vehicle_pending ?? '—' }}
                  </span>
                </td>
                <td class="px-4 py-3 tabular-nums">
                  <span :class="(s.gs_pending ?? 0) > 0 ? 'text-orange-600 font-semibold' : 'text-slate-400'">
                    {{ s.gs_pending ?? '—' }}
                  </span>
                </td>
                <td class="px-4 py-3 tabular-nums font-semibold text-slate-700">{{ s.requests_this_month ?? '—' }}</td>
                <!-- Module dots -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <component
                      v-for="(label, key) in moduleLabels"
                      :key="key"
                      :is="MODULE_ICONS[key] || Squares2X2Icon"
                      :title="label"
                      class="h-3.5 w-3.5"
                      :class="isModuleEnabled(s.modules, key) ? 'text-emerald-500' : 'text-slate-200'"
                    />
                  </div>
                </td>
                <td class="px-4 py-3 text-right">
                  <a :href="route('central.overview.campus', s.id)"
                    class="inline-flex items-center gap-1 text-xs text-primary-500 hover:text-primary-700 font-medium whitespace-nowrap">
                    Details <ArrowTopRightOnSquareIcon class="h-3 w-3" />
                  </a>
                </td>
              </tr>
              <tr v-if="!provisioned.length">
                <td colspan="9" class="px-4 py-10 text-center text-slate-400 text-sm">No provisioned campuses.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Unprovisioned -->
      <div v-if="unprovisioned.length" class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-500 flex items-center gap-2 mb-3">
          <ExclamationTriangleIcon class="h-4 w-4 text-amber-400" />
          Not yet provisioned ({{ unprovisioned.length }})
        </h2>
        <div class="flex flex-wrap gap-2">
          <span v-for="s in unprovisioned" :key="s.id"
            class="text-xs bg-amber-50 border border-amber-100 text-amber-700 px-3 py-1.5 rounded-lg font-medium"
          >
            {{ s.name }} <span class="text-amber-400 font-mono ml-1">({{ s.id }})</span>
          </span>
        </div>
      </div>

    </div>
  </div>
</template>
