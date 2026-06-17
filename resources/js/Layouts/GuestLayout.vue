<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-slate-100 pt-8 sm:justify-center sm:pt-0">

        <Link href="/" class="flex flex-col items-center gap-2 mb-6">
            <img src="/pshslogo.png" alt="PSHS Logo" class="h-16 w-16 object-contain drop-shadow-sm" />
            <span class="text-sm font-semibold text-slate-700 tracking-wide">SRMIS</span>
            <span class="text-xs text-slate-400">Philippine Science High School System</span>
        </Link>

        <div class="w-full overflow-hidden bg-white px-6 py-6 shadow-md sm:max-w-md sm:rounded-xl border border-slate-100">
            <slot />
        </div>

        <p class="mt-6 text-xs text-slate-400">Service Requests Management Information System</p>

    </div>
</template>
