<script setup>
import { ref, watch, computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import axios from 'axios'
import { auth, provider, signInWithPopup } from '@/firebase'
import {
  ComputerDesktopIcon,
  WrenchScrewdriverIcon,
  UsersIcon,
  InboxIcon,
  ChartBarIcon,
  ChatBubbleLeftRightIcon,
  PencilSquareIcon,
  CheckCircleIcon,
  QuestionMarkCircleIcon,
} from '@heroicons/vue/24/outline'

// Campus accounts use @<campus>.pshs.edu.ph; the OED uses @pshssystem.edu.ph.
// The campus is detected server-side from the email — this is a UX pre-check only.
const OFFICIAL_DOMAINS = ['.pshs.edu.ph', '@pshssystem.edu.ph']
const isLoading = ref(false)

const showAlert = (options) => Swal.fire({ confirmButtonColor: '#1447c0', ...options })
const isAuthorizedEmail = (email) => OFFICIAL_DOMAINS.some((d) => email?.toLowerCase().endsWith(d))

const page = usePage()
const appVersion = computed(() => page.props.appVersion?.current ?? '1.0.0')

watch(() => page.props.errors, (errs) => {
  if (!errs) return
  const emailErr = errs.email || (Array.isArray(errs.email) ? errs.email[0] : null)
  const msg = emailErr || (typeof errs === 'string' ? errs : null)
  if (!msg) return
  if (String(msg).includes('Unable to logged in')) {
    showAlert({ icon: 'error', title: 'Unable to log in', text: 'Contact MIS administrator.' })
  } else {
    showAlert({ icon: 'error', title: 'Login Failed', text: String(msg) })
  }
}, { immediate: true })

const googleLogin = async () => {
  if (isLoading.value) return
  try {
    isLoading.value = true
    const { user } = await signInWithPopup(auth, provider)
    if (!isAuthorizedEmail(user.email)) {
      showAlert({ icon: 'error', title: 'Unauthorized Email', text: 'Only official PSHS campus or OED accounts are allowed.' })
      return
    }
    const { data } = await axios.post('/google/login', { email: user.email, name: user.displayName, uid: user.uid })
    if (!data?.success) throw new Error('Account verification failed')
    showAlert({ icon: 'success', title: 'Login Successful', text: `Welcome, ${user.displayName}! Redirecting…`, timer: 1500, showConfirmButton: false })
    window.location.href = data.redirect_to
  } catch (error) {
    showAlert({ icon: 'error', title: 'Login Failed', text: error.response?.data?.message || error.message })
  } finally {
    isLoading.value = false
  }
}

// ── Email + password fallback (campus admins / non-Google accounts) ──────────
const form = useForm({ email: '', password: '', remember: false })

function submit() {
  form.post(route('login'), { onFinish: () => form.reset('password') })
}

// ── Live campus detection (mirrors App\Tenancy\CampusEmailMapper) ────────────
const CAMPUS_NAMES = {
  oed:   'Office of the Executive Director',
  mc:    'Main Campus',
  brc:   'Bicol Region Campus',
  cbzrc: 'CALABARZON Region Campus',
  carc:  'Cordillera Administrative Region Campus',
  cvc:   'Cagayan Valley Campus',
  clc:   'Central Luzon Campus',
  cmc:   'Central Mindanao Campus',
  cvisc: 'Central Visayas Campus',
  crc:   'Caraga Region Campus',
  evc:   'Eastern Visayas Campus',
  irc:   'Ilocos Region Campus',
  mrc:   'MIMAROPA Region Campus',
  smc:   'Southern Mindanao Campus',
  src:   'SOCCSKSARGEN Region Campus',
  wvc:   'Western Visayas Campus',
  zrc:   'Zamboanga Peninsula Region Campus',
}

const detectedCampus = computed(() => {
  const email = form.email.toLowerCase().trim()
  const at = email.lastIndexOf('@')
  if (at < 1 || at === email.length - 1) return null

  const domain = email.slice(at + 1)
  if (!domain.includes('.')) return null

  if (domain === 'pshssystem.edu.ph') {
    return { known: true, name: CAMPUS_NAMES.oed }
  }
  if (domain.endsWith('.pshs.edu.ph')) {
    const slug = domain.slice(0, -'.pshs.edu.ph'.length)
    if (slug && !slug.includes('.')) {
      return { known: true, name: CAMPUS_NAMES[slug] ?? `${slug.toUpperCase()} Campus` }
    }
  }
  return { known: false }
})

const modules = [
  { icon: ComputerDesktopIcon,    label: 'IT Job Requests' },
  { icon: WrenchScrewdriverIcon,  label: 'General Services' },
  { icon: UsersIcon,              label: 'Data Management' },
  { icon: InboxIcon,              label: 'Approvals' },
  { icon: ChartBarIcon,           label: 'Reports' },
  { icon: ChatBubbleLeftRightIcon, label: 'Chat' },
  { icon: PencilSquareIcon,       label: 'e-Signature' },
]
</script>

<template>
  <Head title="SRMIS — Sign in" />

  <div class="flex min-h-screen bg-white">

    <!-- ── Left panel: illustration ─────────────────────────────────────────── -->
    <div class="relative hidden w-[55%] flex-col justify-between overflow-hidden md:flex"
         style="background: linear-gradient(160deg, #060e50 0%, #0d1f8a 55%, #1447c0 100%);">

      <!-- soft background shapes -->
      <div class="pointer-events-none absolute inset-0 opacity-20">
        <div class="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-cyan-400/30 blur-3xl"></div>
        <div class="absolute bottom-10 right-0 h-80 w-80 rounded-full bg-indigo-400/30 blur-3xl"></div>
      </div>

      <!-- brand -->
      <div class="relative z-10 flex items-center gap-3 px-10 pt-8">
        <img src="/images/pshslogo.png" alt="PSHS" class="h-9 w-9 object-contain" style="filter: drop-shadow(0 0 8px rgba(0,200,232,0.45));" />
        <div>
          <p class="text-sm font-bold tracking-wide text-white">SRMIS</p>
          <p class="text-[11px] text-blue-200/60">Philippine Science High School System</p>
        </div>
      </div>

      <!-- vector illustration: a service request flowing to approval -->
      <div class="relative z-10 flex flex-1 items-center justify-center px-10">
        <svg viewBox="0 0 520 380" fill="none" class="w-full max-w-lg" aria-hidden="true">
          <!-- ground shadow -->
          <ellipse cx="260" cy="352" rx="190" ry="16" fill="#04123f" opacity="0.45" />

          <!-- dashed flow line: form → approval -->
          <path d="M150 210 C 210 130, 320 130, 384 96" stroke="#7dd3fc" stroke-width="2.5"
                stroke-dasharray="2 9" stroke-linecap="round" opacity="0.8" />
          <path d="M168 250 C 250 300, 330 290, 396 240" stroke="#7dd3fc" stroke-width="2.5"
                stroke-dasharray="2 9" stroke-linecap="round" opacity="0.55" />

          <!-- main request form card -->
          <g>
            <rect x="60" y="110" width="190" height="232" rx="18" fill="#ffffff" />
            <rect x="60" y="110" width="190" height="232" rx="18" fill="url(#cardSheen)" opacity="0.5" />
            <!-- clip -->
            <rect x="125" y="96" width="60" height="28" rx="9" fill="#38bdf8" />
            <rect x="138" y="103" width="34" height="14" rx="7" fill="#0d1f8a" />

            <!-- row 1: done -->
            <circle cx="92" cy="166" r="11" fill="#34d399" />
            <path d="M86.5 166 l4 4 l7 -8" stroke="#064e3b" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            <rect x="112" y="156" width="116" height="9" rx="4.5" fill="#c7d2fe" />
            <rect x="112" y="171" width="78" height="7" rx="3.5" fill="#e0e7ff" />

            <!-- row 2: done -->
            <circle cx="92" cy="216" r="11" fill="#34d399" />
            <path d="M86.5 216 l4 4 l7 -8" stroke="#064e3b" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            <rect x="112" y="206" width="116" height="9" rx="4.5" fill="#c7d2fe" />
            <rect x="112" y="221" width="92" height="7" rx="3.5" fill="#e0e7ff" />

            <!-- row 3: pending -->
            <circle cx="92" cy="266" r="11" fill="#fbbf24" />
            <circle cx="92" cy="266" r="4.5" fill="#92400e" />
            <rect x="112" y="256" width="116" height="9" rx="4.5" fill="#c7d2fe" />
            <rect x="112" y="271" width="64" height="7" rx="3.5" fill="#e0e7ff" />

            <!-- submit bar -->
            <rect x="84" y="300" width="142" height="22" rx="11" fill="#1447c0" />
            <rect x="128" y="308" width="54" height="6" rx="3" fill="#bfdbfe" />
          </g>

          <!-- approval seal -->
          <g>
            <circle cx="404" cy="84" r="34" fill="#34d399" />
            <circle cx="404" cy="84" r="34" stroke="#a7f3d0" stroke-width="3" stroke-dasharray="5 7" fill="none" />
            <path d="M388 84 l11 11 l21 -23" stroke="#064e3b" stroke-width="5.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
          </g>

          <!-- floating module chips -->
          <!-- wrench / work -->
          <g>
            <rect x="284" y="156" width="54" height="54" rx="14" fill="#ffffff" />
            <path d="M321 173 a10 10 0 0 0 -13 13 l-9 9 a4.5 4.5 0 0 0 6.4 6.4 l9 -9 a10 10 0 0 0 13 -13 l-6 6 l-6.5 -6.5 z"
                  fill="#1447c0" />
          </g>
          <!-- vehicle -->
          <g>
            <rect x="356" y="262" width="60" height="46" rx="13" fill="#ffffff" />
            <path d="M368 290 v-7 a4 4 0 0 1 4 -4 h4 l5 -7 h14 a4 4 0 0 1 3.4 1.9 L402 279 h2 a4 4 0 0 1 4 4 v7 z" fill="#1447c0" />
            <circle cx="377" cy="292" r="4.4" fill="#0b1c69" />
            <circle cx="399" cy="292" r="4.4" fill="#0b1c69" />
          </g>
          <!-- chat bubble -->
          <g>
            <rect x="292" y="40" width="52" height="48" rx="13" fill="#ffffff" />
            <path d="M304 56 h28 a5 5 0 0 1 5 5 v10 a5 5 0 0 1 -5 5 h-15 l-8 7 v-7 h-5 a5 5 0 0 1 -5 -5 v-10 a5 5 0 0 1 5 -5 z" fill="#38bdf8" />
            <circle cx="311" cy="66" r="2.1" fill="#075985" />
            <circle cx="318.5" cy="66" r="2.1" fill="#075985" />
            <circle cx="326" cy="66" r="2.1" fill="#075985" />
          </g>
          <!-- paper plane / messengerial -->
          <g>
            <rect x="430" y="160" width="52" height="48" rx="13" fill="#ffffff" />
            <path d="M440 184 l34 -13 l-9 30 l-8 -9 l-4 7 l-2 -10 z" fill="#1447c0" />
            <path d="M457 192 l17 -21" stroke="#93c5fd" stroke-width="2" stroke-linecap="round" />
          </g>

          <!-- sparkles -->
          <circle cx="62" cy="70" r="4" fill="#7dd3fc" opacity="0.9" />
          <circle cx="476" cy="46" r="3" fill="#a5b4fc" opacity="0.9" />
          <circle cx="46" cy="318" r="3.4" fill="#a5b4fc" opacity="0.8" />
          <circle cx="496" cy="300" r="4" fill="#7dd3fc" opacity="0.7" />
          <path d="M236 36 l3.4 8 l8 3.4 l-8 3.4 l-3.4 8 l-3.4 -8 l-8 -3.4 l8 -3.4 z" fill="#facc15" opacity="0.95" />

          <defs>
            <linearGradient id="cardSheen" x1="60" y1="110" x2="250" y2="342" gradientUnits="userSpaceOnUse">
              <stop stop-color="#ffffff" />
              <stop offset="1" stop-color="#dbeafe" />
            </linearGradient>
          </defs>
        </svg>
      </div>

      <!-- tagline + module strip -->
      <div class="relative z-10 px-10 pb-9">
        <h2 class="text-xl font-semibold leading-snug text-white">
          One portal for every campus service request.
        </h2>
        <p class="mt-1.5 max-w-md text-sm text-blue-200/70">
          A unified service request platform for the PSHS System.
        </p>
        <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2">
          <span v-for="m in modules" :key="m.label" class="inline-flex items-center gap-1.5 text-[11px] font-medium text-blue-200/60">
            <component :is="m.icon" class="h-3.5 w-3.5" />
            {{ m.label }}
          </span>
        </div>
      </div>
    </div>

    <!-- ── Right panel: sign in ──────────────────────────────────────────────── -->
    <div class="flex w-full flex-col items-center justify-center px-6 py-8 md:w-[45%]">
      <div class="w-full max-w-sm">

        <!-- brand (compact, all viewports) -->
        <div class="text-center">
          <img src="/images/pshslogo.png" alt="PSHS" class="mx-auto h-12 w-12 object-contain" />
          <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">SRMIS</h1>
          <p class="mt-1 text-sm text-slate-500">Service Requests Management Information System</p>
        </div>

        <!-- Google sign-in -->
        <button
          @click="googleLogin"
          :disabled="isLoading"
          class="mt-6 flex w-full items-center justify-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 disabled:opacity-60"
        >
          <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" class="h-5 w-5" alt="" />
          {{ isLoading ? 'Signing in…' : 'Continue with Google' }}
        </button>

        <div class="my-4 flex items-center gap-3">
          <div class="h-px flex-1 bg-slate-200"></div>
          <span class="text-xs font-medium uppercase tracking-wide text-slate-400">or sign in with email</span>
          <div class="h-px flex-1 bg-slate-200"></div>
        </div>

        <!-- Email + password -->
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              autocomplete="username"
              placeholder="name@crc.pshs.edu.ph"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />

            <!-- live campus detection -->
            <p v-if="detectedCampus?.known" class="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
              <CheckCircleIcon class="h-3.5 w-3.5" />
              {{ detectedCampus.name }}
            </p>
            <p v-else-if="detectedCampus" class="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
              <QuestionMarkCircleIcon class="h-3.5 w-3.5" />
              Use your campus address (@&lt;campus&gt;.pshs.edu.ph) or @pshssystem.edu.ph for OED
            </p>
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
          </div>

          <div>
            <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              autocomplete="current-password"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
          </div>

          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              Remember me
            </label>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
          >
            {{ form.processing ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <p class="mt-5 text-center text-xs leading-relaxed text-slate-400">
          Your campus is detected automatically from your email address.<br />
          Need access? Contact your campus administrator.
        </p>
      </div>

      <div class="mt-6 text-center">
        <p class="text-[11px] text-slate-300">
          SRMIS v{{ appVersion }} · Philippine Science High School System
        </p>
        <p class="mt-1 text-[11px] text-slate-400">
          Developed by the PSHS-CRC MIS Team
        </p>
        <a href="/developer" class="mt-0.5 inline-block text-[11px] font-medium text-indigo-400 hover:text-indigo-600">
          Meet the developers →
        </a>
      </div>
    </div>
  </div>
</template>
