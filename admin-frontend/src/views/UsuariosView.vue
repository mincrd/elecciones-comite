<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '../api/axios';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

// PrimeVue
import Card from 'primevue/card';
import Toolbar from 'primevue/toolbar';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import ProgressSpinner from 'primevue/progressspinner';
import Dropdown from 'primevue/dropdown';

const toast = useToast();
const confirm = useConfirm();

// --- Estado ---
const usuarios = ref([]);
const isLoading = ref(true);

const search = ref('');
const rows = ref(10);
const totalRecords = ref(0);
const page = ref(1);            // 1-based para Laravel
const first = ref(0);           // 0-based para DataTable

const showUserModal = ref(false);
const isEditing = ref(false);
const userForm = ref({
  id: null,
  name: '',
  email: '',
  rol: 'supervisor',
  // En crear/editar usamos este modal; la contraseña se maneja en otro modal
});

const showPwdModal = ref(false);
const pwdForm = ref({
  id: null,
  password: '',
  password_confirmation: '',
});

const roles = [
  { label: 'Administrador', value: 'admin' },
  { label: 'Supervisor', value: 'supervisor' },
];

// --- Helpers ---
const roleSeverity = (rol) => (rol === 'admin' ? 'primary' : 'info');

function parseLaravelIndexPayload(data) {
  // Soporta: array plano o respuesta paginada de Laravel
  if (Array.isArray(data)) {
    return { items: data, total: data.length };
  }
  if (data && Array.isArray(data.data)) {
    return { items: data.data, total: Number(data.total ?? data.data.length) };
  }
  return { items: [], total: 0 };
}

// --- API ---
async function fetchUsuarios() {
  isLoading.value = true;
  try {
    const { data } = await apiClient.get('/admin/usuarios', {
      params: {
        per_page: rows.value,
        page: page.value,
        search: search.value || undefined,
      },
    });
    const { items, total } = parseLaravelIndexPayload(data);
    usuarios.value = items;
    totalRecords.value = total;
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los usuarios.', life: 3000 });
  } finally {
    isLoading.value = false;
  }
}

async function saveUsuario() {
  try {
    const payload = {
      name: userForm.value.name,
      email: userForm.value.email,
      rol: userForm.value.rol,
    };

    if (isEditing.value) {
      await apiClient.put(`/admin/usuarios/${userForm.value.id}`, payload);
      toast.add({ severity: 'success', summary: 'Actualizado', detail: 'Usuario actualizado correctamente.', life: 2500 });
    } else {
      // Para crear exige password + confirmation
      const pwd = prompt('Asigne una contraseña inicial (mínimo 8 caracteres):');
      if (!pwd || pwd.length < 8) {
        toast.add({ severity: 'warn', summary: 'Cancelado', detail: 'No se creó el usuario (contraseña inválida).', life: 2500 });
        return;
      }
      await apiClient.post('/admin/usuarios', {
        ...payload,
        password: pwd,
        password_confirmation: pwd,
      });
      toast.add({ severity: 'success', summary: 'Creado', detail: 'Usuario creado correctamente.', life: 2500 });
    }

    showUserModal.value = false;
    await fetchUsuarios();
  } catch (e) {
    const msg =
      e?.response?.data?.errors
        ? Object.values(e.response.data.errors).flat().join(' ')
        : e?.response?.data?.message || 'Error al guardar el usuario.';
    toast.add({ severity: 'error', summary: 'Error de validación', detail: msg, life: 5000 });
  }
}

function openNewUser() {
  isEditing.value = false;
  userForm.value = {
    id: null,
    name: '',
    email: '',
    rol: 'supervisor',
  };
  showUserModal.value = true;
}

function openEditUser(u) {
  isEditing.value = true;
  userForm.value = {
    id: u.id,
    name: u.name,
    email: u.email,
    rol: u.rol,
  };
  showUserModal.value = true;
}

function openPwdModal(u) {
  pwdForm.value = {
    id: u.id,
    password: '',
    password_confirmation: '',
  };
  showPwdModal.value = true;
}

async function changePassword() {
  try {
    if (!pwdForm.value.password || pwdForm.value.password.length < 8) {
      toast.add({ severity: 'warn', summary: 'Inválido', detail: 'La contraseña debe tener al menos 8 caracteres.', life: 2500 });
      return;
    }
    if (pwdForm.value.password !== pwdForm.value.password_confirmation) {
      toast.add({ severity: 'warn', summary: 'Inválido', detail: 'Las contraseñas no coinciden.', life: 2500 });
      return;
    }
    await apiClient.post(`/admin/usuarios/${pwdForm.value.id}/password`, {
      password: pwdForm.value.password,
      password_confirmation: pwdForm.value.password_confirmation,
    });
    toast.add({ severity: 'success', summary: 'Listo', detail: 'Contraseña actualizada.', life: 2500 });
    showPwdModal.value = false;
  } catch (e) {
    const msg =
      e?.response?.data?.errors
        ? Object.values(e.response.data.errors).flat().join(' ')
        : e?.response?.data?.message || 'No se pudo actualizar la contraseña.';
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 5000 });
  }
}

