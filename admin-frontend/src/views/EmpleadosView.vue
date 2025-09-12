<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '../api/axios';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

import Button from 'primevue/button';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Toolbar from 'primevue/toolbar';

const toast = useToast();
const confirm = useConfirm();

const empleados = ref([]);
const isLoading = ref(true);

const search = ref('');
const rows = ref(10);
const totalRecords = ref(0);
const page = ref(1);   // 1-based para Laravel
const first = ref(0);  // 0-based para DataTable

const showModal = ref(false);
const isEditing = ref(false);
const empleadoForm = ref({
  id: null,
  nombre_completo: '',
  cedula: '',
  grupo_ocupacional: '',
  cargo: '',
  lugar_de_funciones: '',
});

// Utils para payload paginado o array plano
function parseLaravelIndexPayload(data) {
  if (Array.isArray(data)) {
    return { items: data, total: data.length };
  }
  if (data && Array.isArray(data.data)) {
    return { items: data.data, total: Number(data.total ?? data.data.length) };
  }
  return { items: [], total: 0 };
}

// --- API ---
const fetchEmpleados = async () => {
  isLoading.value = true;
  try {
    const { data } = await apiClient.get('/admin/empleados-habiles', {
      params: {
        per_page: rows.value,
        page: page.value,
        search: search.value || undefined,
        // sort_by: 'id', sort_dir: 'desc' // si quieres enviar orden
      }
    });
    const { items, total } = parseLaravelIndexPayload(data);
    empleados.value = items;
    totalRecords.value = total;
  } catch (error) {
    console.error(error);
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los empleados.', life: 3000 });
  } finally {
    isLoading.value = false;
  }
};

const saveEmpleado = async () => {
  isLoading.value = true;
  try {
    if (isEditing.value) {
      await apiClient.put(`/admin/empleados-habiles/${empleadoForm.value.id}`, empleadoForm.value);
      toast.add({ severity: 'success', summary: 'Éxito', detail: 'Empleado actualizado correctamente.', life: 3000 });
    } else {
      await apiClient.post('/admin/empleados-habiles', empleadoForm.value);
      toast.add({ severity: 'success', summary: 'Éxito', detail: 'Empleado creado correctamente.', life: 3000 });
    }
    showModal.value = false;
    // si estabas en una página >1 y la página queda vacía, retrocede
    if (!isEditing.value && empleados.value.length === 0 && page.value > 1) {
      page.value -= 1;
      first.value = (page.value - 1) * rows.value;
    }
    await fetchEmpleados();
  } catch (error) {
    const messages = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(' ')
      : 'Ocurrió un error al guardar el empleado.';
    toast.add({ severity: 'error', summary: 'Error de Validación', detail: messages, life: 5000 });
  } finally {
    isLoading.value = false;
  }
};

const deleteEmpleado = (empleado) => {
  confirm.require({
    message: `¿Estás seguro de que quieres eliminar a "${empleado.nombre_completo}"?`,
    header: 'Confirmación de Eliminación',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Sí, eliminar',
    rejectLabel: 'Cancelar',
    accept: async () => {
      try {
        await apiClient.delete(`/admin/empleados-habiles/${empleado.id}`);
        toast.add({ severity: 'warn', summary: 'Eliminado', detail: 'Empleado eliminado.', life: 3000 });
        // Ajuste de página si queda vacía
        if (empleados.value.length === 1 && page.value > 1) {
          page.value -= 1;
          first.value = (page.value - 1) * rows.value;
        }
        await fetchEmpleados();
      } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar el empleado.', life: 3000 });
      }
    },
  });
};

// --- UI ---
const openNewModal = () => {
  isEditing.value = false;
  empleadoForm.value = {
    id: null,
    nombre_completo: '',
    cedula: '',
    grupo_ocupacional: '',
    cargo: '',
    lugar_de_funciones: '',
  };
  showModal.value = true;
};

const openEditModal = (empleado) => {
  isEditing.value = true;
  empleadoForm.value = { ...empleado };
  showModal.value = true;
};

// --- DataTable events ---
function onPageChange(evt) {
  // evt: { first, rows, page, pageCount }
  first.value = evt.first;
  rows.value = evt.rows;
  page.value = Math.floor(evt.first / evt.rows) + 1;
  fetchEmpleados();
}

