<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  PlusIcon, PencilSquareIcon, TrashIcon,
  ShieldCheckIcon, MagnifyingGlassIcon,
  XMarkIcon, KeyIcon, UsersIcon,
  InformationCircleIcon, UserCircleIcon, CheckIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  initialTab: { type: String, default: 'roles' }, // 'roles' | 'permissions' | 'assign'
})

const activeTab = ref(props.initialTab)
const tabs = [
  { key: 'roles',       label: 'Roles' },
  { key: 'permissions', label: 'Permissions' },
  { key: 'assign',      label: 'Assign to Users' },
]

function switchTab(key) {
  activeTab.value = key
}

/* ══════════════════════════════════════════════════════════════════════════
 * TAB 1 — Roles (+ per-role permission assignment)
 * ════════════════════════════════════════════════════════════════════════ */
const rolesList     = ref([])
const roleAllPerms  = ref([])
const rolesLoading  = ref(true)
const rolesSearch   = ref('')

const showRoleModal  = ref(false)
const roleModalMode  = ref('create')
const roleForm       = ref({ id: null, name: '', description: '' })
const roleSaving     = ref(false)

const showPermModal  = ref(false)
const editingRole     = ref(null)
const rolePerms       = ref([])
const rolePermSearch  = ref('')
const syncingPerms    = ref(false)

const rolesFiltered = computed(() =>
  rolesList.value.filter(r => r.name.toLowerCase().includes(rolesSearch.value.toLowerCase()))
)

const roleTotalPerms = computed(() => roleAllPerms.value.reduce((n, g) => n + g.permissions.length, 0))

const roleFilteredPerms = computed(() => {
  if (!rolePermSearch.value) return roleAllPerms.value
  const q = rolePermSearch.value.toLowerCase()
  return roleAllPerms.value
    .map(g => ({
      ...g,
      permissions: g.permissions.filter(p =>
        p.name.toLowerCase().includes(q) || p.description?.toLowerCase().includes(q)
      ),
    }))
    .filter(g => g.permissions.length > 0)
})

async function loadRolesTab() {
  rolesLoading.value = true
  const [rolesRes, permsRes] = await Promise.all([
    axios.get('/admin/rbac/roles'),
    axios.get('/admin/rbac/permissions-all'),
  ])
  rolesList.value    = rolesRes.data
  roleAllPerms.value = permsRes.data
  rolesLoading.value = false
}

function openCreateRole() {
  roleForm.value      = { id: null, name: '', description: '' }
  roleModalMode.value = 'create'
  showRoleModal.value = true
}

function openEditRole(role) {
  roleForm.value      = { id: role.id, name: role.name, description: role.description ?? '' }
  roleModalMode.value = 'edit'
  showRoleModal.value = true
}

async function submitRole() {
  roleSaving.value = true
  try {
    if (roleModalMode.value === 'create') {
      const res = await axios.post('/admin/rbac/roles', roleForm.value)
      rolesList.value.push({ ...res.data, permissions_count: 0, users_count: 0 })
    } else {
      await axios.put(`/admin/rbac/roles/${roleForm.value.id}`, roleForm.value)
      const idx = rolesList.value.findIndex(r => r.id === roleForm.value.id)
      if (idx !== -1) Object.assign(rolesList.value[idx], roleForm.value)
    }
    showRoleModal.value = false
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message ?? 'Something went wrong.', 'error')
  } finally {
    roleSaving.value = false
  }
}