function confirmDelete(u) {
  confirm.require({
    message: `¿Eliminar al usuario "${u.name}"?`,
    header: 'Confirmación',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Sí, eliminar',
    rejectLabel: 'Cancelar',
    accept: async () => {
      try {
        await apiClient.delete(`/admin/usuarios/${u.id}`);
        toast.add({ severity: 'warn', summary: 'Eliminado', detail: 'Usuario eliminado.', life: 2500 });
        // si la página se queda vacía tras borrar, retrocede una página
        if (usuarios.value.length === 1 && page.value > 1) {
          page.value -= 1;
          first.value = (page.value - 1) * rows.value;
        }
        await fetchUsuarios();
      } catch (e) {
        const msg = e?.response?.data?.message || 'No se pudo eliminar el usuario.';
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 });
      }
    },
  });
}

// --- Eventos DataTable (paginación server-side) ---
function onPageChange(evt) {
  // evt: { first, rows, page, pageCount }
  first.value = evt.first;
  rows.value = evt.rows;
  page.value = Math.floor(evt.first / evt.rows) + 1;
  fetchUsuarios();
}

function doSearch() {
  page.value = 1;
  first.value = 0;
  fetchUsuarios();
}

function clearSearch() {
  search.value = '';
  page.value = 1;
  first.value = 0;
  fetchUsuarios();
}

onMounted(fetchUsuarios);
</script>

<template>
  <Card class="bg-white shadow-sm border-0">
    <template #title>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900">Gestión de Usuarios</h2>
      </div>
    </template>

    <template #content>
      <Toolbar class="mb-4 bg-gray-50 border-gray-200 rounded-lg">
        <template #start>
          <div class="flex items-center gap-2">
            <InputText v-model="search" placeholder="Buscar por nombre, email o rol" @keyup.enter="doSearch" />
            <Button icon="pi pi-search" label="Buscar" class="p-button-secondary" @click="doSearch" />
            <Button icon="pi pi-times" label="Limpiar" class="p-button-text" @click="clearSearch" />
          </div>
        </template>
        <template #end>
          <Button label="Nuevo Usuario" icon="pi pi-user-plus" class="p-button-primary" @click="openNewUser" />
        </template>
      </Toolbar>

      <div v-if="isLoading" class="flex justify-center items-center py-10">
        <ProgressSpinner />
      </div>

      <DataTable
        v-else
        :value="usuarios"
        :paginator="true"
        :rows="rows"
        :first="first"
        :totalRecords="totalRecords"
        lazy
        @page="onPageChange"
        responsiveLayout="scroll"
        class="p-datatable-sm"
        stripedRows
      >
        <Column field="id" header="ID" style="width: 80px" sortable />
        <Column field="name" header="Nombre" sortable />
        <Column field="email" header="Email" sortable />
        <Column field="rol" header="Rol" sortable>
          <template #body="{ data }">
            <Tag :value="data.rol" :severity="roleSeverity(data.rol)" rounded />
          </template>
        </Column>
        <Column header="Acciones" style="width: 260px">
          <template #body="{ data }">
            <div class="flex gap-2">
              <Button icon="pi pi-pencil" class="p-button-text p-button-sm" v-tooltip.top="'Editar'"
                      @click="openEditUser(data)" />
              <Button icon="pi pi-key" class="p-button-text p-button-sm p-button-secondary" v-tooltip.top="'Cambiar contraseña'"
                      @click="openPwdModal(data)" />
              <Button icon="pi pi-trash" class="p-button-text p-button-sm p-button-danger" v-tooltip.top="'Eliminar'"
                      @click="confirmDelete(data)" />
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="text-center py-10">
            <i class="pi pi-users text-4xl text-gray-300 mb-2"></i>
            <p class="text-gray-500">No hay usuarios registrados.</p>
          </div>
        </template>
      </DataTable>
    </template>
  </Card>

  <!-- Modal Crear/Editar Usuario -->
  <Dialog v-model:visible="showUserModal" :header="isEditing ? 'Editar Usuario' : 'Nuevo Usuario'" modal class="w-full max-w-lg">
    <div class="p-fluid space-y-4 pt-2">
      <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
        <InputText id="name" v-model.trim="userForm.name" class="w-full" />
      </div>
      <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <InputText id="email" v-model.trim="userForm.email" type="email" class="w-full" />
      </div>
      <div>
        <label for="rol" class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
        <Dropdown id="rol" v-model="userForm.rol" :options="roles" optionLabel="label" optionValue="value" class="w-full" />
      </div>
    </div>
    <template #footer>
      <Button label="Cancelar" icon="pi pi-times" class="p-button-text" @click="showUserModal = false" />
      <Button label="Guardar" icon="pi pi-check" class="p-button-primary" @click="saveUsuario" />
    </template>
  </Dialog>

  <!-- Modal Cambiar Contraseña -->
  <Dialog v-model:visible="showPwdModal" header="Cambiar Contraseña" modal class="w-full max-w-md">
    <div class="p-fluid space-y-4 pt-2">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
        <InputText v-model.trim="pwdForm.password" type="password" class="w-full" autocomplete="new-password" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña</label>
        <InputText v-model.trim="pwdForm.password_confirmation" type="password" class="w-full" autocomplete="new-password" />
      </div>
    </div>
    <template #footer>
      <Button label="Cancelar" icon="pi pi-times" class="p-button-text" @click="showPwdModal = false" />
      <Button label="Actualizar" icon="pi pi-check" class="p-button-primary" @click="changePassword" />
    </template>
  </Dialog>
</template>
