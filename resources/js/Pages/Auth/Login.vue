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
  const raw = String(msg)
  if (raw.includes('Unable to logged in') || raw.includes('inactive')) {
    showAlert({ icon: 'error', title: 'Unable to log in', text: 'Your account is inactive. Please contact your campus administrator.' })
  } else if (raw.includes('These credentials do not match') || raw.includes('password')) {
    showAlert({ icon: 'error', title: 'Login Failed', text: 'The email or password you entered is incorrect. Please try again.' })
  } else if (raw.includes('throttle') || raw.includes('Too many')) {
    showAlert({ icon: 'error', title: 'Too Many Attempts', text: 'Too many login attempts. Please wait a moment and try again.' })
  } else {
    showAlert({ icon: 'error', title: 'Login Failed', text: 'Unable to sign in. Please contact your campus administrator if this persists.' })
  }
}, { immediate: true })

// A long-idle login tab (or one restored from the browser's back/forward
// cache) can hold a stale session/XSRF-token cookie pair — the server mints
// a fresh one on the next request, but this raw axios call never sees it
// since it bypasses Inertia's own request cycle. On a 419, refresh the pair
// with one GET and retry the POST once instead of surfacing a raw CSRF error.
async function postGoogleLogin(payload) {
  try {
    return await axios.post('/google/login', payload)
  } catch (error) {
    if (error.response?.status === 419) {
      await axios.get(route('login'))
      return axios.post('/google/login', payload)
    }
    throw error
  }
}

const googleLogin = async () => {
  if (isLoading.value) return
  try {
    isLoading.value = true
    const { user } = await signInWithPopup(auth, provider)
    if (!isAuthorizedEmail(user.email)) {
      showAlert({ icon: 'error', title: 'Unauthorized Email', text: 'Only official PSHS campus or OED accounts are allowed.' })
      return
    }
    const idToken = await user.getIdToken()
    const { data } = await postGoogleLogin({ email: user.email, name: user.displayName, uid: user.uid, idToken })
    if (!data?.success) throw new Error('Account verification failed')
    showAlert({ icon: 'success', title: 'Login Successful', text: `Welcome, ${user.displayName}! Redirecting…`, timer: 1500, showConfirmButton: false })
    window.location.href = data.redirect_to
  } catch (error) {
    showAlert({ icon: 'error', title: 'Login Failed', text: error.response?.data?.message || error.message })
  } finally {
    isLoading.value = false
  }
}

// If this login page itself was restored from the bfcache (e.g. the user
// hit the browser back button after navigating away), force a real reload
// so the session/XSRF cookie pair — and the page's own CSRF-bound form — are
// guaranteed fresh before any submit.
window.addEventListener('pageshow', (event) => {
  if (event.persisted) window.location.reload()
})

// ── Email + password fallback (campus admins / non-Google accounts) ──────────
const form = useForm({ email: '', password: '', remember: false })

