<script setup>
import { Head, useForm, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ categories: Array });
const form = useForm({ id: null, name: '' });
const showModal = ref(false);

const searchQuery = ref('')

const filteredCategories = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const list = props.categories || []
  if (!q) return list
  return list.filter(c => c.name?.toLowerCase().includes(q))
})

const openModal = (category = null) => {
  form.reset();
  form.clearErrors();
  if (category) {
    form.id = category.id;
    form.name = category.name;
  }
  showModal.value = true;
};

const closeModal = () => { showModal.value = false; form.reset(); };

const submit = () => {
  if (form.id) {
    form.put(route('admin.it-job-categories.update', form.id), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Category updated', timer: 1200, showConfirmButton: false }) },
    });
  } else {
    form.post(route('admin.it-job-categories.store'), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Category added', timer: 1200, showConfirmButton: false }) },
    });
  }
};

const remove = (category) => {
  Swal.fire({
    title: `Delete "${category.name}"?`,
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  }).then((res) => {
    if (!res.isConfirmed) return
    router.delete(route('admin.it-job-categories.destroy', category.id), {
      onSuccess: (page) => {
        const flash = page.props.flash ?? {}
        if (flash.error) {
          Swal.fire({ icon: 'error', title: 'Cannot delete', text: flash.error })
        } else {
          Swal.fire({ icon: 'success', title: 'Category deleted', timer: 1200, showConfirmButton: false })
        }
      },
    })
  })
};
</script>

<template>
  <Head title="IT Job Categories" />
  <AdminLayout title="IT Job Categories">
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">IT Job Request Categories</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage the categories available on the IT Job Request form.</p>
        </div>
        <button @click.prevent="openModal()"
          class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Category
        </button>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search categories..."
            class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400" />
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requests Using It</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="c in filteredCategories" :key="c.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700 font-medium max-w-[20rem] truncate" :title="c.name">{{ c.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-500">{{ c.requests_count ?? 0 }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <button @click.prevent="openModal(c)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click.prevent="remove(c)"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredCategories.length === 0">
                <td colspan="3" class="py-16 text-center text-slate-400 text-sm">No categories found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ form.id ? 'Edit Category' : 'New Category' }}</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Category Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-400" />
              <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
              <button type="button" @click.prevent="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Cancel
              </button>
              <button :disabled="form.processing" type="submit"
                class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                {{ form.processing ? 'Saving...' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
