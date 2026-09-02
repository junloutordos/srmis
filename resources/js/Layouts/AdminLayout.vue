<script setup>
import { ref, computed, markRaw, onMounted, onUnmounted, watch } from "vue";
import { storageUrl } from "@/Composables/useStorage.js";
import { sessionExpired } from "@/Composables/useSession.js";
const props = defineProps({ title: { type: String, default: '' } });
const title = props.title;
import { Head, usePage, router, useForm } from "@inertiajs/vue3";
import SidebarLink from "@/Components/SidebarLink.vue";
import NotificationBell from '@/Components/NotificationBell.vue';
import ProfileEditModal from '@/Components/ProfileEditModal.vue';
import {
  HomeIcon,
  UsersIcon,
  DocumentTextIcon,
  Bars3Icon,
  ChevronDownIcon,
  ClipboardDocumentListIcon,
  UserGroupIcon,
  ChartBarIcon,
  ServerStackIcon,
  QueueListIcon,
  ComputerDesktopIcon,
  BookOpenIcon,
  ArchiveBoxIcon,
  WrenchScrewdriverIcon,
  ShoppingCartIcon,
  CreditCardIcon,
  BanknotesIcon,
  CurrencyDollarIcon,
  HeartIcon,
  ChatBubbleLeftRightIcon,
  ChatBubbleOvalLeftEllipsisIcon,
  HomeModernIcon,
  UserIcon,
  CursorArrowRippleIcon,
  ClockIcon,
  XMarkIcon,
  ShieldCheckIcon,
  KeyIcon,
  TableCellsIcon,
  StarIcon,
  DocumentChartBarIcon,
  AcademicCapIcon,
  CalendarDaysIcon,
  SparklesIcon,
  ScaleIcon,
  CpuChipIcon,
  AdjustmentsHorizontalIcon,
  CheckCircleIcon,
  BuildingLibraryIcon,
  IdentificationIcon,
  UserPlusIcon,
  UserCircleIcon,
  InboxIcon,
  QuestionMarkCircleIcon,
  ArrowUpCircleIcon,

} from "@heroicons/vue/24/outline";

// (menu insertion removed here; menu items are defined later in `menuItems`)
// --- State ---
const collapsed = ref(false);
const mobileOpen = ref(false);

const expanded = ref({});
const showVersionModal = ref(false);
const showAddVersionModal = ref(false);
const versionForm = useForm({
  version:    '',
  date:       new Date().toISOString().slice(0, 10),
  remarks:    '',
  is_current: true,
});
function openAddVersionModal() {
  versionForm.reset();
  versionForm.date       = new Date().toISOString().slice(0, 10);
  versionForm.is_current = true;
  showAddVersionModal.value = true;
}
function submitVersion() {
  versionForm.post(route('app-versions.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showAddVersionModal.value = false;
      versionForm.reset();
    },
  });
}

// ─── Chat unread badge (Phase 8) ──────────────────────────────────────────
const chatUnreadCount = ref(0);

async function fetchChatUnread() {
  try {
    const res = await window.axios.get('/api/chat/unread-count');
    chatUnreadCount.value = res.data.unread_count ?? 0;
  } catch {
    // silently ignore — badge just won't show
  }
}

let chatEchoChannel = null;

function setupChatNotifications() {
  if (!window.Echo) return;

  const userId = user?.id;
  if (!userId) return;

  chatEchoChannel = window.Echo.private(`user.${userId}`)
    .listen('.new.message', (e) => {
      // Increment badge if not currently on the Chat page
      if (!route().current('chat.index')) {
        chatUnreadCount.value += 1;
      }

      // Browser notification when tab is not focused
      if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
        const senderName = e.message?.sender_name ?? 'Someone';
        const body = e.message?.body || '📎 Attachment';
        new Notification(`New message from ${senderName}`, {
          body,
          icon: '/favicon.ico',
        });
      }
    });
}

// Reset badge when navigating to Chat
watch(() => route().current('chat.index'), (onChat) => {
  if (onChat) chatUnreadCount.value = 0;
});

// Close mobile sidebar on Inertia navigation
let removeNavListener;
onMounted(() => {
  removeNavListener = router.on('navigate', () => {
    mobileOpen.value = false;
    // Reset badge when navigating to Chat page
    if (route().current('chat.index')) chatUnreadCount.value = 0;
  });

  fetchChatUnread();
  setupChatNotifications();

  // Request browser notification permission (non-blocking)
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }
});
onUnmounted(() => {
  if (removeNavListener) removeNavListener();
  if (chatEchoChannel) {
    window.Echo?.leave(`user.${user?.id}`);
    chatEchoChannel = null;
  }
});

