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

  <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-primary-900 via-primary-800 to-primary-600 px-4">

    <!-- dot-grid texture -->
    <div class="pointer-events-none absolute inset-0 opacity-[0.06]"
         style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 22px 22px;"></div>

    <!-- soft glow -->
    <div class="pointer-events-none absolute -top-24 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-sky-400/20 blur-3xl"></div>

    <div class="animate-fade-in-up relative w-full max-w-md">
      <div class="text-center mb-8">
        <img src="/images/pshslogo.png" alt="PSHS Logo" class="mx-auto h-16 w-16 object-contain" />
        <h1 class="mt-4 text-2xl font-bold text-white">STRIDE</h1>
        <p class="text-sm text-primary-100">Service Ticketing and Request Information &amp; Dispatch Engine</p>
        <p class="mt-1 text-xs text-primary-200/70 uppercase tracking-widest">System Administration</p>
      </div>

      <form @submit.prevent="submit" class="rounded-2xl bg-white p-8 shadow-2xl ring-1 ring-black/5 space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
          <input
            v-model="form.email"
            type="email"
            required
            autofocus
            autocomplete="username"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm transition focus:border-primary-300 focus:outline-none focus:ring-2 focus:ring-primary-500 w-full"
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
            class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm transition focus:border-primary-300 focus:outline-none focus:ring-2 focus:ring-primary-500 w-full"
          />
          <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
          Remember me
        </label>

        <button
          type="submit"
          :disabled="form.processing"
          class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium w-full shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg disabled:pointer-events-none disabled:opacity-60"
        >
          {{ form.processing ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-primary-200/70">
        Campus users sign in at the main login page — this page is for the system superadmin only.
      </p>
    </div>
  </div>
</template>
