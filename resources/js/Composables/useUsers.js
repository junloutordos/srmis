import { ref, computed, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useUsers(props) {
  const usersList = ref(props.users || [])
  const rolesList = ref(props.roles || [])
  const divisionsList = ref(props.divisions || []) // ✅ make it reactive
  const officesList = ref(props.offices || [])

  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedUser = ref(null)
  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  // usersList is a plain ref (not a computed over props.users) because rows
  // are also mutated in place after delete/activate. Without this watcher,
  // changing the status filter triggers a fresh Inertia fetch but the table
  // keeps rendering whatever list was present on the very first page load.
  watch(() => props.users, (newUsers) => {
    usersList.value = newUsers || []
    currentPage.value = 1
  })

  // Form
  const form = ref({
    id: null,
    name: "",
    email: "",
    sex:"",
    badge_id: "",
    role_id: [],
    position: "",
    specialization: "",
    division_id: "",
    office_id: "",
    emp_category: '',
  })

  const isEmployeesPage = !!(props.pageTitle && String(props.pageTitle).toLowerCase().includes('employee'))
  const statusFilter = ref(props.filters?.status ?? 'active')
  const isInactivePage = computed(() => statusFilter.value === 'inactive')

  // Filtered + paginated users (backend already scopes by status; this only
  // applies the free-text search on top of the current status-scoped list)
  const filteredUsersAll = computed(() => {
    const q = searchQuery.value.toLowerCase()
    return usersList.value.filter((u) => {
      // resolve role names for searching
      const roleNames = Array.isArray(u.roles)
        ? u.roles.map(r => (r.name || '')).join(' ')
        : (u.role?.name || '')
      return (
        (u.name || '').toLowerCase().includes(q) ||
        (u.email || '').toLowerCase().includes(q) ||
        (u.badge_id || '').toString().toLowerCase().includes(q) ||
        (u.position || '').toLowerCase().includes(q) ||
        (u.division?.division_name || '').toLowerCase().includes(q) ||
        (u.office?.name || '').toLowerCase().includes(q) ||
        (u.emp_category || '').toLowerCase().includes(q) ||
        roleNames.toLowerCase().includes(q)
      )
    })
  })

  const filteredUsers = computed(() => {
    const start = (currentPage.value - 1) * perPage
    return filteredUsersAll.value.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredUsersAll.value.length / perPage))
  )

  // Modal logic
  const openModal = (mode, user = null) => {
    modalMode.value = mode
    showModal.value = true
    if (mode === "edit" && user) {
      form.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        sex: user.sex,
        badge_id: user.badge_id ?? "",
        employee_no: user.employee_no ?? "",
        role_id: Array.isArray(user.role_id)
          ? user.role_id
          : user.role_id
          ? user.role_id.toString().split(',').map((s) => Number(s.trim()))
          : [],
        position: user.position ?? "",
        specialization: user.specialization ?? "",
        division_id: user.division_id ?? "",
        office_id: user.office_id ?? user.office?.id ?? "",
        emp_category: user.emp_category ?? '',
        status: user.status ?? 'active',
      }
    } else {
      form.value = {
        id: null,
        name: "",
        email: "",
        badge_id: "",
        role_id: [],
        position: "",
        specialization: "",
        division_id: "",
        office_id: "",
        emp_category: '',
      }
    }
    selectedUser.value = user
  }

  const closeModal = () => {
    showModal.value = false
    selectedUser.value = null
  }

  const viewUser = (user) => {
    modalMode.value = "view"
    selectedUser.value = user
    showModal.value = true
  }

  // Submit
  const submitUser = async () => {
    if (modalMode.value === "create") {
      const payload = { ...form.value }
      if (Array.isArray(payload.role_id)) payload.role_id = payload.role_id.join(',')

      // If this is the HR Employees page, send to the HR endpoint and strip admin-only fields
      if (isEmployeesPage) {
        // remove admin-only fields
        delete payload.email
        delete payload.badge_id
        delete payload.role_id
        router.post("/hr/employees", payload, {
          onSuccess: async () => {
            closeModal()
            await Swal.fire("Success", "The employee has been added successfully", "success")
            window.location.reload()
          },
          onError: async (errors) => {
            await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
          },
        })
        return
      }

      router.post("/users", payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Success", "The user has been added successfully", "success")
          window.location.reload()
        },
        onError: async (errors) => {
          await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
        },
      })
    } else if (modalMode.value === "edit") {
      const payload = { ...form.value }
      if (Array.isArray(payload.role_id)) payload.role_id = payload.role_id.join(',')

      router.put(`/users/${form.value.id}`, payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Updated", "The user has been updated successfully", "success")
          window.location.reload()
        },
        onError: async (errors) => {
          await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
        },
      })
    }
  }

  // Delete
  const deleteUser = async (user) => {
    const result = await Swal.fire({
      title: `Delete ${user.name}?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
      cancelButtonText: "Cancel",
    })
    if (result.isConfirmed) {
      router.delete(`/users/${user.id}`, {
        onSuccess: async () => {
          if (statusFilter.value === 'all') {
            const target = usersList.value.find((u) => u.id === user.id)
            if (target) target.status = 'inactive'
          } else {
            usersList.value = usersList.value.filter((u) => u.id !== user.id)
          }
          await Swal.fire("Deleted", "User has been deleted", "success")
          closeModal()
        },
        onError: async (errors) => {
          await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
        },
      })
    }
  }

  // Send password reset link to user's email
  const sendPasswordReset = async (user) => {
    const result = await Swal.fire({
      title: `Send password reset to ${user.name}?`,
      text: `A reset link will be emailed to ${user.email}.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, send link',
      cancelButtonText: 'Cancel',
    })
    if (result.isConfirmed) {
      router.post(`/users/${user.id}/send-password-reset`, null, {
        onSuccess: async () => {
          await Swal.fire('Sent', `Password reset link sent to ${user.email}`, 'success')
        },
        onError: async (errors) => {
          await Swal.fire('Error', Object.values(errors).flat().join(', '), 'error')
        }
      })
    }
  }

  // Activate (reactivate) user
  const activateUser = async (user) => {
    const result = await Swal.fire({
      title: `Activate ${user.name}?`,
      text: 'This will return the user to active status.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, activate',
      cancelButtonText: 'Cancel',
    })
    if (result.isConfirmed) {
      router.post(`/users/${user.id}/activate`, null, {
        onSuccess: async () => {
          if (statusFilter.value === 'inactive') {
            usersList.value = usersList.value.filter((u) => u.id !== user.id)
          } else {
            const target = usersList.value.find((u) => u.id === user.id)
            if (target) target.status = 'active'
          }
          await Swal.fire('Activated', 'User has been reactivated', 'success')
        },
        onError: async (errors) => {
          await Swal.fire('Error', Object.values(errors).flat().join(', '), 'error')
        }
      })
    }
  }

  return {
    usersList,
    rolesList,
    divisionsList, // ✅ return it
    officesList,
    showModal,
    modalMode,
    selectedUser,
    searchQuery,
    currentPage,
    totalPages,
    filteredUsers,
    form,
    openModal,
    closeModal,
    submitUser,
    viewUser,
    deleteUser,
    activateUser,
    sendPasswordReset,
    isEmployeesPage,
    isInactivePage,
    statusFilter,
  }
}