// --- Page + Auth ---
const page = usePage();
const appVersion = computed(() => page.props.appVersion ?? { current: '1.0.0', history: [] });
const user = page.props.auth?.user || { role: { name: "Guest" }, name: "Guest" };
const roleName = user.role?.name || "Guest";
// Support multiple roles: array of role name strings
const baseRoleNames = user.roleNames?.length ? user.roleNames : (roleName !== "Guest" ? [roleName] : []);
// Inject synthetic 'PMRater' role when user is a committee head or SA coordinator
const roleNames = [
  ...baseRoleNames,
  ...(page.props.isPMRater ? ['PMRater'] : []),
  ...(page.props.isAUH    ? ['AUH']     : []),
];

// Permission set — populated by HandleInertiaRequests via shared Inertia props
// Using a Set for O(1) lookups on every sidebar render
const userPermissions = new Set(user.permissions ?? []);
const hasPerm = (...perms) => perms.some(p => userPermissions.has(p));

// Also expose hasPerm for use in template (e.g. version modal button)
const isAdmin = hasPerm('roles.assign');

// --- Mandatory digital signature + PIN setup gate ---
// Blocks interaction with the rest of the app (via a non-dismissable overlay,
// not a server redirect) until the user has both a signature image and PIN
// on file. Client-side only, so it can never create a redirect loop or
// interfere with JSON/API calls or signed email-action links.
const showSignatureGate = computed(() =>
  !!user.needsSignatureSetup && page.component !== 'Profile/Signature'
);


// --- Helpers ---
const isActive = (name) => name && route().current(name); // ✅ check via routeName

// Safely coerce a raw Inertia prop value to a non-negative integer
const toBadgeInt = (val) => {
  const n = parseInt(val, 10);
  return isNaN(n) || n < 0 ? 0 : n;
};

// Return numeric badge from shared Inertia props based on child routeName
const getBadge = (child) => {
  const rn = child?.routeName || null;
  // Chat badge is available to all roles — check it first
  if (rn === 'chat.index') return toBadgeInt(chatUnreadCount.value);
  if (!page || !page.props) return 0;
  switch (rn) {
    case 'consultations.index':
      return toBadgeInt(page.props.consultationsNotificationCount);
    case 'jobrequests.index':
      return toBadgeInt(page.props.itJobRequestsNotificationCount);
    case 'vehicle-requests.index':
      return toBadgeInt(page.props.vehicleRequestsNotificationCount);
    case 'gatepass.index':
      return toBadgeInt(page.props.gatepassNotificationCount);
    case 'facility-requests.index':
      return toBadgeInt(page.props.facilityRequestsNotificationCount);
    case 'service-requests.index':
      return toBadgeInt(page.props.serviceRequestsNotificationCount);
    case 'work-requests.index':
      return toBadgeInt(page.props.workRequestsNotificationCount);
    case 'library.borrowings.index':
      return toBadgeInt(page.props.borrowingsOverdueCount);
    case 'document-tracking.index':
      return toBadgeInt(page.props.documentTrackingNotificationCount);
    case 'approvals.inbox':
      return toBadgeInt(page.props.approvalInboxCount);
    default:
      return 0;
  }
};

// Return aggregate badge count for a group (sum of all children badges), capped at 99
const getGroupBadge = (item) => {
  if (!item.children?.length) return 0;
  const total = item.children.reduce((sum, child) => sum + getBadge(child), 0);
  return Math.min(total, 99);
};

const showProfileModal = ref(false);

// Consultation Log modal state
const showConsultationLogModal = ref(false);
const consultationLogStart = ref("");
const consultationLogEnd = ref("");
const consultationLogRouteName = ref(null);
const consultationLogType = ref('student');
const openConsultationLogModal = (routeName = null) => {
  consultationLogRouteName.value = routeName;
  // set default type based on incoming routeName, allow user to change in modal
  consultationLogType.value = (routeName && String(routeName).includes('employee')) ? 'employee' : 'student';
  showConsultationLogModal.value = true;
};
const closeConsultationLogModal = () => {
  showConsultationLogModal.value = false;
  consultationLogStart.value = "";
  consultationLogEnd.value = "";
};
  const generateConsultationLog = () => {
  if (!consultationLogStart.value || !consultationLogEnd.value) {
    alert("Please select both start and end dates.");
    return;
  }
  if (consultationLogStart.value > consultationLogEnd.value) {
    alert("Start date must be before or equal to end date.");
    return;
  }
  const base = consultationLogType.value === 'employee' ? 'consultations.employee.log.print' : 'consultations.log.print';
  const url = route(base) + `?start=${consultationLogStart.value}&end=${consultationLogEnd.value}&type=${consultationLogType.value}`;
  window.open(url, "_blank");
  closeConsultationLogModal();
};

