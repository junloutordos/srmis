<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
  ArrowPathIcon,
  BuildingOffice2Icon,
  UserGroupIcon,
  ComputerDesktopIcon,
  TruckIcon,
  WrenchScrewdriverIcon,
  CalendarDaysIcon,
  ChevronLeftIcon,
  StarIcon,
  InboxStackIcon,
  ChartBarIcon,
  ChatBubbleLeftRightIcon,
  PencilSquareIcon,
  Squares2X2Icon,
  CircleStackIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  CpuChipIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarSolidIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  campus:       { type: Object, default: () => ({}) },
  detail:       { type: Object, default: () => ({}) },
  moduleLabels: { type: Object, default: () => ({}) },
})

const page    = usePage()
const flash   = computed(() => page.props.flash || {})
const loading = ref(false)

function refresh() {
  loading.value = true
  router.post(route('central.overview.campus.refresh', props.campus.id), {}, {
    preserveScroll: true,
    onFinish: () => { loading.value = false },
  })
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function maxOf(obj) {
  return Math.max(1, ...Object.values(obj))
}
function maxOfArr(arr) {
  return Math.max(1, ...arr.map(r => r.count))
}
function pct(val, max) {
  return Math.round((val / max) * 100)
}
function isModuleEnabled(key) {
  const modules = props.campus.modules || {}
  return !(key in modules) || !!modules[key]
}

// ── IT status colours ──────────────────────────────────────────────────────────
const IT_STATUS_COLORS = {
  'For DC Approval':      { bar: 'bg-amber-400',   text: 'text-amber-700',   badge: 'bg-amber-50 border-amber-200 text-amber-700' },
  'Pending OCD Approval': { bar: 'bg-orange-400',  text: 'text-orange-700',  badge: 'bg-orange-50 border-orange-200 text-orange-700' },
  'In Progress':          { bar: 'bg-blue-400',    text: 'text-blue-700',    badge: 'bg-blue-50 border-blue-200 text-blue-700' },
  'Completed':            { bar: 'bg-emerald-400', text: 'text-emerald-700', badge: 'bg-emerald-50 border-emerald-200 text-emerald-700' },
  'Rejected':             { bar: 'bg-red-400',     text: 'text-red-700',     badge: 'bg-red-50 border-red-200 text-red-700' },
}

// ── GS status colours ──────────────────────────────────────────────────────────
const GS_STATUS_COLORS = {
  'Pending':           { bar: 'bg-amber-400',   text: 'text-amber-700' },
  'OCD Approved':      { bar: 'bg-blue-300',    text: 'text-blue-600' },
  'FAD Approved':      { bar: 'bg-sky-300',     text: 'text-sky-600' },
  'GSU Approved':      { bar: 'bg-cyan-300',    text: 'text-cyan-600' },
  'Division Approved': { bar: 'bg-blue-300',    text: 'text-blue-600' },
  'Approved':          { bar: 'bg-blue-500',    text: 'text-blue-700' },
  'Completed':         { bar: 'bg-emerald-400', text: 'text-emerald-700' },
  'Declined':          { bar: 'bg-red-400',     text: 'text-red-700' },
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

// ── Rating helpers ─────────────────────────────────────────────────────────────
const avgRating    = computed(() => props.detail.it_avg_rating ?? null)
const ratedCount   = computed(() => props.detail.it_rated_count ?? 0)
const fullStars    = computed(() => avgRating.value ? Math.floor(avgRating.value) : 0)
const hasHalfStar  = computed(() => avgRating.value ? (avgRating.value % 1) >= 0.5 : false)
const emptyStars   = computed(() => avgRating.value ? 5 - fullStars.value - (hasHalfStar.value ? 1 : 0) : 5)

// ── Computed bar data ──────────────────────────────────────────────────────────
const itStatusMax   = computed(() => maxOf(props.detail.it_statuses || {}))
const itCatMax      = computed(() => maxOfArr(props.detail.it_categories || []))
const empCatMax     = computed(() => maxOfArr(props.detail.emp_categories || []))
const ictMax        = computed(() => maxOfArr(props.detail.ict_equipment || []))
const pmsMax        = computed(() => maxOfArr(props.detail.pms_statuses || []))
</script>

<template>
  <Head :title="`${campus.name} — Campus Overview`" />

  <div class="min-h-screen bg-slate-50">

    <!-- Header -->
    <div class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between sticky top-0 z-10">
      <div class="flex items-center gap-3">
        <BuildingOffice2Icon class="h-6 w-6 text-primary-500" />
        <div>
          <h1 class="text-lg font-semibold text-slate-800">{{ campus.name }}</h1>
          <p class="text-xs text-slate-400">
            Campus Analytics
            <span v-if="campus.campus_code" class="font-mono ml-1 text-slate-300">· {{ campus.campus_code }}</span>
          </p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a :href="route('central.overview')" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
          <ChevronLeftIcon class="h-4 w-4" />
          Overview
        </a>
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

      <!-- Quick stats -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <CalendarDaysIcon class="h-5 w-5 text-primary-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-primary-600">{{ (detail.total_all_time ?? 0).toLocaleString() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">All-time Requests</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <ChartBarIcon class="h-5 w-5 text-blue-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-blue-600">{{ (detail.total_this_month ?? 0).toLocaleString() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">This Month</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <ClockIcon class="h-5 w-5 text-violet-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-violet-600">{{ (detail.total_this_week ?? 0).toLocaleString() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">This Week</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <CheckCircleIcon class="h-5 w-5 text-emerald-400 mx-auto mb-1" />
          <p class="text-2xl font-bold text-emerald-600">{{ (detail.it_completed_month ?? 0).toLocaleString() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">IT Done/Mo</p>
        </div>
      </div>

      <!-- ── IT Job Requests ──────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
          <ComputerDesktopIcon class="h-4 w-4 text-blue-400" />
          <h2 class="text-sm font-semibold text-slate-700">IT Job Requests</h2>
          <span class="ml-auto text-xs text-slate-400">{{ detail.it_this_month ?? 0 }} this month · {{ detail.it_this_week ?? 0 }} this week</span>
        </div>
        <div class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- Status breakdown -->
          <div>
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">By Status</p>
            <div class="space-y-2.5">
              <div v-for="(count, status) in (detail.it_statuses || {})" :key="status">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-xs text-slate-600 truncate max-w-[70%]">{{ status }}</span>
                  <span class="text-xs font-semibold tabular-nums" :class="IT_STATUS_COLORS[status]?.text || 'text-slate-600'">{{ count }}</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="IT_STATUS_COLORS[status]?.bar || 'bg-slate-300'"
                    :style="{ width: pct(count, itStatusMax) + '%' }"
                  />
                </div>
              </div>
              <div v-if="!Object.keys(detail.it_statuses || {}).length" class="text-xs text-slate-400 italic">No data</div>
            </div>
          </div>

          <!-- Top categories -->
          <div>
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Top Categories</p>
            <div class="space-y-2.5">
              <div v-for="row in (detail.it_categories || [])" :key="row.label">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-xs text-slate-600 truncate max-w-[75%]">{{ row.label || 'Uncategorized' }}</span>
                  <span class="text-xs font-semibold text-blue-600 tabular-nums">{{ row.count }}</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-full bg-blue-300 rounded-full transition-all" :style="{ width: pct(row.count, itCatMax) + '%' }" />
                </div>
              </div>
              <div v-if="!(detail.it_categories || []).length" class="text-xs text-slate-400 italic">No data</div>
            </div>
          </div>

          <!-- Priority + Rating -->
          <div class="space-y-6">
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">By Priority</p>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="row in (detail.it_priorities || [])"
                  :key="row.label"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border"
                  :class="{
                    'bg-red-50 border-red-200 text-red-700':    row.label?.toLowerCase() === 'high' || row.label?.toLowerCase() === 'urgent',
                    'bg-amber-50 border-amber-200 text-amber-700': row.label?.toLowerCase() === 'medium' || row.label?.toLowerCase() === 'normal',
                    'bg-slate-50 border-slate-200 text-slate-600':  !['high','urgent','medium','normal'].includes(row.label?.toLowerCase()),
                  }"
                >
                  {{ row.label }} <span class="font-bold">{{ row.count }}</span>
                </span>
                <span v-if="!(detail.it_priorities || []).length" class="text-xs text-slate-400 italic">No data</span>
              </div>
            </div>
            <div v-if="avgRating !== null">
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Avg. Satisfaction</p>
              <div class="flex items-center gap-2">
                <div class="flex items-center gap-0.5">
                  <StarSolidIcon v-for="n in fullStars"   :key="'f'+n" class="h-4 w-4 text-amber-400" />
                  <StarIcon      v-for="n in emptyStars"  :key="'e'+n" class="h-4 w-4 text-slate-200" />
                </div>
                <span class="text-sm font-bold text-amber-500">{{ avgRating }}</span>
                <span class="text-xs text-slate-400">({{ ratedCount }} rated)</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- ── General Services ─────────────────────────────────────────────── -->
      <div>
        <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3 flex items-center gap-2">
          <TruckIcon class="h-4 w-4 text-slate-400" />
          General Services
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- Vehicle -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-50 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <TruckIcon class="h-4 w-4 text-violet-400" />
                <span class="text-sm font-semibold text-slate-700">Vehicle Requests</span>
              </div>
              <span class="text-xs text-slate-400">{{ detail.vehicle_this_month ?? 0 }}/mo</span>
            </div>
            <div class="p-4 space-y-2">
              <div v-for="(count, status) in (detail.vehicle_statuses || {})" :key="status">
                <div class="flex justify-between mb-0.5">
                  <span class="text-xs text-slate-600">{{ status }}</span>
                  <span class="text-xs font-semibold tabular-nums" :class="GS_STATUS_COLORS[status]?.text || 'text-slate-600'">{{ count }}</span>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full" :class="GS_STATUS_COLORS[status]?.bar || 'bg-slate-300'"
                    :style="{ width: pct(count, maxOf(detail.vehicle_statuses || { _: 1 })) + '%' }" />
                </div>
              </div>
              <div v-if="!Object.keys(detail.vehicle_statuses || {}).length" class="text-xs text-slate-400 italic py-2">No data</div>
            </div>
          </div>

          <!-- Work -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-50 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <WrenchScrewdriverIcon class="h-4 w-4 text-orange-400" />
                <span class="text-sm font-semibold text-slate-700">Work Requests</span>
              </div>
              <span class="text-xs text-slate-400">{{ detail.work_this_month ?? 0 }}/mo</span>
            </div>
            <div class="p-4 space-y-2">
              <div v-for="(count, status) in (detail.work_statuses || {})" :key="status">
                <div class="flex justify-between mb-0.5">
                  <span class="text-xs text-slate-600">{{ status }}</span>
                  <span class="text-xs font-semibold tabular-nums" :class="GS_STATUS_COLORS[status]?.text || 'text-slate-600'">{{ count }}</span>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full" :class="GS_STATUS_COLORS[status]?.bar || 'bg-slate-300'"
                    :style="{ width: pct(count, maxOf(detail.work_statuses || { _: 1 })) + '%' }" />
                </div>
              </div>
              <div v-if="!Object.keys(detail.work_statuses || {}).length" class="text-xs text-slate-400 italic py-2">No data</div>
            </div>
          </div>

          <!-- Service -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-50 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Squares2X2Icon class="h-4 w-4 text-sky-400" />
                <span class="text-sm font-semibold text-slate-700">Service Requests</span>
              </div>
              <span class="text-xs text-slate-400">{{ detail.service_this_month ?? 0 }}/mo</span>
            </div>
            <div class="p-4 space-y-2">
              <div v-for="(count, status) in (detail.service_statuses || {})" :key="status">
                <div class="flex justify-between mb-0.5">
                  <span class="text-xs text-slate-600">{{ status }}</span>
                  <span class="text-xs font-semibold tabular-nums" :class="GS_STATUS_COLORS[status]?.text || 'text-slate-600'">{{ count }}</span>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full" :class="GS_STATUS_COLORS[status]?.bar || 'bg-slate-300'"
                    :style="{ width: pct(count, maxOf(detail.service_statuses || { _: 1 })) + '%' }" />
                </div>
              </div>
              <div v-if="!Object.keys(detail.service_statuses || {}).length" class="text-xs text-slate-400 italic py-2">No data</div>
            </div>
          </div>

          <!-- Facility -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-50 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <BuildingOffice2Icon class="h-4 w-4 text-teal-400" />
                <span class="text-sm font-semibold text-slate-700">Facility Requests</span>
              </div>
              <span class="text-xs text-slate-400">{{ detail.facility_this_month ?? 0 }}/mo</span>
            </div>
            <div class="p-4 space-y-2">
              <div v-for="(count, status) in (detail.facility_statuses || {})" :key="status">
                <div class="flex justify-between mb-0.5">
                  <span class="text-xs text-slate-600">{{ status }}</span>
                  <span class="text-xs font-semibold tabular-nums" :class="GS_STATUS_COLORS[status]?.text || 'text-slate-600'">{{ count }}</span>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full" :class="GS_STATUS_COLORS[status]?.bar || 'bg-slate-300'"
                    :style="{ width: pct(count, maxOf(detail.facility_statuses || { _: 1 })) + '%' }" />
                </div>
              </div>
              <div v-if="!Object.keys(detail.facility_statuses || {}).length" class="text-xs text-slate-400 italic py-2">No data</div>
            </div>
          </div>

        </div>
      </div>

      <!-- ── Users + ICT (bottom row) ────────────────────────────────────── -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Users panel -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <UserGroupIcon class="h-4 w-4 text-slate-400" />
            <h2 class="text-sm font-semibold text-slate-700">Users</h2>
            <span class="ml-auto text-xs font-bold text-slate-600">{{ (detail.active_users ?? 0).toLocaleString() }} active</span>
          </div>
          <div class="p-5 space-y-6">
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">By Category</p>
              <div class="space-y-2">
                <div v-for="row in (detail.emp_categories || [])" :key="row.label">
                  <div class="flex justify-between mb-0.5">
                    <span class="text-xs text-slate-600 truncate max-w-[70%]">{{ row.label }}</span>
                    <span class="text-xs font-semibold text-primary-600 tabular-nums">{{ row.count }}</span>
                  </div>
                  <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-primary-300 rounded-full" :style="{ width: pct(row.count, empCatMax) + '%' }" />
                  </div>
                </div>
                <div v-if="!(detail.emp_categories || []).length" class="text-xs text-slate-400 italic">No data</div>
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">By Sex</p>
              <div class="space-y-2">
                <div v-for="row in (detail.sex_breakdown || [])" :key="row.label">
                  <div class="flex justify-between mb-0.5">
                    <span class="text-xs text-slate-600">{{ row.label }}</span>
                    <span class="text-xs font-semibold text-primary-600 tabular-nums">{{ row.count }}</span>
                  </div>
                  <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-primary-400 rounded-full" :style="{ width: pct(row.count, detail.active_users || 1) + '%' }" />
                  </div>
                </div>
                <div v-if="!(detail.sex_breakdown || []).length" class="text-xs text-slate-400 italic">No data</div>
              </div>
            </div>
          </div>
        </div>

        <!-- ICT Assets panel -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <CpuChipIcon class="h-4 w-4 text-slate-400" />
            <h2 class="text-sm font-semibold text-slate-700">ICT Assets</h2>
          </div>
          <div class="p-5 space-y-6">
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Equipment by Status</p>
              <div class="space-y-2">
                <div v-for="row in (detail.ict_equipment || [])" :key="row.label">
                  <div class="flex justify-between mb-0.5">
                    <span class="text-xs text-slate-600 truncate max-w-[70%]">{{ row.label || 'Unknown' }}</span>
                    <span class="text-xs font-semibold text-slate-700 tabular-nums">{{ row.count }}</span>
                  </div>
                  <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full"
                      :class="{
                        'bg-emerald-400': /active|working|good/i.test(row.label),
                        'bg-red-400':     /defective|repair|broken/i.test(row.label),
                        'bg-orange-400':  /disposal|retire/i.test(row.label),
                        'bg-slate-300':   true,
                      }"
                      :style="{ width: pct(row.count, ictMax) + '%' }"
                    />
                  </div>
                </div>
                <div v-if="!(detail.ict_equipment || []).length" class="text-xs text-slate-400 italic">No data</div>
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">PMS by Status</p>
              <div class="space-y-2">
                <div v-for="row in (detail.pms_statuses || [])" :key="row.label">
                  <div class="flex justify-between mb-0.5">
                    <span class="text-xs text-slate-600 truncate max-w-[70%]">{{ row.label || 'Unknown' }}</span>
                    <span class="text-xs font-semibold text-slate-700 tabular-nums">{{ row.count }}</span>
                  </div>
                  <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full"
                      :class="{
                        'bg-emerald-400': /done|complete|passed/i.test(row.label),
                        'bg-amber-400':   /scheduled|pending/i.test(row.label),
                        'bg-red-400':     /fail|overdue/i.test(row.label),
                        'bg-slate-300':   true,
                      }"
                      :style="{ width: pct(row.count, pmsMax) + '%' }"
                    />
                  </div>
                </div>
                <div v-if="!(detail.pms_statuses || []).length" class="text-xs text-slate-400 italic">No data</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Module status panel -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <Squares2X2Icon class="h-4 w-4 text-slate-400" />
            <h2 class="text-sm font-semibold text-slate-700">Modules</h2>
          </div>
          <div class="p-5">
            <div class="space-y-3">
              <div
                v-for="(label, key) in moduleLabels"
                :key="key"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg"
                :class="isModuleEnabled(key) ? 'bg-emerald-50' : 'bg-slate-50'"
              >
                <component
                  :is="MODULE_ICONS[key] || Squares2X2Icon"
                  class="h-5 w-5 shrink-0"
                  :class="isModuleEnabled(key) ? 'text-emerald-500' : 'text-slate-300'"
                />
                <span class="text-sm flex-1" :class="isModuleEnabled(key) ? 'text-emerald-800 font-medium' : 'text-slate-400'">
                  {{ label }}
                </span>
                <CheckCircleIcon v-if="isModuleEnabled(key)" class="h-4 w-4 text-emerald-400 shrink-0" />
                <XCircleIcon v-else class="h-4 w-4 text-slate-300 shrink-0" />
              </div>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>