async function deleteRole(role) {
  const result = await Swal.fire({
    title: `Delete "${role.name}"?`,
    text: 'This will detach all users and permissions from this role.',
    icon: 'warning', showCancelButton: true,
    confirmButtonText: 'Delete', confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  try {
    await axios.delete(`/admin/rbac/roles/${role.id}`)
    rolesList.value = rolesList.value.filter(r => r.id !== role.id)
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message ?? 'Cannot delete this role.', 'error')
  }
}

async function openPermissions(role) {
  editingRole.value    = role
  rolePermSearch.value = ''
  const res = await axios.get(`/admin/rbac/roles/${role.id}`)
  rolePerms.value      = res.data.permissions.map(p => p.id)
  showPermModal.value  = true
}

function togglePerm(id) {
  const idx = rolePerms.value.indexOf(id)
  idx === -1 ? rolePerms.value.push(id) : rolePerms.value.splice(idx, 1)
}

function toggleModule(group) {
  const ids   = group.permissions.map(p => p.id)
  const allOn = ids.every(id => rolePerms.value.includes(id))
  if (allOn) {
    rolePerms.value = rolePerms.value.filter(id => !ids.includes(id))
  } else {
    ids.forEach(id => { if (!rolePerms.value.includes(id)) rolePerms.value.push(id) })
  }
}

function moduleChecked(group)       { return group.permissions.every(p => rolePerms.value.includes(p.id)) }
function moduleIndeterminate(group) {
  const cnt = group.permissions.filter(p => rolePerms.value.includes(p.id)).length
  return cnt > 0 && cnt < group.permissions.length
}

function selectAllPerms() {
  if (rolePerms.value.length === roleTotalPerms.value) {
    rolePerms.value = []
  } else {
    rolePerms.value = roleAllPerms.value.flatMap(g => g.permissions.map(p => p.id))
  }
}

async function savePermissions() {
  syncingPerms.value = true
  try {
    await axios.put(`/admin/rbac/roles/${editingRole.value.id}/permissions`, {
      permission_ids: rolePerms.value,
    })
    const idx = rolesList.value.findIndex(r => r.id === editingRole.value.id)
    if (idx !== -1) rolesList.value[idx].permissions_count = rolePerms.value.length
    showPermModal.value = false
    Swal.fire({ icon: 'success', title: 'Permissions saved', timer: 1500, showConfirmButton: false })
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message ?? 'Could not save permissions.', 'error')
  } finally {
    syncingPerms.value = false
  }
}

/* ══════════════════════════════════════════════════════════════════════════
 * TAB 2 — Permission catalog CRUD
 * ════════════════════════════════════════════════════════════════════════ */
const permGroups     = ref([])   // [{module, permissions:[{id,name,module,description,roles_count}]}]
const permsLoading   = ref(true)
const permsSearch    = ref('')
const showGuide      = ref(false)

const showPermCrudModal = ref(false)
const permModalMode     = ref('create')
const permForm          = ref({ id: null, name: '', module: '', description: '' })
const permSaving        = ref(false)

const permsFiltered = computed(() => {
  if (!permsSearch.value) return permGroups.value
  const q = permsSearch.value.toLowerCase()
  return permGroups.value
    .map(g => ({
      ...g,
      permissions: g.permissions.filter(p =>
        p.name.toLowerCase().includes(q) || p.description?.toLowerCase().includes(q)
      ),
    }))
    .filter(g => g.permissions.length > 0)
})

const permModuleList  = computed(() => [...new Set(permGroups.value.map(g => g.module))].sort())
const permsTotalPerms = computed(() => permGroups.value.reduce((s, g) => s + g.permissions.length, 0))

async function loadPermissionsTab() {
  permsLoading.value = true
  const res = await axios.get('/admin/rbac/permissions')
  permGroups.value  = res.data
  permsLoading.value = false
}

function openCreatePerm() {
  permForm.value      = { id: null, name: '', module: '', description: '' }
  permModalMode.value = 'create'
  showPermCrudModal.value = true
}

function openEditPerm(perm) {
  permForm.value      = { id: perm.id, name: perm.name, module: perm.module, description: perm.description ?? '' }
  permModalMode.value = 'edit'
  showPermCrudModal.value = true
}

async function submitPerm() {
  permSaving.value = true
  try {
    if (permModalMode.value === 'create') {
      await axios.post('/admin/rbac/permissions', permForm.value)
    } else {
      await axios.put(`/admin/rbac/permissions/${permForm.value.id}`, permForm.value)
    }
    showPermCrudModal.value = false
    await loadPermissionsTab()
    // A new/renamed permission can change per-role counts shown in the Roles tab.
    if (loadedTabs.value.roles) await loadRolesTab()
  } catch (e) {
    const errors = e.response?.data?.errors
    const msg    = errors
      ? Object.values(errors).flat().join('\n')
      : e.response?.data?.message ?? 'Something went wrong.'
    Swal.fire('Error', msg, 'error')
  } finally {
    permSaving.value = false
  }
}