// --- Attendance Logs Modal State ---
const showAttendanceModal = ref(false);
const attendanceStart = ref("");
const attendanceEnd = ref("");
const openAttendanceModal = () => {
  showAttendanceModal.value = true;
};
const closeAttendanceModal = () => {
  showAttendanceModal.value = false;
  attendanceStart.value = "";
  attendanceEnd.value = "";
};
const generateAttendanceReport = () => {
  if (!attendanceStart.value || !attendanceEnd.value) {
    alert('Please select both start and end dates.');
    return;
  }
  if (attendanceStart.value > attendanceEnd.value) {
    alert('Start date must be before or equal to end date.');
    return;
  }
  // Navigate to attendance index with query params
  router.get(route('hr.attendance.index'), { start: attendanceStart.value, end: attendanceEnd.value });
  closeAttendanceModal();
};

// --- Library Statistics Modal State ---
const showLibraryStatsModal = ref(false);
const libraryStatsStart = ref("");
const libraryStatsEnd = ref("");
const openLibraryStatsModal = () => {
  showLibraryStatsModal.value = true;
};
const closeLibraryStatsModal = () => {
  showLibraryStatsModal.value = false;
  libraryStatsStart.value = "";
  libraryStatsEnd.value = "";
};
const generateLibraryStats = () => {
  if (!libraryStatsStart.value || !libraryStatsEnd.value) {
    alert("Please select both start and end dates.");
    return;
  }
  if (libraryStatsStart.value > libraryStatsEnd.value) {
    alert("Start date must be before or equal to end date.");
    return;
  }
  const url = route('library.statistics.report') + `?start=${libraryStatsStart.value}&end=${libraryStatsEnd.value}`;
  window.open(url, "_blank");
  closeLibraryStatsModal();
};

// --- Health Statistics Modal ---
const showHealthStatsModal = ref(false);
const healthStatsStart = ref("");
const healthStatsEnd = ref("");
const openHealthStatsModal = () => {
  showHealthStatsModal.value = true;
};
const closeHealthStatsModal = () => {
  showHealthStatsModal.value = false;
  healthStatsStart.value = "";
  healthStatsEnd.value = "";
};
const generateHealthStats = () => {
  if (!healthStatsStart.value || !healthStatsEnd.value) {
    alert("Please select both start and end dates.");
    return;
  }
  if (healthStatsStart.value > healthStatsEnd.value) {
    alert("Start date must be before or equal to end date.");
    return;
  }
  // Open a report route if available (may be added later).
  try {
    const url = route('health.statistics.report') + `?start=${healthStatsStart.value}&end=${healthStatsEnd.value}&autoprint=1`;
    window.open(url, "_blank");
  } catch (e) {
    // If route helper is not available for this route yet, just close the modal.
    console.warn('health.statistics.report route not defined yet');
  }
  closeHealthStatsModal();
};