function doSearch() {
  page.value = 1;
  first.value = 0;
  fetchEmpleados();
}

function clearSearch() {
  search.value = '';
  page.value = 1;
  first.value = 0;
  fetchEmpleados();
}

onMounted(fetchEmpleados);
</script>

<template>
  <div class="space-y-6">
    <Card class="bg-white shadow-sm border-0">
      <template #title>
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold text-gray-900">Gestión de Empleados Hábiles</h2>
        </div>
      </template>

      <template #content>
        <Toolbar class="mb-4 bg-gray-50 border-gray-200 rounded-lg">
          <template #start>
            <div class="flex items-center gap-2">
              <InputText v-model="search" placeholder="Buscar por nombre, cédula, cargo..." @keyup.enter="doSearch" />
              <Button icon="pi pi-search" label="Buscar" class="p-button-secondary" @click="doSearch" />
              <Button icon="pi pi-times" label="Limpiar" class="p-button-text" @click="clearSearch" />
            </div>
          </template>
          <template #end>
            <Button label="Nuevo Empleado" icon="pi pi-plus" class="p-button-primary" @click="openNewModal" />
          </template>
        </Toolbar>

        <div v-if="isLoading" class="flex justify-center items-center py-8">
          <ProgressSpinner />
        </div>

        <DataTable
          v-else
          :value="empleados"
          responsiveLayout="scroll"
          class="p-datatable-sm"
          stripedRows
          :paginator="true"
          :rows="rows"
          :first="first"
          :totalRecords="totalRecords"
          lazy
          @page="onPageChange"
        >
          <Column field="id" header="Id" sortable />
          <Column field="nombre_completo" header="Nombre Completo" sortable style="min-width: 16rem" />
          <Column field="cedula" header="Cédula" sortable />
          <Column field="cargo" header="Cargo" sortable />
          <Column field="grupo_ocupacional" header="Grupo Ocupacional" sortable />
          <Column field="lugar_de_funciones" header="Lugar de Funciones" sortable />
          <Column header="Acciones" style="width: 140px">
            <template #body="slotProps">
              <div class="flex space-x-2">
                <Button icon="pi pi-pencil" class="p-button-text p-button-sm" v-tooltip="'Editar'"
                        @click="openEditModal(slotProps.data)" />
                <Button icon="pi pi-trash" class="p-button-text p-button-sm p-button-danger" v-tooltip="'Eliminar'"
                        @click="deleteEmpleado(slotProps.data)" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-8">
              <i class="pi pi-users text-4xl text-gray-300 mb-2"></i>
              <p class="text-gray-500">No hay empleados registrados.</p>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="showModal"
            :header="isEditing ? 'Editar Empleado' : 'Nuevo Empleado'"
            :modal="true"
            class="w-full max-w-2xl mx-4">
      <div class="space-y-4 p-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre Completo</label>
            <InputText id="nombre" v-model="empleadoForm.nombre_completo" class="w-full" placeholder="Nombres y Apellidos"/>
          </div>
          <div>
            <label for="cedula" class="block text-sm font-medium text-gray-700 mb-2">Cédula</label>
            <InputText id="cedula" v-model="empleadoForm.cedula" class="w-full" placeholder="Número de cédula"/>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="cargo" class="block text-sm font-medium text-gray-700 mb-2">Cargo</label>
            <InputText id="cargo" v-model="empleadoForm.cargo" class="w-full" placeholder="Posición del empleado"/>
          </div>
          <div>
            <label for="grupo" class="block text-sm font-medium text-gray-700 mb-2">Grupo Ocupacional</label>
            <InputText id="grupo" v-model="empleadoForm.grupo_ocupacional" class="w-full" placeholder="Ej: III, IV"/>
          </div>
        </div>
        <div>
          <label for="lugar" class="block text-sm font-medium text-gray-700 mb-2">Lugar de Funciones</label>
          <InputText id="lugar" v-model="empleadoForm.lugar_de_funciones" class="w-full" placeholder="Departamento o área"/>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end space-x-3 pt-4">
          <Button label="Cancelar" icon="pi pi-times" class="p-button-text" @click="showModal = false" />
          <Button label="Guardar" icon="pi pi-check" class="p-button-primary" @click="saveEmpleado" />
        </div>
      </template>
    </Dialog>
  </div>
</template>