async function deletePerm(perm) {
  const result = await Swal.fire({
    title: `Delete "${perm.name}"?`,
    text: `This will detach it from all ${perm.roles_count} role(s).`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  try {
    await axios.delete(`/admin/rbac/permissions/${perm.id}`)
    await loadPermissionsTab()
  } catch (e) {
    Swal.fire('Error', 'Could not delete permission.', 'error')
  }
}

/* ══════════════════════════════════════════════════════════════════════════
 * TAB 3 — Assign roles to users
 * ════════════════════════════════════════════════════════════════════════ */
const assignUsers      = ref([])
const assignPagination = ref(null)
const assignAllRoles   = ref([])   // [{id,name,description,permissions:[{id,name,description}]}]
const assignLoading    = ref(true)
const assignSearch     = ref('')
const filterRole       = ref('')
const searchTimer      = ref(null)

const selected    = ref(null)
const userRoles   = ref([])   // current role ids for the selected user
const assignSyncing = ref(false)

const hoveredRole = ref(null)

const previewRole = computed(() => {
  if (hoveredRole.value) return assignAllRoles.value.find(r => r.id === hoveredRole.value)
  return null
})

async function loadUsers(page = 1) {
  assignLoading.value = true
  const params = { page, per_page: 20 }
  if (assignSearch.value) params.search = assignSearch.value
  if (filterRole.value)   params.role   = filterRole.value
  const res = await axios.get('/admin/rbac/users', { params })
  assignUsers.value      = res.data.data
  assignPagination.value = res.data
  assignLoading.value    = false
}

async function loadAssignRolesList() {
  const res = await axios.get('/admin/rbac/roles-list')
  assignAllRoles.value = res.data
}

function onSearch() {
  clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(() => loadUsers(1), 350)
}

function onFilterChange() {
  loadUsers(1)
}

async function selectUser(user) {
  selected.value    = user
  userRoles.value   = user.roles.map(r => r.id)
  hoveredRole.value = null
}

function toggleRole(id) {
  const idx = userRoles.value.indexOf(id)
  if (idx === -1) userRoles.value.push(id)
  else userRoles.value.splice(idx, 1)
}

async function saveRoles() {
  assignSyncing.value = true
  try {
    const res = await axios.put(
      `/admin/rbac/users/${selected.value.id}/roles`,
      { role_ids: userRoles.value }
    )
    const idx = assignUsers.value.findIndex(u => u.id === selected.value.id)
    if (idx !== -1) assignUsers.value[idx].roles = res.data.roles
    selected.value.roles = res.data.roles
    Swal.fire({ icon: 'success', title: 'Roles updated', timer: 1200, showConfirmButton: false })
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message ?? 'Could not update roles.', 'error')
  } finally {
    assignSyncing.value = false
  }
}

function roleNames(user) {
  return user.roles?.map(r => r.name).join(', ') || '—'
}

function statusClass(status) {
  return status === 'active'
    ? 'bg-emerald-50 text-emerald-700'
    : 'bg-slate-100 text-slate-600'
}

function groupedPerms(permissions) {
  if (!permissions?.length) return []
  const map = {}
  for (const p of permissions) {
    const mod = p.module ?? p.name.split('.').slice(0, -1).join('.') ?? 'Other'
    if (!map[mod]) map[mod] = []
    map[mod].push(p)
  }
  return Object.entries(map).sort((a, b) => a[0].localeCompare(b[0]))
}

/* ══════════════════════════════════════════════════════════════════════════
 * Tab lazy-loading — declared last so it runs only after every loader above
 * is initialized (an immediate watcher fires synchronously during setup).
 * ════════════════════════════════════════════════════════════════════════ */
const loadedTabs = ref({ roles: false, permissions: false, assign: false })

watch(activeTab, (key) => {
  if (loadedTabs.value[key]) return
  loadedTabs.value[key] = true
  if (key === 'roles') loadRolesTab()
  if (key === 'permissions') loadPermissionsTab()
  if (key === 'assign') { loadUsers(); loadAssignRolesList() }
}, { immediate: true })
</script>

<template>
  <Head title="Roles & Permissions" />
  <AdminLayout title="Roles & Permissions">

    <!-- Tab strip -->
    <div class="flex gap-1 mb-6 bg-white border border-slate-200 rounded-xl p-1 w-fit shadow-sm">
      <button v-for="t in tabs" :key="t.key" @click="switchTab(t.key)"
        class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors"
        :class="activeTab === t.key ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'">
        {{ t.label }}
      </button>
    </div>

    <!-- ══════════════════════ TAB: Roles ══════════════════════ -->
    <div v-show="activeTab === 'roles'">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Roles</h1>
          <p class="text-sm text-slate-500 mt-0.5">Define roles and assign permissions to each role.</p>
        </div>
        <button @click="openCreateRole"
          class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> New Role
        </button>
      </div>

      <div v-if="rolesLoading" class="py-20 text-center text-slate-400 text-sm">Loading…</div>
      <template v-else>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 mb-4 flex items-center gap-2">
          <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 shrink-0" />
          <input v-model="rolesSearch" type="text" placeholder="Search roles…"
            class="flex-1 border-none outline-none text-sm text-slate-800 placeholder-slate-400 bg-transparent" />
          <span class="text-xs text-slate-400">{{ rolesFiltered.length }} role(s)</span>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="role in rolesFiltered" :key="role.id"
            class="bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col group hover:border-primary-300 transition-colors">
            <div class="px-4 pt-4 pb-3 border-b border-slate-100">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <div class="flex items-center gap-2">
                    <ShieldCheckIcon class="w-4 h-4 text-primary-500 shrink-0" />
                    <span class="font-semibold text-slate-800">{{ role.name }}</span>
                    <span v-if="role.name === 'Administrator'"
                      class="text-[10px] font-semibold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">Super</span>
                  </div>
                  <p class="text-xs text-slate-400 mt-0.5 ml-6">{{ role.description || 'No description' }}</p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                  <button @click="openEditRole(role)" title="Edit role"
                    class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors">
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button @click="deleteRole(role)" title="Delete role"
                    :disabled="role.name === 'Administrator'"
                    class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
            <div class="px-4 py-3 flex items-center justify-between">
              <div class="flex items-center gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1">
                  <KeyIcon class="w-3.5 h-3.5" />
                  {{ role.permissions_count }} permission{{ role.permissions_count !== 1 ? 's' : '' }}
                </span>
                <span class="flex items-center gap-1">
                  <UsersIcon class="w-3.5 h-3.5" />
                  {{ role.users_count }} user{{ role.users_count !== 1 ? 's' : '' }}
                </span>
              </div>
              <button @click="openPermissions(role)"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 hover:text-primary-800 transition-colors">
                <ShieldCheckIcon class="w-3.5 h-3.5" />
                Edit Permissions
              </button>
            </div>
          </div>

          <div v-if="rolesFiltered.length === 0" class="col-span-3 py-16 text-center text-slate-400 text-sm">
            No roles found.
          </div>
        </div>
      </template>

      <!-- Role create/edit modal -->
      <Teleport to="body">
        <div v-if="showRoleModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 class="text-base font-semibold text-slate-800">{{ roleModalMode === 'create' ? 'New Role' : 'Edit Role' }}</h2>
              <button @click="showRoleModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700">
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>
            <div class="px-6 py-5">
              <form @submit.prevent="submitRole" class="space-y-4">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Role Name <span class="text-red-500">*</span></label>
                  <input v-model="roleForm.name" type="text" required maxlength="100" placeholder="e.g. Librarian"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                  <input v-model="roleForm.description" type="text" maxlength="255" placeholder="Brief description of this role"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                  <button type="button" @click="showRoleModal = false"
                    class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                  <button type="submit" :disabled="roleSaving"
                    class="px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium disabled:opacity-60">
                    {{ roleSaving ? 'Saving…' : (roleModalMode === 'create' ? 'Create Role' : 'Save Changes') }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Permission assignment modal -->
      <Teleport to="body">
        <div v-if="showPermModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
              <div>
                <h2 class="text-base font-semibold text-slate-800">
                  Permissions — <span class="text-primary-600">{{ editingRole?.name }}</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">{{ rolePerms.length }} of {{ roleTotalPerms }} permissions selected</p>
              </div>
              <div class="flex items-center gap-2">
                <div class="relative">
                  <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                  <input v-model="rolePermSearch" type="text" placeholder="Filter…"
                    class="pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary-400 w-40" />
                </div>
                <button @click="showPermModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700">
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">
              <div v-if="editingRole?.name === 'Administrator'"
                class="flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-4 py-3 text-sm">
                <ShieldCheckIcon class="w-4 h-4 shrink-0" />
                Administrator bypasses all permission checks and always has full access — its permission list is locked and cannot be edited here.
              </div>

              <div v-for="group in roleFilteredPerms" :key="group.module">
                <label class="flex items-center gap-2 mb-2.5 cursor-pointer group">
                  <input type="checkbox"
                    :checked="moduleChecked(group)"
                    :indeterminate.prop="moduleIndeterminate(group)"
                    :disabled="editingRole?.name === 'Administrator'"
                    @change="toggleModule(group)"
                    class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 w-4 h-4 disabled:opacity-50" />
                  <span class="text-xs font-bold uppercase tracking-widest text-slate-700">{{ group.module }}</span>
                  <span class="text-xs text-slate-400 font-normal ml-1">
                    ({{ group.permissions.filter(p => rolePerms.includes(p.id)).length }}/{{ group.permissions.length }})
                  </span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 pl-6">
                  <label v-for="perm in group.permissions" :key="perm.id"
                    class="flex items-start gap-2 cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors"
                    :class="{ 'bg-primary-50/60 border border-primary-100': rolePerms.includes(perm.id) }">
                    <input type="checkbox"
                      :checked="rolePerms.includes(perm.id)"
                      :disabled="editingRole?.name === 'Administrator'"
                      @change="togglePerm(perm.id)"
                      class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 w-4 h-4 mt-0.5 shrink-0 disabled:opacity-50" />
                    <div class="min-w-0">
                      <div class="text-xs text-slate-700 font-medium leading-tight">{{ perm.description || perm.name }}</div>
                      <div class="text-[10px] font-mono text-slate-400 mt-0.5">{{ perm.name }}</div>
                    </div>
                  </label>
                </div>
              </div>

              <p v-if="roleFilteredPerms.length === 0" class="text-center text-slate-400 text-sm py-8">No permissions match your filter.</p>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between shrink-0">
              <button v-if="editingRole?.name !== 'Administrator'" @click="selectAllPerms" class="text-xs text-slate-500 hover:text-primary-600 transition-colors">
                {{ rolePerms.length === roleTotalPerms ? 'Deselect all' : 'Select all' }}
              </button>
              <span v-else></span>
              <div class="flex gap-2">
                <button @click="showPermModal = false"
                  class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                <button v-if="editingRole?.name !== 'Administrator'" @click="savePermissions" :disabled="syncingPerms"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium disabled:opacity-60">
                  <span v-if="syncingPerms" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                  {{ syncingPerms ? 'Saving…' : 'Save Permissions' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>

    <!-- ══════════════════════ TAB: Permissions ══════════════════════ -->
    <div v-show="activeTab === 'permissions'">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Permissions</h1>
          <p class="text-sm text-slate-500 mt-0.5">
            {{ permsTotalPerms }} permission(s) across {{ permGroups.length }} module(s).
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button @click="showGuide = !showGuide"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <InformationCircleIcon class="w-4 h-4" /> Naming Guide
          </button>
          <button @click="openCreatePerm"
            class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <PlusIcon class="w-4 h-4" /> New Permission
          </button>
        </div>
      </div>

      <div v-if="showGuide" class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-5 text-sm">
        <div class="flex items-start gap-3">
          <KeyIcon class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
          <div class="space-y-3 flex-1">
            <p class="font-semibold text-amber-800">Permission Naming Convention</p>
            <p class="text-amber-700">
              Permissions follow the pattern <code class="bg-amber-100 px-1 rounded font-mono text-xs">module.action</code>
              or <code class="bg-amber-100 px-1 rounded font-mono text-xs">module.submodule.action</code>.
              Always use <strong>lowercase letters, dots, and hyphens only</strong>.
            </p>
            <div class="grid sm:grid-cols-2 gap-3">
              <div class="bg-white rounded-lg border border-amber-200 p-3">
                <p class="text-xs font-semibold text-amber-700 mb-2">Common Actions</p>
                <ul class="space-y-1 text-xs text-amber-700">
                  <li><code class="font-mono bg-amber-50 px-1 rounded">.view</code> — read-only access</li>
                  <li><code class="font-mono bg-amber-50 px-1 rounded">.manage</code> — create / edit / delete</li>
                  <li><code class="font-mono bg-amber-50 px-1 rounded">.approve</code> — workflow approval</li>
                  <li><code class="font-mono bg-amber-50 px-1 rounded">.monitor</code> — oversight / reports</li>
                  <li><code class="font-mono bg-amber-50 px-1 rounded">.export</code> — export data</li>
                </ul>
              </div>
              <div class="bg-white rounded-lg border border-amber-200 p-3">
                <p class="text-xs font-semibold text-amber-700 mb-2">Examples</p>
                <ul class="space-y-1 text-xs font-mono text-amber-700">
                  <li>hr.dtr.view</li>
                  <li>hr.dtr.manage</li>
                  <li>ipcr.view</li>
                  <li>ipcr.approve</li>
                  <li>leave.manage</li>
                  <li>users.view</li>
                </ul>
              </div>
            </div>
            <p class="text-xs text-amber-600">
              <strong>Adding a new module:</strong> Just create a permission with a new module name (e.g. <code class="font-mono bg-amber-100 px-1 rounded">payroll.view</code>) and set the <strong>Module</strong> field to <code class="font-mono bg-amber-100 px-1 rounded">payroll</code>.
              It will automatically appear as a new group. Then protect the route with <code class="font-mono bg-amber-100 px-1 rounded">middleware('permission:payroll.view')</code> in <code class="font-mono bg-amber-100 px-1 rounded">routes/web.php</code>.
              New permissions are automatically granted to the Administrator role.
            </p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 mb-4 flex items-center gap-2">
        <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 shrink-0" />
        <input v-model="permsSearch" type="text" placeholder="Search by name or description…"
          class="flex-1 border-none outline-none text-sm text-slate-800 placeholder-slate-400 bg-transparent" />
        <span v-if="permsSearch" class="text-xs text-slate-400">{{ permsFiltered.reduce((s,g) => s + g.permissions.length, 0) }} result(s)</span>
      </div>

      <div v-if="permsLoading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
      <div v-else class="space-y-4">
        <div v-for="group in permsFiltered" :key="group.module" class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <KeyIcon class="w-4 h-4 text-primary-400" />
              <h2 class="text-xs font-semibold uppercase tracking-wider text-primary-600">{{ group.module }}</h2>
            </div>
            <span class="text-[11px] text-slate-400">{{ group.permissions.length }} permission(s)</span>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Key</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
                  <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Roles</th>
                  <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="perm in group.permissions" :key="perm.id" class="hover:bg-slate-50/60">
                  <td class="px-4 py-3">
                    <code class="font-mono text-primary-700 text-xs bg-primary-50 px-2 py-0.5 rounded">{{ perm.name }}</code>
                  </td>
                  <td class="px-4 py-3 text-slate-500 text-sm">{{ perm.description ?? '—' }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">{{ perm.roles_count }}</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                      <button @click="openEditPerm(perm)"
                        class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                        <PencilSquareIcon class="w-4 h-4" />
                      </button>
                      <button @click="deletePerm(perm)"
                        class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <p v-if="permsFiltered.length === 0" class="py-16 text-center text-slate-400 text-sm">No permissions found.</p>
      </div>

      <!-- Create / Edit Permission Modal -->
      <div v-if="showPermCrudModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ permModalMode === 'create' ? 'New Permission' : 'Edit Permission' }}</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="showPermCrudModal = false"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="px-6 py-5">
            <form @submit.prevent="submitPerm" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                  Permission Key
                  <span class="text-slate-400 font-normal ml-1">(lowercase · dots · hyphens)</span>
                </label>
                <input v-model="permForm.name" type="text" required maxlength="100"
                  pattern="[a-z0-9._\-]+"
                  placeholder="e.g. hr.dtr.view or payroll.manage"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 font-mono placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400 w-full" />
                <p class="text-[11px] text-slate-400 mt-1">This key is used in routes: <code class="font-mono">middleware('permission:{{ permForm.name || "…" }}')</code></p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Module <span class="text-slate-400 font-normal">(groups this permission)</span></label>
                <input v-model="permForm.module" list="module-list" type="text" required maxlength="50"
                  placeholder="e.g. hr · ipcr · payroll"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400 w-full" />
                <datalist id="module-list">
                  <option v-for="m in permModuleList" :key="m" :value="m" />
                </datalist>
                <p class="text-[11px] text-slate-400 mt-1">Enter a new module name to create a new group.</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description <span class="text-slate-400 font-normal">(shown to admins)</span></label>
                <input v-model="permForm.description" type="text" maxlength="255"
                  placeholder="e.g. View HR Daily Time Records"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400 w-full" />
              </div>
              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="showPermCrudModal = false"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
                <button type="submit" :disabled="permSaving"
                  class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
                  {{ permSaving ? 'Saving…' : 'Save' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════ TAB: Assign to Users ══════════════════════ -->
    <div v-show="activeTab === 'assign'">
      <div class="mb-5">
        <h1 class="text-xl font-semibold text-slate-800">Assign Roles to Users</h1>
        <p class="text-sm text-slate-500 mt-0.5">Click a user to open the role assignment panel. Hover a role to preview its permissions.</p>
      </div>

      <div class="flex gap-4 min-h-0">
        <div class="flex-1 min-w-0">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 mb-4 flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 flex-1 min-w-[180px]">
              <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 shrink-0" />
              <input v-model="assignSearch" @input="onSearch" type="text" placeholder="Search name, email, position…"
                class="flex-1 border-none outline-none text-sm text-slate-800 placeholder-slate-400 bg-transparent" />
            </div>
            <select v-model="filterRole" @change="onFilterChange"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400">
              <option value="">All roles</option>
              <option v-for="r in assignAllRoles" :key="r.id" :value="r.name">{{ r.name }}</option>
            </select>
            <span class="text-xs text-slate-400 shrink-0">{{ assignPagination?.total ?? 0 }} user(s)</span>
          </div>

          <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            <div v-if="assignLoading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap hidden sm:table-cell">Position</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Roles</th>
                    <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="user in assignUsers" :key="user.id"
                    @click="selectUser(user)"
                    class="hover:bg-primary-50/40 cursor-pointer transition-colors"
                    :class="{ 'bg-primary-50 ring-1 ring-inset ring-primary-200': selected?.id === user.id }">
                    <td class="px-4 py-3">
                      <div class="font-medium text-slate-800">{{ user.name }}</div>
                      <div class="text-xs text-slate-400">{{ user.email }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-500 hidden sm:table-cell">{{ user.position ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600 max-w-[200px]">
                      <div class="flex flex-wrap gap-1">
                        <span v-for="r in (user.roles ?? [])" :key="r.id"
                          class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-primary-50 text-primary-700">
                          {{ r.name }}
                        </span>
                        <span v-if="!(user.roles?.length)" class="text-slate-400 text-xs">—</span>
                      </div>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium" :class="statusClass(user.status)">
                        {{ user.status ?? 'active' }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="assignUsers.length === 0">
                    <td colspan="4" class="py-16 text-center text-slate-400 text-sm">No users found.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="assignPagination && assignPagination.last_page > 1"
              class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
              <button @click="loadUsers(assignPagination.current_page - 1)"
                :disabled="assignPagination.current_page <= 1"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">← Prev</button>
              <span>Page {{ assignPagination.current_page }} / {{ assignPagination.last_page }}</span>
              <button @click="loadUsers(assignPagination.current_page + 1)"
                :disabled="assignPagination.current_page >= assignPagination.last_page"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next →</button>
            </div>
          </div>
        </div>

        <div class="w-72 shrink-0 self-start sticky top-4 relative">
          <div v-if="!selected" class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 text-center text-slate-400 flex flex-col items-center gap-3">
            <UserCircleIcon class="w-12 h-12 text-slate-200" />
            <p class="text-sm">Select a user to assign roles</p>
          </div>

          <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <div class="pb-3 mb-3 border-b border-slate-100">
              <p class="font-semibold text-slate-800 truncate">{{ selected.name }}</p>
              <p class="text-xs text-slate-400 truncate">{{ selected.email }}</p>
              <p class="text-xs text-slate-500 mt-0.5">{{ selected.position ?? '—' }}</p>
            </div>

            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Assign Roles</h3>
            <div class="space-y-1 mb-4">
              <label v-for="role in assignAllRoles" :key="role.id"
                class="flex items-center gap-2.5 cursor-pointer group rounded-lg px-2 py-1.5 transition-colors"
                :class="userRoles.includes(role.id) ? 'bg-primary-50' : 'hover:bg-slate-50'"
                @mouseenter="hoveredRole = role.id"
                @mouseleave="hoveredRole = null">
                <input type="checkbox"
                  :checked="userRoles.includes(role.id)"
                  @change="toggleRole(role.id)"
                  class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 shrink-0" />
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5">
                    <ShieldCheckIcon class="w-3.5 h-3.5 text-primary-400 shrink-0" />
                    <span class="text-sm font-medium text-slate-700 truncate group-hover:text-primary-700 transition-colors">{{ role.name }}</span>
                    <span v-if="role.name === 'Administrator'"
                      class="text-[10px] font-semibold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full shrink-0">Super</span>
                  </div>
                  <p v-if="role.description" class="text-[11px] text-slate-400 ml-5 truncate">{{ role.description }}</p>
                </div>
              </label>
            </div>

            <button @click="saveRoles" :disabled="assignSyncing"
              class="w-full inline-flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
              <CheckIcon class="w-4 h-4" />
              {{ assignSyncing ? 'Saving…' : 'Save Roles' }}
            </button>
          </div>

          <Transition enter-active-class="transition-all duration-150" enter-from-class="opacity-0 translate-x-2" enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition-all duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="previewRole"
              class="absolute top-0 right-full mr-3 w-64 bg-white rounded-xl border border-primary-200 shadow-lg p-4 z-10">
              <div class="flex items-center gap-2 mb-3">
                <KeyIcon class="w-4 h-4 text-primary-500" />
                <p class="text-xs font-semibold text-primary-700">{{ previewRole.name }} — Permissions</p>
              </div>
              <div v-if="previewRole.name === 'Administrator'" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                Administrator bypasses all permission checks — has access to everything.
              </div>
              <div v-else-if="!previewRole.permissions?.length" class="text-xs text-slate-400 text-center py-2">
                No permissions assigned to this role.
              </div>
              <div v-else class="space-y-2 max-h-64 overflow-y-auto pr-1">
                <div v-for="[mod, perms] in groupedPerms(previewRole.permissions)" :key="mod">
                  <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">{{ mod }}</p>
                  <div class="flex flex-wrap gap-1">
                    <span v-for="p in perms" :key="p.id"
                      class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono bg-primary-50 text-primary-700 border border-primary-100"
                      :title="p.description">
                      {{ p.name }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
