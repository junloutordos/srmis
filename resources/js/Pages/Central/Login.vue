<script setup>
import { Head, useForm } from '@inertiajs/vue3'

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

function submit() {
  form.post(route('central.login.store'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <Head title="System Superadmin Login" />

  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-950 via-indigo-900 to-blue-800 px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <img src="/images/pshslogo.png" alt="PSHS Logo" class="mx-auto h-16 w-16 object-contain" />
        <h1 class="mt-4 text-2xl font-bold text-white">SRMIS</h1>
        <p class="text-sm text-indigo-200">Service Requests Management Information System</p>
        <p class="mt-1 text-xs text-indigo-300/70 uppercase tracking-widest">System Administration</p>
      </div>

      <form @submit.prevent="submit" class="rounded-2xl bg-white p-8 shadow-xl space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
          <input
            v-model="form.email"
            type="email"
            required
            autofocus
            autocomplete="username"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
          />
          <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
          <input
            v-model="form.password"
            type="password"
            required
            autocomplete="current-password"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
          />
          <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
          Remember me
        </label>

        <button
          type="submit"
          :disabled="form.processing"
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium w-full disabled:opacity-60"
        >
          {{ form.processing ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-indigo-300/60">
        Campus users sign in at the main login page — this page is for the system superadmin only.
      </p>
    </div>
  </div>
</template>
