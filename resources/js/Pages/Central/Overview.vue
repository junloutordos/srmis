<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  XCircleIcon,
  UserGroupIcon,
  ComputerDesktopIcon,
  TruckIcon,
  WrenchScrewdriverIcon,
  ExclamationTriangleIcon,
  BuildingOffice2Icon,
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

function isModuleEnabled(modules, key) {
  return !(key in modules) || !!modules[key]
}

const MODULE_ICONS = {
  'mis':               '🖥',
  'general-services':  '🚛',
  'data-management':   '🗂',
  'approval-inbox':    '📥',
  'reports':           '📊',
  'chat':              '💬',
  'digital-signature': '✍',
}

function refresh() {
  loading.value = true
  router.post(route('central.overview.refresh'), {}, {
    preserveScroll: true,
    onFinish: () => { loading.value = false },
  })
}

function totalPending(s) {
  return (s.it_open ?? 0) + (s.vehicle_pending ?? 0) + (s.gs_pending ?? 0)
}
</script>

<template>
  <Head title="System Overview" />

  <div class="min-h-screen bg-slate-50">
    <!-- Header bar -->
    <div class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <BuildingOffice2Icon class="h-6 w-6 text-indigo-500" />
        <div>
          <h1 class="text-lg font-semibold text-slate-800">System Overview</h1>
          <p class="text-xs text-slate-400">Cross-campus status · cached 5 min</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a :href="route('central.admin')" class="text-sm text-slate-500 hover:text-slate-700 underline">← Admin</a>
        <button
          @click="refresh"
          :disabled="loading"
          class="inline-flex items-center gap-2 text-sm px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors disabled:opacity-60"
        >
          <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
      </div>
    </div>

    <div class="px-6 py-6 space-y-6 max-w-7xl mx-auto">

      <!-- Flash -->
      <div v-if="flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
        {{ flash.success }}
      </div>

      <!-- Summary row -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-indigo-600">{{ provisioned.length }}</p>
          <p class="text-xs text-slate-500 mt-1">Provisioned campuses</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-slate-700">{{ provisioned.reduce((a, s) => a + (s.active_users ?? 0), 0) }}</p>
          <p class="text-xs text-slate-500 mt-1">Active users (total)</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-amber-600">{{ provisioned.reduce((a, s) => a + (s.it_open ?? 0), 0) }}</p>
          <p class="text-xs text-slate-500 mt-1">Open IT requests</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-orange-600">{{ provisioned.reduce((a, s) => a + (s.vehicle_pending ?? 0) + (s.gs_pending ?? 0), 0) }}</p>
          <p class="text-xs text-slate-500 mt-1">Pending GS requests</p>
        </div>
      </div>

      <!-- Campus grid -->
      <div>
        <h2 class="text-sm font-semibold text-slate-600 mb-3">Provisioned Campuses ({{ provisioned.length }})</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div
            v-for="s in provisioned"
            :key="s.id"
            class="bg-white rounded-xl border shadow-sm p-4 space-y-3 hover:border-indigo-200 transition-colors"
            :class="totalPending(s) > 10 ? 'border-amber-200' : 'border-slate-100'"
          >
            <!-- Campus header -->
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-semibold text-slate-800 text-sm leading-tight">{{ s.name }}</p>
                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ s.campus_code || s.id }}</p>
              </div>
              <span v-if="totalPending(s) > 10"
                class="shrink-0 text-[10px] bg-amber-100 text-amber-700 font-semibold px-1.5 py-0.5 rounded-full"
              >
                High
              </span>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-2 text-xs">
              <div class="flex items-center gap-1.5 text-slate-600">
                <UserGroupIcon class="h-3.5 w-3.5 text-slate-400 shrink-0" />
                <span class="font-semibold text-slate-800">{{ s.active_users ?? '—' }}</span> users
              </div>
              <div class="flex items-center gap-1.5 text-slate-600">
                <ComputerDesktopIcon class="h-3.5 w-3.5 text-blue-400 shrink-0" />
                <span class="font-semibold" :class="(s.it_open ?? 0) > 0 ? 'text-blue-600' : 'text-slate-800'">{{ s.it_open ?? '—' }}</span> IT open
              </div>
              <div class="flex items-center gap-1.5 text-slate-600">
                <TruckIcon class="h-3.5 w-3.5 text-violet-400 shrink-0" />
                <span class="font-semibold" :class="(s.vehicle_pending ?? 0) > 0 ? 'text-violet-600' : 'text-slate-800'">{{ s.vehicle_pending ?? '—' }}</span> vehicles
              </div>
              <div class="flex items-center gap-1.5 text-slate-600">
                <WrenchScrewdriverIcon class="h-3.5 w-3.5 text-orange-400 shrink-0" />
                <span class="font-semibold" :class="(s.gs_pending ?? 0) > 0 ? 'text-orange-600' : 'text-slate-800'">{{ s.gs_pending ?? '—' }}</span> GS pending
              </div>
            </div>

            <!-- Module pills -->
            <div class="flex flex-wrap gap-1">
              <span
                v-for="(label, key) in moduleLabels"
                :key="key"
                :title="label"
                :class="isModuleEnabled(s.modules, key)
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-100'
                  : 'bg-slate-50 text-slate-300 border-slate-100 line-through'"
                class="text-[10px] border px-1.5 py-0.5 rounded font-medium"
              >
                {{ MODULE_ICONS[key] || '·' }} {{ key.replace('general-services','GS').replace('data-management','DM').replace('approval-inbox','Inbox').replace('digital-signature','Sig') }}
              </span>
            </div>

            <!-- Link -->
            <a :href="route('central.admin')" class="block text-xs text-indigo-500 hover:text-indigo-700 text-right">
              Manage →
            </a>
          </div>
        </div>
      </div>

      <!-- Unprovisioned campuses -->
      <div v-if="unprovisioned.length">
        <h2 class="text-sm font-semibold text-slate-400 mb-3 flex items-center gap-2">
          <ExclamationTriangleIcon class="h-4 w-4 text-amber-400" />
          Not yet provisioned ({{ unprovisioned.length }})
        </h2>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="s in unprovisioned"
            :key="s.id"
            class="text-xs bg-amber-50 border border-amber-100 text-amber-700 px-3 py-1.5 rounded-lg font-medium"
          >
            {{ s.name }} <span class="text-amber-400 font-mono">({{ s.id }})</span>
          </span>
        </div>
      </div>

    </div>
  </div>
</template>
