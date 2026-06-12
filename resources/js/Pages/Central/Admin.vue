<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
  tenants:  { type: Array,  default: () => [] },
  campuses: { type: Array,  default: () => [] },
  modules:  { type: Object, default: () => ({}) },
  domain:   { type: String, default: '' },
})

const page = usePage()
const flash = computed(() => page.props.flash || {})

// ── New tenant form ───────────────────────────────────────────────────────────
const showCreate = ref(false)
const createForm = useForm({
  slug: '',
  name: '',
  campus_code: '',
  modules: Object.fromEntries(Object.keys(props.modules).map((m) => [m, true])),
})

const unprovisionedPresets = computed(() =>
  props.campuses.filter((c) => !props.tenants.some((t) => t.id === c.slug)),
)

function applyPreset(preset) {
  createForm.slug = preset.slug
  createForm.name = preset.name
  createForm.campus_code = preset.code
}

function submitCreate() {
  createForm.post(route('central.tenants.store'), {
    preserveScroll: true,
    onSuccess: () => { showCreate.value = false; createForm.reset() },
  })
}

function provisionAllPresets() {
  if (!confirm(`Provision all ${unprovisionedPresets.value.length} missing preset campuses? Each schema will be created and migrated.`)) return
  router.post(route('central.tenants.presets'), {}, { preserveScroll: true })
}

// ── Module toggles ────────────────────────────────────────────────────────────
const savingModules = ref(null)

function isEnabled(tenant, moduleKey) {
  const mods = tenant.modules || {}
  return !(moduleKey in mods) || !!mods[moduleKey]
}

function toggleModule(tenant, moduleKey) {
  const next = { ...Object.fromEntries(Object.keys(props.modules).map((m) => [m, isEnabled(tenant, m)])) }
  next[moduleKey] = !isEnabled(tenant, moduleKey)
  savingModules.value = tenant.id
  router.put(route('central.tenants.modules', tenant.id), { modules: next }, {
    preserveScroll: true,
    onFinish: () => (savingModules.value = null),
  })
}

// ── Campus admin seeding ──────────────────────────────────────────────────────
const seedTarget = ref(null)
const seedForm = useForm({ name: '', email: '', password: '' })

function submitSeed() {
  seedForm.post(route('central.tenants.seed-admin', seedTarget.value.id), {
    preserveScroll: true,
    onSuccess: () => { seedTarget.value = null; seedForm.reset() },
  })
}

function migrateTenant(tenant) {
  router.post(route('central.tenants.migrate', tenant.id), {}, { preserveScroll: true })
}

function deleteTenant(tenant) {
  const phrase = prompt(`This permanently DROPS schema ${tenant.schema} and all of ${tenant.name}'s data.\nType the campus slug (${tenant.id}) to confirm:`)
  if (phrase !== tenant.id) return
  router.delete(route('central.tenants.destroy', tenant.id) + '?confirmed=1', { preserveScroll: true })
}

function logout() {
  router.post(route('central.logout'))
}
</script>