// --- Menu Items ---
const menuItems = [
  {
    label: "Dashboard",
    routeName: "dashboard",
    href: route("dashboard"),
    icon: HomeIcon,
  },
  {
    label: "Approvals",
    routeName: "approvals.inbox",
    href: route("approvals.inbox"),
    icon: InboxIcon,
    roles: ["Administrator", "DivisionChief", "OCD", "GSU Head", "FAD Chief"],
  },
  {
    label: "Chat",
    routeName: "chat.index",
    href: route("chat.index"),
    icon: ChatBubbleLeftRightIcon,
    permissions: ["chat.access"],
  },
  {
    label: "Data Management",
    icon: UsersIcon,
    permissions: ["users.view", "roles.assign", "org.view"],
    children: [
      {
        label: "All Users",
        routeName: "users.index",
        href: route("users.index"),
        icon: UserGroupIcon,
        permissions: ["users.view"],
      },
      {
        label: "Roles & Permissions",
        routeName: "admin.roles",
        href: "/admin/roles",
        icon: ShieldCheckIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Permissions",
        routeName: "admin.permissions",
        href: "/admin/permissions",
        icon: KeyIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Assign Roles",
        routeName: "admin.assign-roles",
        href: "/admin/assign-roles",
        icon: UserGroupIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Division",
        routeName: "roles.divisions",
        href: route("roles.divisions"),
        icon: CursorArrowRippleIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Office/Unit",
        routeName: "offices.index",
        href: route("offices.index"),
        icon: HomeIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Org Structure",
        routeName: "hr.org.index",
        href: route("hr.org.index"),
        icon: BuildingLibraryIcon,
        permissions: ["org.view"],
      },
      {
        label: "Buildings",
        routeName: "buildings.index",
        href: route("buildings.index"),
        icon: HomeModernIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Campus",
        routeName: "campuses.index",
        href: route("campuses.index"),
        icon: HomeIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Rooms",
        routeName: "rooms.index",
        href: route("rooms.index"),
        icon: HomeIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Vehicle",
        routeName: "vehicles.index",
        href: route("vehicles.index"),
        icon: ArchiveBoxIcon,
        permissions: ["vehicles.manage"],
      },
      {
        label: "Facility",
        routeName: "facilities.index",
        href: route("facilities.index"),
        icon: ArchiveBoxIcon,
        permissions: ["facilities.manage"],
      },
    ],
  },
  {
    label: "MIS",
    icon: ServerStackIcon,
    permissions: ["it.requests.view", "it.requests.manage", "it.requests.dispatch"],
    children: [
      {
        label: "MIS Dashboard",
        routeName: "mis.dashboard",
        href: route("mis.dashboard"),
        icon: ChartBarIcon,
        permissions: ["it.requests.manage"],
      },
      {
        label: "CSM Feedback",
        routeName: "csm.dashboard",
        href: route("csm.dashboard"),
        icon: StarIcon,
        permissions: ["it.requests.manage"],
      },
      {
        label: "IT Job Requests",
        routeName: "jobrequests.index",
        href: route("jobrequests.index"),
        icon: ComputerDesktopIcon,
        permissions: ["it.requests.view"],
      },
      {
        label: "Dispatch Queue",
        routeName: "jobrequests.dispatch",
        href: route("jobrequests.dispatch"),
        icon: ComputerDesktopIcon,
        permissions: ["it.requests.dispatch"],
      },
      {
        label: "Equipment Inventory",
        routeName: "ict-equipments.index",
        href: route("ict-equipments.index"),
        icon: QueueListIcon,
        permissions: ["it.equipment.view"],
      },
      {
        label: "PMS",
        routeName: "ict-pms.index",
        href: route("ict-pms.index"),
        icon: ClockIcon,
        permissions: ["it.equipment.view"],
      },
      {
        label: "ITJR Categories",
        routeName: "admin.it-job-categories.index",
        href: route("admin.it-job-categories.index"),
        icon: ComputerDesktopIcon,
        permissions: ["it.requests.manage"],
      },
    ],
  },
  {
    label: "General Services",
    icon: WrenchScrewdriverIcon,
    permissions: ["vehicles.view", "vehicles.dispatch", "facilities.view"],
    children: [
      {
        label: "Vehicle Request",
        routeName: "vehicle-requests.index",
        href: route("vehicle-requests.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["vehicles.view"],
      },
      {
        label: "Vehicle Dispatch",
        routeName: "vehicle-requests.gsu-dispatch",
        href: route("vehicle-requests.gsu-dispatch"),
        icon: ClipboardDocumentListIcon,
        permissions: ["vehicles.dispatch"],
      },
      {
        label: "Facility Request",
        routeName: "facility-requests.index",
        href: route("facility-requests.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["facilities.view"],
      },
      {
        label: "Request for Services",
        routeName: "service-requests.index",
        href: route("service-requests.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["facilities.view"],
      },
      {
        label: "Work Request",
        routeName: "work-requests.index",
        href: route("work-requests.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["facilities.view"],
      },
    ],
  },
  {
    label: "Reports",
    icon: DocumentChartBarIcon,
    permissions: ["roles.assign"],
    children: [
      {
        label: "Audit Logs",
        routeName: "reports.audit_logs",
        href: route("reports.audit_logs"),
        icon: TableCellsIcon,
        permissions: ["roles.assign"],
      },
    ],
  },
];

// --- Filter Menu by Role ---


const filterMenuByRole = (items, userRoleNames) =>
  items
    .filter((item) => {
      if (item.permissions?.length) {
        return hasPerm(...item.permissions);
      }
      return item.roles?.some((r) => userRoleNames.includes(r)) ?? true;
    })
    .map((item) =>
      item.children
        ? { ...item, icon: item.icon ? markRaw(item.icon) : item.icon, children: filterMenuByRole(item.children, userRoleNames) }
        : item
    );

const filteredMenu = computed(() => filterMenuByRole(menuItems, roleNames));

// --- Expand logic ---
const toggleExpand = (label) => (expanded.value[label] = !expanded.value[label]);

filteredMenu.value.forEach((item) => {
  if (item.children?.some((c) => isActive(c.routeName))) {
    expanded.value[item.label] = true;
  }
});
</script>

<template>
  <Head :title="title" />

  <div class="min-h-screen flex bg-slate-50">
    <!-- Mobile backdrop -->
    <div
      v-if="mobileOpen"
      @click="mobileOpen = false"
      class="fixed inset-0 bg-black/50 z-30 md:hidden backdrop-blur-sm"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        'transition-all duration-300 z-40 flex-shrink-0 flex flex-col',
        'fixed inset-y-0 left-0 md:static md:inset-auto',
        mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        collapsed ? 'w-72 md:w-[68px]' : 'w-72 md:w-60',
      ]"
      style="background: linear-gradient(180deg, #060e50 0%, #0d1f8a 55%, #1447c0 100%); box-shadow: inset -1px 0 0 rgba(0,200,232,0.18), 4px 0 28px rgba(6,14,80,0.5);"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center gap-3 border-b border-white/10 px-4 shrink-0">
        <img src="/images/pshslogo.png" alt="PSHS Logo" class="h-8 w-8 shrink-0 rounded-lg object-contain" style="filter: drop-shadow(0 0 8px rgba(0,200,232,0.45));" />
        <div v-if="!collapsed" class="min-w-0">
          <p class="text-sm font-bold text-white leading-tight truncate tracking-wide">STRIDE</p>
          <p class="text-[10px] text-blue-200/50 truncate">{{ page.props.campus?.name || 'STRIDE' }}</p>
        </div>
        <!-- Close button (mobile only) -->
        <button
          @click="mobileOpen = false"
          class="ml-auto p-1 rounded-lg hover:bg-white/10 md:hidden shrink-0"
          aria-label="Close sidebar"
        >
          <XMarkIcon class="h-4 w-4 text-blue-200/60" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-0.5 scrollbar-thin">
        <template v-for="item in filteredMenu" :key="item.label">

          <!-- Section label -->
          <div
            v-if="item.type === 'section' && !collapsed"
            class="px-3 pt-5 pb-1.5 text-[10px] font-bold text-blue-200/45 uppercase tracking-[0.12em]"
          >
            {{ item.label }}
          </div>
          <div v-else-if="item.type === 'section' && collapsed" class="my-2 mx-3 h-px bg-white/10" />

          <!-- Single link -->
          <SidebarLink
            v-else-if="!item.children"
            :href="item.href"
            :target="item.target"
            :icon="item.icon"
            :label="item.label"
            :collapsed="collapsed"
            :active="isActive(item.routeName)"
            :badge="getBadge(item)"
          />

          <!-- Group with children -->
          <div v-else>
            <button
              @click="toggleExpand(item.label)"
              class="group relative flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150 border-l-2 border-transparent"
              :class="expanded[item.label]
                ? 'bg-white/10 text-white border-l-2 border-[#00c8e8]'
                : 'text-blue-200/75 hover:bg-white/10 hover:text-white'"
            >
              <component
                v-if="item.icon"
                :is="item.icon"
                class="h-4 w-4 shrink-0 transition-colors"
                :class="[
                  collapsed ? 'mx-auto' : 'mr-2.5',
                  expanded[item.label] ? 'text-[#00c8e8]' : 'text-blue-200/50 group-hover:text-white'
                ]"
              />
              <span v-if="!collapsed" class="flex-1 truncate text-left">{{ item.label }}</span>
              <span
                v-if="!collapsed && !expanded[item.label] && getGroupBadge(item) > 0"
                class="ml-1 shrink-0 inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none bg-amber-400 text-slate-900"
              >{{ getGroupBadge(item) }}</span>
              <span
                v-else-if="collapsed && getGroupBadge(item) > 0"
                class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-amber-400"
              />
              <ChevronDownIcon
                v-if="!collapsed"
                class="h-3.5 w-3.5 ml-1 shrink-0 text-blue-200/35 transition-transform duration-200"
                :class="{ 'rotate-180 text-[#00c8e8]': expanded[item.label] }"
              />
            </button>

            <div v-show="expanded[item.label] && !collapsed" class="mt-0.5 ml-4 pl-3 border-l border-white/10 space-y-0.5">
              <template v-for="child in item.children" :key="child.label">
                <SidebarLink
                  v-if="!['consultations.log.print','consultations.employee.log.print','library.statistics.report','health.statistics.report','hr.attendance.index'].includes(child.routeName)"
                  :href="child.href"
                  :target="child.target"
                  :label="child.label"
                  :icon="child.icon"
                  :collapsed="collapsed"
                  :active="isActive(child.routeName)"
                  :badge="getBadge(child)"
                />
                <!-- Modal-trigger child buttons — styled to match SidebarLink -->
                <button
                  v-else-if="['consultations.log.print','consultations.employee.log.print'].includes(child.routeName)"
                  @click="openConsultationLogModal(child.routeName)"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-blue-200/75 transition-all duration-150 hover:bg-white/10 hover:text-white pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-blue-200/50 group-hover:text-white" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'library.statistics.report'"
                  @click="openLibraryStatsModal"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-blue-200/75 transition-all duration-150 hover:bg-white/10 hover:text-white pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-blue-200/50 group-hover:text-white" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'health.statistics.report'"
                  @click="openHealthStatsModal"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-blue-200/75 transition-all duration-150 hover:bg-white/10 hover:text-white pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-blue-200/50 group-hover:text-white" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'hr.attendance.index'"
                  @click="openAttendanceModal"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-blue-200/75 transition-all duration-150 hover:bg-white/10 hover:text-white pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-blue-200/50 group-hover:text-white" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
              </template>
            </div>
          </div>
        </template>
      </nav>

      <!-- Version footer -->
      <div class="shrink-0 border-t border-white/10 px-3 py-3">
        <button
          @click="showVersionModal = true"
          class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-blue-200/50 hover:bg-white/10 hover:text-blue-200 transition-all duration-150"
          :class="collapsed ? 'justify-center' : 'justify-between'"
        >
          <span class="font-mono" :class="collapsed ? 'font-bold text-blue-200/50' : 'text-blue-200/50'">
            v{{ appVersion.current }}
          </span>
          <span v-if="!collapsed" class="text-blue-200/35">Changelog →</span>
        </button>
      </div>
    </aside>

    <!-- Version History Modal -->
    <Teleport to="body">
      <div v-if="showVersionModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/40" @click="showVersionModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
              <h2 class="text-lg font-bold text-gray-800">Version History</h2>
              <p class="text-xs text-gray-400">Current version: v{{ appVersion.current }}</p>
            </div>
            <button @click="showVersionModal = false" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
          <div class="overflow-y-auto px-6 py-4 space-y-4">
            <div
              v-for="entry in appVersion.history"
              :key="entry.version"
              class="flex gap-4"
            >
              <div class="flex flex-col items-center">
                <div class="w-2.5 h-2.5 rounded-full mt-1.5"
                  :class="entry.version === appVersion.current ? 'bg-blue-500' : 'bg-gray-300'">
                </div>
                <div class="w-px flex-1 bg-gray-200 mt-1"></div>
              </div>
              <div class="pb-4 flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <span class="font-semibold text-sm text-gray-800">v{{ entry.version }}</span>
                  <span v-if="entry.version === appVersion.current"
                    class="text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full">Latest</span>
                  <span class="text-xs text-gray-400 ml-auto">{{ entry.date }}</span>
                </div>
                <p class="text-sm text-gray-600">{{ entry.remarks }}</p>
              </div>
            </div>
            <p v-if="!appVersion.history.length" class="text-sm text-gray-400 text-center py-4">No history yet.</p>
          </div>
          <!-- Admin footer -->
          <div v-if="isAdmin" class="px-6 py-3 border-t flex justify-end">
            <button
              @click="openAddVersionModal"
              class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              + Add New Version
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add Version Modal -->
    <Teleport to="body">
      <div v-if="showAddVersionModal" class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="showAddVersionModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-lg font-bold text-gray-800">Add New Version</h2>
            <button @click="showAddVersionModal = false" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
          <form @submit.prevent="submitVersion" class="px-6 py-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Version <span class="text-red-500">*</span></label>
              <input
                v-model="versionForm.version"
                type="text"
                placeholder="e.g. 1.2.0"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                required
              />
              <p v-if="versionForm.errors.version" class="mt-1 text-xs text-red-500">{{ versionForm.errors.version }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Release Date <span class="text-red-500">*</span></label>
              <input
                v-model="versionForm.date"
                type="date"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Remarks / Changelog <span class="text-red-500">*</span></label>
              <textarea
                v-model="versionForm.remarks"
                rows="4"
                placeholder="Describe what changed in this version…"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                required
              ></textarea>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
              <input v-model="versionForm.is_current" type="checkbox" class="rounded border-gray-300 text-blue-600" />
              Set as current version
            </label>
            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="showAddVersionModal = false" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 text-sm">Cancel</button>
              <button
                type="submit"
                :disabled="versionForm.processing"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm disabled:opacity-60 disabled:cursor-not-allowed min-w-[80px]"
              >
                <span v-if="versionForm.processing">Saving…</span>
                <span v-else>Save</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Navbar -->
      <header class="h-14 bg-white border-b border-gray-100 flex items-center justify-between px-4 md:px-6">
        <!-- Left: hamburger + page title -->
        <div class="flex items-center gap-3">
          <!-- Mobile hamburger -->
          <button
            @click="mobileOpen = !mobileOpen"
            class="p-1.5 rounded-md hover:bg-gray-100 md:hidden"
            aria-label="Open sidebar"
          >
            <Bars3Icon class="h-5 w-5 text-gray-500" />
          </button>
          <!-- Desktop hamburger -->
          <button
            @click="collapsed = !collapsed; if (collapsed) expanded = {}"
            class="hidden md:block p-1.5 rounded-md hover:bg-gray-100"
            aria-label="Toggle sidebar"
          >
            <Bars3Icon class="h-5 w-5 text-gray-500" />
          </button>
          <span v-if="title" class="hidden md:block text-sm font-medium text-gray-700">{{ title }}</span>
        </div>

        <!-- Right: notifications + chat + profile -->
        <div class="flex items-center gap-2">

        <!-- Privacy Policy -->
        <a
          href="/privacy"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 transition-colors border border-slate-200 hover:border-emerald-200"
          aria-label="Data Privacy Policy"
        >
          <ShieldCheckIcon class="h-4 w-4" />
          Privacy Policy
        </a>

        <!-- QMS Manuals -->
        <a
          href="https://drive.google.com/drive/folders/16XAkvSwQCPquxuMtgFmEOWEMAeUfOqwL"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:text-amber-600 hover:bg-amber-50 transition-colors border border-slate-200 hover:border-amber-200"
          aria-label="QMS Manuals"
        >
          <BookOpenIcon class="h-4 w-4" />
          QMS Manuals
        </a>

        <!-- Notification Bell -->
        <NotificationBell v-if="user?.id" :user-id="user.id" />

        <!-- Chat Icon -->
        <a
          :href="route('chat.index')"
          class="relative p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
          aria-label="Messenger"
        >
          <ChatBubbleLeftRightIcon class="h-5 w-5 text-gray-500" />
          <span
            v-if="chatUnreadCount > 0"
            class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white"
          >{{ chatUnreadCount > 99 ? '99+' : chatUnreadCount }}</span>
        </a>

        <!-- Profile Panel trigger -->
        <button
          @click="showProfileModal = true"
          class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <img
            v-if="storageUrl(user.profile_picture)"
            :src="storageUrl(user.profile_picture)"
            alt="User Avatar"
            class="w-7 h-7 rounded-full object-cover ring-2 ring-gray-200"
          />
          <div
            v-else
            class="w-7 h-7 rounded-full ring-2 ring-gray-200 bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-bold select-none"
          >{{ (user.name ?? '?').charAt(0).toUpperCase() }}</div>
          <div class="hidden md:block text-left">
            <p class="text-sm font-medium text-gray-800 leading-none">{{ user.name }}</p>
            <p class="text-[11px] text-gray-500 leading-none mt-0.5">{{ roleName }}</p>
          </div>
        </button>

        </div><!-- end right group -->
      </header>

      <!-- Page Content -->
      <main class="p-4 md:p-6 flex-1 min-w-0">
        <slot />
      </main>
    </div>
  <ProfileEditModal :show="showProfileModal" @close="showProfileModal = false" />
  <!-- Consultation Log Date Range Modal -->
  <div v-if="showConsultationLogModal" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black opacity-30 z-40" @click="closeConsultationLogModal"></div>
    <div @click.stop class="bg-white rounded-lg shadow-lg z-50 w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Consultation Log Generation</h3>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Report Type</label>
          <div class="mt-2 flex items-center gap-4">
            <label class="flex items-center space-x-2">
              <input type="radio" value="student" v-model="consultationLogType" class="form-radio" />
              <span class="text-sm">Student</span>
            </label>
            <label class="flex items-center space-x-2">
              <input type="radio" value="employee" v-model="consultationLogType" class="form-radio" />
              <span class="text-sm">Employee</span>
            </label>
            <label class="flex items-center space-x-2">
              <input type="radio" value="both" v-model="consultationLogType" class="form-radio" />
              <span class="text-sm">Both</span>
            </label>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Start Date</label>
          <input type="date" v-model="consultationLogStart" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">End Date</label>
          <input type="date" v-model="consultationLogEnd" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
      </div>
      <div class="px-6 py-4 border-t flex justify-end gap-2">
        <button @click="closeConsultationLogModal" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
        <button @click="generateConsultationLog" class="px-4 py-2 rounded bg-blue-600 text-white">Generate Graph</button>
      </div>
    </div>
  </div>

  <!-- Library Statistic Report Date Range Modal -->
  <div v-if="showLibraryStatsModal" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black opacity-30 z-40" @click="closeLibraryStatsModal"></div>
    <div @click.stop class="bg-white rounded-lg shadow-lg z-50 w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Library Statistic Report</h3>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Start Date</label>
          <input type="date" v-model="libraryStatsStart" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">End Date</label>
          <input type="date" v-model="libraryStatsEnd" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
      </div>
      <div class="px-6 py-4 border-t flex justify-end gap-2">
        <button @click="closeLibraryStatsModal" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
        <button @click="generateLibraryStats" class="px-4 py-2 rounded bg-blue-600 text-white">Generate</button>
      </div>
    </div>
  </div>

  <!-- Health Statistics Date Range Modal -->
  <div v-if="showHealthStatsModal" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black opacity-30 z-40" @click="closeHealthStatsModal"></div>
    <div @click.stop class="bg-white rounded-lg shadow-lg z-50 w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Statistics Report</h3>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Start Date</label>
          <input type="date" v-model="healthStatsStart" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">End Date</label>
          <input type="date" v-model="healthStatsEnd" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
      </div>
      <div class="px-6 py-4 border-t flex justify-end gap-2">
        <button @click="closeHealthStatsModal" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
        <button @click="generateHealthStats" class="px-4 py-2 rounded bg-blue-600 text-white">Generate</button>
      </div>
    </div>
  </div>

  <!-- Attendance Logs Date Range Modal -->
  <div v-if="showAttendanceModal" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black opacity-30 z-40" @click="closeAttendanceModal"></div>
    <div @click.stop class="bg-white rounded-lg shadow-lg z-50 w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Attendance Logs</h3>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Start Date</label>
          <input type="date" v-model="attendanceStart" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">End Date</label>
          <input type="date" v-model="attendanceEnd" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
      </div>
      <div class="px-6 py-4 border-t flex justify-end gap-2">
        <button @click="closeAttendanceModal" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
        <button @click="generateAttendanceReport" class="px-4 py-2 rounded bg-blue-600 text-white">View</button>
      </div>
    </div>
  </div>

</div>

  <!-- Session-expired overlay — shown when a 419 or 405 is received -->
  <Teleport to="body">
    <div v-if="sessionExpired" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl px-8 py-8 max-w-sm w-full mx-4 text-center">
        <div class="flex items-center justify-center w-14 h-14 rounded-full bg-amber-100 mx-auto mb-4">
          <svg class="w-7 h-7 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-slate-800 mb-1">Session Expired</h2>
        <p class="text-sm text-slate-500 mb-6">Your session has timed out. Please sign in again to continue.</p>
        <a
          :href="route('force-logout')"
          class="block w-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors text-center"
        >
          Sign In Again
        </a>
        <a
          :href="route('force-logout')"
          class="block w-full mt-3 text-sm text-slate-500 hover:text-slate-700 py-1 transition-colors text-center"
        >
          Sign Out
        </a>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div v-if="showSignatureGate" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl px-8 py-8 max-w-sm w-full mx-4 text-center">
        <div class="flex items-center justify-center w-14 h-14 rounded-full bg-primary-100 mx-auto mb-4">
          <svg class="w-7 h-7 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6.293-6.293a1 1 0 011.414 0l2.586 2.586a1 1 0 010 1.414L13 15l-4 1 1-4z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 19h14" />
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-slate-800 mb-1">Set Up Your Digital Signature</h2>
        <p class="text-sm text-slate-500 mb-6">Before you continue, please upload your electronic signature and set a PIN. You'll use these to sign and approve requests in STRIDE.</p>
        <a
          :href="route('profile.signature')"
          class="block w-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors text-center"
        >
          Set Up Now
        </a>
      </div>
    </div>
  </Teleport>

</template>





