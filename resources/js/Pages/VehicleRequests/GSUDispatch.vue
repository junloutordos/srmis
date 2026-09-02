<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { TruckIcon, UserIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  requests: { type: Object, default: () => ({ data: [] }) },
  filters:  { type: Object, default: () => ({}) },
  drivers:  { type: Array,  default: () => [] },
  vehicles: { type: Array,  default: () => [] },
})

const search    = ref(props.filters?.search ?? '')
const isLoading = ref(false)

function applyFilters() {
  isLoading.value = true
  router.get(route('vehicle-requests.gsu-dispatch'), {
    search: search.value || undefined,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => { isLoading.value = false },
  })
}

const items = computed(() => props.requests?.data ?? props.requests ?? [])

// ── Assign modal ──────────────────────────────────────────────────────────────
const showModal   = ref(false)
const activeReq   = ref(null)
const selectedDriverId  = ref('')
const selectedVehicleId = ref('')
const assignLoading     = ref(false)

function openAssignModal(req) {
  activeReq.value = req
  selectedDriverId.value = ''
  // Pre-select a vehicle matching the requestor's preference, if any
  const match = props.vehicles.find(v => (v.name ?? '') === (req.vehicle_type ?? ''))
  selectedVehicleId.value = match?.id ?? ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  activeReq.value = null
  selectedDriverId.value = ''
  selectedVehicleId.value = ''
}

async function assignDriverAndVehicle() {
  if (!activeReq.value || !selectedDriverId.value || !selectedVehicleId.value) {
    Swal.fire({ icon: 'warning', title: 'Select both a driver and a vehicle' })
    return
  }
  assignLoading.value = true
  try {
    await axios.post(route('vehicle-requests.assign-driver', activeReq.value.id), {
      driver_id: selectedDriverId.value,
      vehicle_id: selectedVehicleId.value,
    })
    Swal.fire({ icon: 'success', title: 'Dispatched', text: 'Driver and vehicle assigned. Sent to Division Chief for approval.', timer: 1800, showConfirmButton: false })
    closeModal()
    router.reload({ only: ['requests'] })
  } catch (e) {
    const data = e?.response?.data
    if (data?.type && data?.message) {
      const kind  = data.type === 'vehicle' ? 'Vehicle conflict' : 'Driver conflict'
      const dates = Array.isArray(data.dates) && data.dates.length ? ` Dates: ${data.dates.join(', ')}` : ''
      Swal.fire({ icon: 'error', title: kind, text: `${data.message}${dates}` })
    } else {
      Swal.fire({ icon: 'error', title: 'Failed to dispatch', text: data?.message ?? 'Please try again.' })
    }
  } finally {
    assignLoading.value = false
  }
}

function formatDates(req) {
  if (req.date_needed_multiple?.length) {
    return req.date_needed_multiple.map(d => new Date(d).toLocaleDateString()).join(', ')
  }
  return req.date_needed ? new Date(req.date_needed).toLocaleDateString() : '—'
}
</script>

<template>
  <Head title="GSU Vehicle Dispatch" />
  <AdminLayout title="GSU Vehicle Dispatch">
    <div>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-2">
          <TruckIcon class="w-6 h-6 text-primary-600" />
          <h1 class="text-xl font-semibold text-slate-800">Vehicle Dispatch</h1>
        </div>
        <a
          :href="route('vehicle-requests.index')"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
        >
          All Requests
        </a>
      </div>

      <p class="text-sm text-slate-500 mb-5">
        These requests are awaiting a driver and vehicle assignment. Once dispatched, the request is sent to the
        requestor's Division Chief for approval.
      </p>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4">
        <div class="flex flex-wrap items-center gap-3">
          <input
            v-model="search"
            type="text"
            placeholder="Search..."
            @keydown.enter.prevent="applyFilters"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400 w-full sm:w-64"
          />
          <button
            @click="applyFilters"
            :disabled="isLoading"
            class="inline-flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50"
          >
            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
            Refresh
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="hidden sm:block overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Purpose</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Preferred Vehicle</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date(s)</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Time</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="req in items" :key="req.id" class="hover:bg-slate-50/60 transition-colors">
                <td class="px-4 py-3 text-slate-700">{{ req.id }}</td>
                <td class="px-4 py-3 text-slate-700">{{ req.requester?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-700 max-w-xs truncate">{{ req.purpose }}</td>
                <td class="px-4 py-3 text-slate-600">{{ req.vehicle_type || 'No preference' }}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">{{ formatDates(req) }}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">{{ req.time_of_departure }} – {{ req.eta }}</td>
                <td class="px-4 py-3 text-center">
                  <button
                    @click="openAssignModal(req)"
                    class="inline-flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm"
                  >
                    <UserIcon class="w-3.5 h-3.5" />
                    Assign
                  </button>
                </td>
              </tr>
              <tr v-if="items.length === 0">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">
                  No requests currently awaiting dispatch.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden p-4 space-y-3">
          <div v-for="req in items" :key="req.id" class="border border-slate-100 rounded-xl p-4">
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="font-semibold text-slate-800">{{ req.requester?.name ?? '—' }}</div>
                <div class="text-xs text-slate-400 mt-0.5">Request #{{ req.id }}</div>
              </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-y-1 text-sm text-slate-600">
              <div class="col-span-2"><span class="text-slate-400">Purpose:</span> {{ req.purpose }}</div>
              <div><span class="text-slate-400">Preferred:</span> {{ req.vehicle_type || 'None' }}</div>
              <div><span class="text-slate-400">Date:</span> {{ formatDates(req) }}</div>
            </div>
            <button
              @click="openAssignModal(req)"
              class="mt-3 w-full inline-flex items-center justify-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
            >
              <UserIcon class="w-4 h-4" />
              Assign Driver &amp; Vehicle
            </button>
          </div>
          <div v-if="items.length === 0" class="py-16 text-center text-slate-400 text-sm">
            No requests currently awaiting dispatch.
          </div>
        </div>
      </div>
    </div>

    <!-- Assign Driver + Vehicle Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
      <div class="bg-white w-full sm:h-auto sm:rounded-2xl sm:shadow-xl sm:max-w-md relative overflow-auto">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-800">Assign Driver &amp; Vehicle</h2>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <p v-if="activeReq" class="text-sm text-slate-500">
            Request #{{ activeReq.id }} — {{ activeReq.purpose }}
            <span v-if="activeReq.vehicle_type"> (preferred: {{ activeReq.vehicle_type }})</span>
          </p>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Vehicle <span class="text-red-500">*</span></label>
            <select v-model="selectedVehicleId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400 w-full">
              <option value="">Select vehicle</option>
              <option v-for="v in props.vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Driver <span class="text-red-500">*</span></label>
            <select v-model="selectedDriverId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400 w-full">
              <option value="">Select driver</option>
              <option v-for="d in props.drivers" :key="d.id" :value="d.id">{{ d.name }}{{ d.position ? ' — ' + d.position : '' }}</option>
            </select>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click.prevent="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
          <button
            @click.prevent="assignDriverAndVehicle"
            :disabled="assignLoading || !selectedDriverId || !selectedVehicleId"
            class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50"
          >
            {{ assignLoading ? 'Dispatching…' : 'Dispatch' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
