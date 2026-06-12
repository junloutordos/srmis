<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Chart as ChartJS,
  Title, Tooltip, Legend,
  ArcElement,
  CategoryScale, LinearScale,
  PointElement, LineElement, BarElement,
  Filler,
} from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
  UsersIcon,
  BuildingOffice2Icon,
  InboxStackIcon,
  ComputerDesktopIcon,
} from '@heroicons/vue/24/outline'

ChartJS.register(
  Title, Tooltip, Legend,
  ArcElement,
  CategoryScale, LinearScale,
  PointElement, LineElement, BarElement,
  Filler,
)

const page = usePage()
const authUser = computed(() => page.props.auth?.user)
const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 18) return 'Good afternoon'
  return 'Good evening'
})
const today = computed(() =>
  new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
)

const props = defineProps({
  campusName:           { type: String, default: '' },
  totalEmployees:       { type: Number, default: 0 },
  facultyCount:         { type: Number, default: 0 },
  staffCount:           { type: Number, default: 0 },
  employeeMaleCount:    { type: Number, default: 0 },
  employeeFemaleCount:  { type: Number, default: 0 },
  activeDivisions:      { type: Number, default: 0 },
  employeesByDivision:  { type: Array,  default: () => [] },
  itjrByCategory:       { type: Array,  default: () => [] },
  requestOverview:      { type: Array,  default: () => [] },
  totalPendingRequests: { type: Number, default: 0 },
  monthlyTrends:        { type: Object, default: () => ({ labels: [], datasets: [] }) },
})

const totalItjr = computed(() => props.itjrByCategory.reduce((sum, r) => sum + r.total, 0))

// ── Charts ───────────────────────────────────────────────────────────────────
const palette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#14b8a6', '#e11d48', '#6366f1']

const trendData = computed(() => ({
  labels: props.monthlyTrends.labels,
  datasets: props.monthlyTrends.datasets.map((d) => ({
    label: d.label,
    data: d.data,
    borderColor: d.color,
    backgroundColor: d.color + '22',
    fill: false,
    tension: 0.35,
    pointRadius: 3,
  })),
}))

const itjrDoughnut = computed(() => ({
  labels: props.itjrByCategory.map((r) => r.category),
  datasets: [{
    data: props.itjrByCategory.map((r) => r.total),
    backgroundColor: palette,
    borderWidth: 1,
  }],
}))

const divisionBar = computed(() => ({
  labels: props.employeesByDivision.map((d) => d.division),
  datasets: [{
    label: 'Employees',
    data: props.employeesByDivision.map((d) => d.count),
    backgroundColor: '#6366f1',
    borderRadius: 6,
  }],
}))

const baseOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
}
</script>

<template>
  <AdminLayout title="Dashboard">
    <div class="space-y-6">
      <!-- Greeting -->
      <div class="rounded-2xl bg-gradient-to-r from-indigo-900 via-indigo-700 to-blue-600 p-6 text-white shadow">
        <p class="text-sm text-indigo-200">{{ today }}</p>
        <h1 class="mt-1 text-2xl font-bold">{{ greeting }}, {{ authUser?.name }}!</h1>
        <p class="mt-1 text-sm text-indigo-100">
          Service Requests Management Information System<span v-if="campusName"> — {{ campusName }}</span>
        </p>
      </div>

      <!-- Stat cards -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
          <div class="flex items-center gap-3">
            <div class="rounded-xl bg-indigo-50 p-2.5"><UsersIcon class="h-6 w-6 text-indigo-600" /></div>
            <div>
              <p class="text-2xl font-bold text-slate-800">{{ totalEmployees.toLocaleString('en-PH') }}</p>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Active Employees</p>
            </div>
          </div>
          <p class="mt-2 text-xs text-slate-400">{{ facultyCount }} faculty · {{ staffCount }} staff</p>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
          <div class="flex items-center gap-3">
            <div class="rounded-xl bg-emerald-50 p-2.5"><BuildingOffice2Icon class="h-6 w-6 text-emerald-600" /></div>
            <div>
              <p class="text-2xl font-bold text-slate-800">{{ activeDivisions }}</p>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Active Divisions</p>
            </div>
          </div>
          <p class="mt-2 text-xs text-slate-400">{{ employeeMaleCount }} male · {{ employeeFemaleCount }} female</p>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
          <div class="flex items-center gap-3">
            <div class="rounded-xl bg-amber-50 p-2.5"><InboxStackIcon class="h-6 w-6 text-amber-600" /></div>
            <div>
              <p class="text-2xl font-bold text-slate-800">{{ totalPendingRequests }}</p>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Pending Requests</p>
            </div>
          </div>
          <p class="mt-2 text-xs text-slate-400">across all request modules</p>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
          <div class="flex items-center gap-3">
            <div class="rounded-xl bg-sky-50 p-2.5"><ComputerDesktopIcon class="h-6 w-6 text-sky-600" /></div>
            <div>
              <p class="text-2xl font-bold text-slate-800">{{ totalItjr.toLocaleString('en-PH') }}</p>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">IT Job Requests</p>
            </div>
          </div>
          <p class="mt-2 text-xs text-slate-400">{{ itjrByCategory.length }} categories</p>
        </div>
      </div>

      <!-- Request overview table -->
      <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Request Overview</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100">
                <th class="py-2 pr-4">Module</th>
                <th class="py-2 pr-4 text-right">Pending</th>
                <th class="py-2 pr-4 text-right">Completed</th>
                <th class="py-2 text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in requestOverview" :key="row.label" class="border-b border-slate-50 last:border-0">
                <td class="py-2.5 pr-4 font-medium text-slate-700">{{ row.label }}</td>
                <td class="py-2.5 pr-4 text-right">
                  <span :class="row.pending > 0 ? 'text-amber-600 font-semibold' : 'text-slate-400'">{{ row.pending }}</span>
                </td>
                <td class="py-2.5 pr-4 text-right text-emerald-600">{{ row.completed }}</td>
                <td class="py-2.5 text-right text-slate-600">{{ row.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100 lg:col-span-2">
          <h2 class="text-sm font-semibold text-slate-700 mb-3">Monthly Request Trends (last 6 months)</h2>
          <div class="h-72">
            <Line :data="trendData" :options="baseOptions" />
          </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700 mb-3">IT Job Requests by Category</h2>
          <div class="h-72">
            <Doughnut v-if="itjrByCategory.length" :data="itjrDoughnut" :options="baseOptions" />
            <p v-else class="flex h-full items-center justify-center text-sm text-slate-400">No data yet</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Employees by Division</h2>
        <div class="h-64">
          <Bar v-if="employeesByDivision.length" :data="divisionBar" :options="baseOptions" />
          <p v-else class="flex h-full items-center justify-center text-sm text-slate-400">No data yet</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
