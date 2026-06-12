<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  campuses:   { type: Array,  default: () => [] },
  modules:    { type: Object, default: () => ({}) },
  state:      { type: Object, default: () => ({}) },
  phpVersion: { type: String, default: '' },
})

const steps = [
  { key: 'database',   label: 'Database' },
  { key: 'domain',     label: 'Domain' },
  { key: 'storage',    label: 'S3 Storage' },
  { key: 'websockets', label: 'WebSockets' },
  { key: 'superadmin', label: 'Superadmin' },
  { key: 'tenants',    label: 'Campuses' },
]

const current = ref(0)
const busy = ref(false)
const error = ref('')
const dbResult = ref(null)

// ── Step forms ────────────────────────────────────────────────────────────────
const domain = ref(props.state.domain || '')
const storage = ref({ key: '', secret: '', region: 'ap-southeast-1', bucket: '' })
const sockets = ref({ app_id: '', app_key: '', app_secret: '', host: '', port: 443, scheme: 'https' })
const superadmin = ref({ name: '', email: '', password: '', password_confirmation: '' })

// Campus selection — all presets checked by default, every module enabled
const tenantRows = ref(props.campuses.map((c) => ({
  slug: c.slug,
  code: c.code,
  name: c.name,
  selected: true,
  modules: Object.fromEntries(Object.keys(props.modules).map((m) => [m, true])),
})))

const selectedCount = computed(() => tenantRows.value.filter((t) => t.selected).length)
const finished = ref(false)
const provisioned = ref([])

async function post(url, payload) {
  busy.value = true
  error.value = ''
  try {
    const { data } = await axios.post(url, payload)
    busy.value = false
    return data
  } catch (e) {
    busy.value = false
    error.value = e.response?.data?.error
      || Object.values(e.response?.data?.errors || {}).flat().join(' ')
      || e.message
    return null
  }
}

async function runStep() {
  const step = steps[current.value].key

  if (step === 'database') {
    const data = await post(route('setup.database'), {})
    if (data?.ok) { dbResult.value = data; current.value++ }
  } else if (step === 'domain') {
    const data = await post(route('setup.domain'), { domain: domain.value })
    if (data?.ok) current.value++
  } else if (step === 'storage') {
    const data = await post(route('setup.storage'), storage.value)
    if (data?.ok) current.value++
  } else if (step === 'websockets') {
    const data = await post(route('setup.websockets'), sockets.value)
    if (data?.ok) current.value++
  } else if (step === 'superadmin') {
    const data = await post(route('setup.superadmin'), superadmin.value)
    if (data?.ok) current.value++
  } else if (step === 'tenants') {
    const tenants = tenantRows.value
      .filter((t) => t.selected)
      .map((t) => ({ slug: t.slug, name: t.name, campus_code: t.code, modules: t.modules }))
    const data = await post(route('setup.finish'), { tenants })
    if (data?.ok) {
      finished.value = true
      provisioned.value = data.provisioned
      setTimeout(() => (window.location.href = data.redirect), 2500)
    }
  }
}

function skipStep() {
  if (current.value < steps.length - 1) current.value++
}
</script>