function submit() {
  if (detectedCampus.value && !detectedCampus.value.known) {
    showAlert({ icon: 'error', title: 'Unauthorized Email', text: 'Only official PSHS campus or OED accounts are allowed. Use your @<campus>.pshs.edu.ph or @pshssystem.edu.ph address.' })
    return
  }
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
  <Head title="STRIDE — Sign in" />

  <div class="flex min-h-screen bg-white">

    <!-- ── Left panel: product preview ──────────────────────────────────────── -->
    <div class="relative hidden w-[55%] flex-col justify-between overflow-hidden md:flex"
         style="background: linear-gradient(160deg, #091F54 0%, #10399B 55%, #1447C0 100%);">

      <!-- dot-grid texture -->
      <div class="pointer-events-none absolute inset-0 opacity-[0.07]"
           style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 22px 22px;"></div>

      <!-- soft background shapes -->
      <div class="pointer-events-none absolute inset-0 opacity-20">
        <div class="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-sky-400/30 blur-3xl"></div>
        <div class="absolute bottom-10 right-0 h-80 w-80 rounded-full bg-primary-400/30 blur-3xl"></div>
      </div>

      <!-- brand -->
      <div class="relative z-10 flex items-center gap-3 px-10 pt-8">
        <img src="/images/pshslogo.png" alt="PSHS" class="h-9 w-9 object-contain" style="filter: drop-shadow(0 0 8px rgba(56,189,248,0.45));" />
        <div>
          <p class="text-sm font-bold tracking-wide text-white">STRIDE</p>
          <p class="text-[11px] text-blue-200/60">Philippine Science High School System</p>
        </div>
      </div>

      <!-- tilted product-preview mockup with floating glass chips -->
      <div class="relative z-10 flex flex-1 items-center justify-center px-12">
        <div class="relative w-full max-w-md" style="perspective: 1600px;">

          <div class="animate-floaty absolute -top-8 left-4 z-20 flex items-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-3 py-2 text-[11px] font-medium text-white shadow-lg backdrop-blur-md"
               style="animation-delay: .3s;">
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/90">
              <CheckCircleIcon class="h-3.5 w-3.5 text-emerald-900" />
            </span>
            Request approved
          </div>
          <div class="animate-floaty absolute -bottom-8 right-4 z-20 flex items-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-3 py-2 text-[11px] font-medium text-white shadow-lg backdrop-blur-md"
               style="animation-delay: 1.4s;">
            <ChatBubbleLeftRightIcon class="h-3.5 w-3.5 text-sky-300" />
            3 new messages
          </div>

          <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5"
               style="transform: rotateY(-9deg) rotateX(5deg) rotateZ(1deg); box-shadow: 0 40px 80px -20px rgba(4,10,50,0.55), 0 15px 35px -10px rgba(4,10,50,0.4);">

            <!-- window chrome -->
            <div class="flex items-center gap-1.5 border-b border-slate-100 bg-slate-50 px-4 py-2.5">
              <span class="h-2.5 w-2.5 rounded-full bg-red-300"></span>
              <span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span>
              <span class="h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
              <span class="ml-3 rounded-full bg-white px-3 py-0.5 text-[10px] font-medium text-slate-400 ring-1 ring-slate-200">
                stride.pshs.edu.ph/dashboard
              </span>
            </div>

            <div class="flex">
              <!-- mini nav rail -->
              <div class="hidden w-14 flex-col items-center gap-3 border-r border-slate-100 bg-slate-50/60 py-4 sm:flex">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-600 text-white">
                  <ComputerDesktopIcon class="h-4 w-4" />
                </span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400">
                  <InboxIcon class="h-4 w-4" />
                </span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400">
                  <UsersIcon class="h-4 w-4" />
                </span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400">
                  <ChartBarIcon class="h-4 w-4" />
                </span>
              </div>

              <!-- mock dashboard content -->
              <div class="flex-1 space-y-3 p-4">
                <div class="flex items-center justify-between">
                  <p class="text-xs font-semibold text-slate-700">Dashboard</p>
                  <div class="h-5 w-16 rounded-md bg-primary-50"></div>
                </div>

                <div class="grid grid-cols-3 gap-2">
                  <div class="rounded-lg bg-emerald-50 p-2">
                    <p class="text-sm font-bold text-emerald-700">128</p>
                    <p class="text-[9px] font-medium text-emerald-600">Approved</p>
                  </div>
                  <div class="rounded-lg bg-amber-50 p-2">
                    <p class="text-sm font-bold text-amber-700">14</p>
                    <p class="text-[9px] font-medium text-amber-600">Pending</p>
                  </div>
                  <div class="rounded-lg bg-primary-50 p-2">
                    <p class="text-sm font-bold text-primary-700">42</p>
                    <p class="text-[9px] font-medium text-primary-600">In progress</p>
                  </div>
                </div>

                <div class="space-y-1.5 rounded-lg border border-slate-100 p-2">
                  <div class="flex items-center gap-2">
                    <span class="h-5 w-5 rounded-md bg-slate-100"></span>
                    <span class="h-2 flex-1 rounded-full bg-slate-100"></span>
                    <span class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[8px] font-semibold text-emerald-700">Approved</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="h-5 w-5 rounded-md bg-slate-100"></span>
                    <span class="h-2 w-3/4 rounded-full bg-slate-100"></span>
                    <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[8px] font-semibold text-amber-700">Pending</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="h-5 w-5 rounded-md bg-slate-100"></span>
                    <span class="h-2 w-5/6 rounded-full bg-slate-100"></span>
                    <span class="rounded-full bg-primary-100 px-1.5 py-0.5 text-[8px] font-semibold text-primary-700">In progress</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
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
      <div class="animate-fade-in-up w-full max-w-sm">

        <!-- brand (compact, all viewports) -->
        <div class="text-center">
          <img src="/images/pshslogo.png" alt="PSHS" class="mx-auto h-12 w-12 object-contain" />
          <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">STRIDE</h1>
          <p class="mt-1 text-sm text-slate-500">Service Ticketing and Request Information &amp; Dispatch Engine</p>
        </div>

        <!-- Google sign-in -->
        <button
          @click="googleLogin"
          :disabled="isLoading"
          class="mt-6 flex w-full items-center justify-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:shadow-md disabled:opacity-60"
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
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm transition focus:border-primary-300 focus:outline-none focus:ring-2 focus:ring-primary-500"
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
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm transition focus:border-primary-300 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
          </div>

          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
              Remember me
            </label>
            <a :href="route('password.request')" class="text-sm font-medium text-primary-600 hover:text-primary-700">
              Forgot password?
            </a>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-lg disabled:pointer-events-none disabled:opacity-60"
          >
            {{ form.processing ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <p class="mt-5 text-center text-xs leading-relaxed text-slate-400">
          Need access? Contact your campus administrator.
        </p>
      </div>

      <div class="animate-fade-in-up mt-6 text-center" style="animation-delay:.15s;">
        <p class="text-[11px] text-slate-400">
          STRIDE v{{ appVersion }} · Philippine Science High School System
        </p>
        <p class="mt-1 text-[11px] text-slate-400">
          Developed by the <a href="/developer" class="font-medium text-primary-500 hover:text-primary-700">PSHS-CRC MIS Team</a>
        </p>
      </div>
    </div>
  </div>
</template>