<template>
  <Head title="SRMIS — System Administration" />

  <div class="min-h-screen bg-slate-100">
    <!-- Top bar -->
    <header class="bg-gradient-to-r from-indigo-950 to-blue-800 text-white">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <div class="flex items-center gap-3">
          <img src="/images/pshslogo.png" alt="PSHS Logo" class="h-9 w-9 object-contain" />
          <div>
            <h1 class="text-lg font-bold leading-tight">SRMIS System Administration</h1>
            <p class="text-xs text-indigo-200">{{ domain }}</p>
          </div>
        </div>
        <button @click="logout" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-medium hover:bg-white/20">Sign out</button>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-8 space-y-6">
      <div v-if="flash.success" class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
        {{ flash.success }}
      </div>
      <div v-if="flash.error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
        {{ flash.error }}
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-slate-700">Campus Tenants ({{ tenants.length }})</h2>
        <div class="flex gap-2">
          <button
            v-if="unprovisionedPresets.length"
            @click="provisionAllPresets"
            class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
          >Provision all presets ({{ unprovisionedPresets.length }} missing)</button>
          <button
            @click="showCreate = !showCreate"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
          >+ New campus</button>
        </div>
      </div>

      <!-- Create form -->
      <div v-if="showCreate" class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Provision a campus tenant</h3>

        <div v-if="unprovisionedPresets.length" class="mb-4">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Presets</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="c in unprovisionedPresets"
              :key="c.slug"
              @click="applyPreset(c)"
              class="rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-600 hover:border-indigo-300 hover:text-indigo-700"
            >{{ c.code }}</button>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Campus slug (schema + email prefix)</label>
            <input v-model="createForm.slug" type="text" placeholder="crc"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            <p v-if="createForm.errors.slug" class="mt-1 text-xs text-red-600">{{ createForm.errors.slug }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Campus name</label>
            <input v-model="createForm.name" type="text" placeholder="Caraga Region Campus"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Campus code</label>
            <input v-model="createForm.campus_code" type="text" placeholder="PSHS-CRC"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1">
          <label v-for="(label, key) in modules" :key="key" class="flex items-center gap-1.5 text-xs text-slate-600" :title="label">
            <input v-model="createForm.modules[key]" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5" />
            {{ key }}
          </label>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button @click="showCreate = false" class="px-4 py-2 rounded-lg text-sm text-slate-500 hover:bg-slate-100">Cancel</button>
          <button @click="submitCreate" :disabled="createForm.processing"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
            {{ createForm.processing ? 'Provisioning…' : 'Provision campus' }}
          </button>
        </div>
      </div>

      <!-- Tenant cards -->
      <div class="space-y-4">
        <div v-for="t in tenants" :key="t.id" class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="text-sm font-bold text-slate-800">{{ t.name }} <span class="ml-1 text-xs font-medium text-slate-400">{{ t.campus_code }}</span></h3>
              <p class="mt-0.5 text-xs text-slate-400 font-mono">
                @{{ t.email_domain }} · schema {{ t.schema }} · since {{ t.created_at }}
              </p>
            </div>
            <div class="flex gap-2">
              <button @click="seedTarget = t" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:border-indigo-300 hover:text-indigo-700">Seed RBAC / admin</button>
              <button @click="migrateTenant(t)" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:border-indigo-300 hover:text-indigo-700">Migrate</button>
              <button @click="deleteTenant(t)" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50">Delete</button>
            </div>
          </div>

          <!-- Module toggles -->
          <div class="mt-3 flex flex-wrap gap-2">
            <button
              v-for="(label, key) in modules"
              :key="key"
              :title="label"
              :disabled="savingModules === t.id"
              @click="toggleModule(t, key)"
              :class="[
                'rounded-full px-3 py-1 text-xs font-medium border transition-colors',
                isEnabled(t, key)
                  ? 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100'
                  : 'bg-slate-50 border-slate-200 text-slate-400 line-through hover:bg-slate-100',
              ]"
            >{{ key }}</button>
          </div>
        </div>

        <p v-if="!tenants.length" class="rounded-2xl bg-white p-8 text-center text-sm text-slate-400 border border-dashed border-slate-200">
          No campus tenants yet — provision the presets or add one manually.
        </p>
      </div>
    </main>

    <!-- Seed admin modal -->
    <div v-if="seedTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" @click.self="seedTarget = null">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h3 class="text-sm font-bold text-slate-800">Seed RBAC — {{ seedTarget.name }}</h3>
        <p class="mt-1 text-xs text-slate-500">
          Re-runs the roles/permissions seeder for this campus. Optionally create (or reset)
          the campus Administrator account.
        </p>
        <div class="mt-4 space-y-3">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Admin name (optional)</label>
            <input v-model="seedForm.name" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Admin email (optional)</label>
            <input v-model="seedForm.email" type="email" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            <p v-if="seedForm.errors.email" class="mt-1 text-xs text-red-600">{{ seedForm.errors.email }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Admin password (min 12 chars)</label>
            <input v-model="seedForm.password" type="password" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            <p v-if="seedForm.errors.password" class="mt-1 text-xs text-red-600">{{ seedForm.errors.password }}</p>
          </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="seedTarget = null" class="px-4 py-2 rounded-lg text-sm text-slate-500 hover:bg-slate-100">Cancel</button>
          <button @click="submitSeed" :disabled="seedForm.processing"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
            {{ seedForm.processing ? 'Seeding…' : 'Run seeder' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