<template>
  <Head title="SRMIS Setup" />

  <div class="min-h-screen bg-gradient-to-br from-indigo-950 via-indigo-900 to-blue-800 px-4 py-10">
    <div class="mx-auto max-w-3xl">
      <div class="text-center mb-8">
        <img src="/images/pshslogo.png" alt="PSHS Logo" class="mx-auto h-14 w-14 object-contain" />
        <h1 class="mt-3 text-2xl font-bold text-white">SRMIS First-Run Setup</h1>
        <p class="text-sm text-indigo-200">Configure this instance for the OED and the 16 PSHS campuses.</p>
      </div>

      <!-- Step indicator -->
      <ol class="flex items-center justify-center gap-2 mb-6 flex-wrap">
        <li v-for="(s, i) in steps" :key="s.key" class="flex items-center gap-2">
          <span
            :class="[
              'flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold',
              i < current ? 'bg-emerald-500 text-white' : i === current ? 'bg-white text-indigo-900' : 'bg-white/20 text-white/70',
            ]"
          >{{ i + 1 }}</span>
          <span :class="['text-xs', i === current ? 'text-white font-semibold' : 'text-indigo-200/70']">{{ s.label }}</span>
        </li>
      </ol>

      <div class="rounded-2xl bg-white p-8 shadow-xl">
        <div v-if="error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {{ error }}
        </div>

        <!-- Finished -->
        <div v-if="finished" class="text-center py-8">
          <p class="text-3xl">🎉</p>
          <h2 class="mt-2 text-lg font-bold text-slate-800">Installation complete</h2>
          <p class="mt-1 text-sm text-slate-500">
            Provisioned {{ provisioned.length }} campus tenant(s): {{ provisioned.join(', ') }}.
          </p>
          <p class="mt-2 text-xs text-slate-400">Redirecting to the admin panel…</p>
        </div>

        <!-- Step 1: Database -->
        <div v-else-if="steps[current].key === 'database'">
          <h2 class="text-lg font-bold text-slate-800">Database</h2>
          <p class="mt-1 text-sm text-slate-500">
            Database credentials are supplied through the container environment
            (SSM Parameter Store in production). This step verifies connectivity to the
            MySQL server and runs the central migrations (tenants, domains, settings).
          </p>
          <div class="mt-4 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600 space-y-1">
            <p>PHP version: <span class="font-mono">{{ phpVersion }}</span></p>
            <p>Connection status:
              <span :class="state.database_ok ? 'text-emerald-600 font-semibold' : 'text-red-600 font-semibold'">
                {{ state.database_ok ? 'reachable' : 'unreachable' }}
              </span>
            </p>
            <p v-if="dbResult">Connected to <span class="font-mono">{{ dbResult.database }}</span> — central migrations applied.</p>
          </div>
        </div>

        <!-- Step 2: Domain -->
        <div v-else-if="steps[current].key === 'domain'">
          <h2 class="text-lg font-bold text-slate-800">Instance Domain</h2>
          <p class="mt-1 text-sm text-slate-500">
            The central domain of this SRMIS instance. Each campus is served on a subdomain
            (e.g. <span class="font-mono">oed.srmis.pshs.edu.ph</span>, <span class="font-mono">crc.srmis.pshs.edu.ph</span>),
            so the Cloudflare SSL certificate must cover <span class="font-mono">*.{{ domain || 'your-domain' }}</span>.
          </p>
          <label class="block text-sm font-medium text-slate-700 mt-4 mb-1">Central domain</label>
          <input v-model="domain" type="text" placeholder="srmis.pshs.edu.ph"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
        </div>

        <!-- Step 3: S3 -->
        <div v-else-if="steps[current].key === 'storage'">
          <h2 class="text-lg font-bold text-slate-800">S3 File Storage</h2>
          <p class="mt-1 text-sm text-slate-500">
            All uploads are stored in a private S3 bucket; each campus's files are isolated
            under a <span class="font-mono">tenants/&lt;campus&gt;/</span> prefix. The secret key is encrypted at rest.
          </p>
          <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Access key ID</label>
              <input v-model="storage.key" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Secret access key</label>
              <input v-model="storage.secret" type="password" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Region</label>
              <input v-model="storage.region" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Bucket</label>
              <input v-model="storage.bucket" type="text" placeholder="srmis-storage" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
          </div>
        </div>

        <!-- Step 4: WebSockets -->
        <div v-else-if="steps[current].key === 'websockets'">
          <h2 class="text-lg font-bold text-slate-800">WebSockets (Soketi)</h2>
          <p class="mt-1 text-sm text-slate-500">
            Pusher-compatible credentials for real-time chat and notifications.
            The app secret is encrypted at rest.
          </p>
          <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">App ID</label>
              <input v-model="sockets.app_id" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">App key</label>
              <input v-model="sockets.app_key" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">App secret</label>
              <input v-model="sockets.app_secret" type="password" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Host</label>
              <input v-model="sockets.host" type="text" placeholder="soketi.srmis.pshs.edu.ph" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Port</label>
              <input v-model.number="sockets.port" type="number" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Scheme</label>
              <select v-model="sockets.scheme" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                <option value="https">https</option>
                <option value="http">http</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Step 5: Superadmin -->
        <div v-else-if="steps[current].key === 'superadmin'">
          <h2 class="text-lg font-bold text-slate-800">System Superadmin</h2>
          <p class="mt-1 text-sm text-slate-500">
            This account manages the SRMIS instance itself — provisioning campuses and toggling
            modules. It is separate from campus administrator accounts.
          </p>
          <div class="mt-4 space-y-3">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Full name</label>
              <input v-model="superadmin.name" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
              <input v-model="superadmin.email" type="email" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password (min 12 chars)</label>
                <input v-model="superadmin.password" type="password" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Confirm password</label>
                <input v-model="superadmin.password_confirmation" type="password" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>
            </div>
          </div>
        </div>

        <!-- Step 6: Campuses -->
        <div v-else-if="steps[current].key === 'tenants'">
          <h2 class="text-lg font-bold text-slate-800">Campus Tenants</h2>
          <p class="mt-1 text-sm text-slate-500">
            Each selected campus gets its own database schema
            (<span class="font-mono">srmis_&lt;slug&gt;</span>) and subdomain. Untick modules a
            campus should not see — you can change this anytime from the admin panel.
          </p>

          <div class="mt-4 max-h-96 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
            <div v-for="t in tenantRows" :key="t.slug" class="px-4 py-3">
              <label class="flex items-center gap-3">
                <input v-model="t.selected" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                <span class="flex-1">
                  <span class="block text-sm font-medium text-slate-800">{{ t.name }}</span>
                  <span class="block text-xs text-slate-400 font-mono">{{ t.code }} · {{ t.slug }}.&lt;domain&gt; · srmis_{{ t.slug }}</span>
                </span>
              </label>
              <div v-if="t.selected" class="mt-2 ml-7 flex flex-wrap gap-x-4 gap-y-1">
                <label v-for="(label, key) in modules" :key="key" class="flex items-center gap-1.5 text-xs text-slate-600">
                  <input v-model="t.modules[key]" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5" />
                  {{ key }}
                </label>
              </div>
            </div>
          </div>
          <p class="mt-2 text-xs text-slate-400">
            {{ selectedCount }} campus(es) selected. Provisioning creates and migrates each schema — this may take a few minutes.
          </p>
        </div>

        <!-- Actions -->
        <div v-if="!finished" class="mt-6 flex items-center justify-between">
          <button
            v-if="current > 0"
            @click="current--"
            class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100"
          >← Back</button>
          <span v-else></span>

          <div class="flex gap-2">
            <button
              v-if="['storage', 'websockets'].includes(steps[current].key)"
              @click="skipStep"
              class="px-4 py-2 rounded-lg text-sm font-medium text-slate-500 hover:bg-slate-100"
            >Skip for now</button>
            <button
              @click="runStep"
              :disabled="busy"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium disabled:opacity-60"
            >
              {{ busy ? 'Working…' : steps[current].key === 'tenants' ? `Provision ${selectedCount} campus(es) & finish` : steps[current].key === 'database' ? 'Check & migrate' : 'Save & continue' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
