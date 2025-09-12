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
import Calendar from 'primevue/calendar';
import ProgressSpinner from 'primevue/progressspinner';
import Toolbar from 'primevue/toolbar';
import Tag from 'primevue/tag';
import SplitButton from 'primevue/splitbutton';
import Textarea from 'primevue/textarea';

const toast = useToast();
const confirm = useConfirm();

const procesos = ref([]);
const logs = ref([]);
const isLoading = ref(true);

const showProcesoModal = ref(false);
const isEditing = ref(false);
const procesoForm = ref({});

const showChangeStateModal = ref(false);
const stateChangeData = ref({});

const showLogsModal = ref(false);
const selectedProcesoForLogs = ref(null);

// ====== Estados y normalizador (tolerante a mayúsculas/minúsculas) ======
const ESTADOS = {
  NUEVO: 'Nuevo',
  ABIERTO: 'Abierto',
  CERRADO: 'Cerrado',
  CONCLUIDO: 'Concluido',
  CANCELADO: 'Cancelado',
};

const normalizeEstado = (s) => {
  const t = String(s ?? '').trim().toLowerCase();
  if (t.startsWith('nuev')) return ESTADOS.NUEVO;
  if (t.startsWith('abier')) return ESTADOS.ABIERTO;
  if (t.startsWith('cerr')) return ESTADOS.CERRADO;
  if (t.startsWith('conclu')) return ESTADOS.CONCLUIDO;
  if (t.startsWith('cancel')) return ESTADOS.CANCELADO;
  return String(s ?? '');
};

// ====== helpers fechas ======
const toYMD = (v) => {
  if (!v) return null;
  const d = v instanceof Date ? v : new Date(v);
  if (isNaN(d)) return null;
  const iso = d.toISOString();
  return iso.slice(0, 10);
};
const toDateOrNull = (v) => {
  if (!v) return null;
  const d = v instanceof Date ? v : new Date(v);
  return isNaN(d) ? null : d;
};

// ====== API ======
const fetchProcesos = async () => {
  isLoading.value = true;
  try {
    const { data } = await apiClient.get('/admin/procesos');
    procesos.value = Array.isArray(data) ? data : (data?.data ?? []);
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los procesos.', life: 3000 });
  } finally {
    isLoading.value = false;
  }
};

const saveProceso = async () => {
  const url = isEditing.value ? `/admin/procesos/${procesoForm.value.id}` : '/admin/procesos';
  const method = isEditing.value ? 'put' : 'post';
  const actionText = isEditing.value ? 'actualizado' : 'creado';

  const payload = {
    ...procesoForm.value,
    ano: Number(procesoForm.value.ano),
    desde: toYMD(procesoForm.value.desde),
    hasta: toYMD(procesoForm.value.hasta),
  };

  try {
    await apiClient[method](url, payload);
    toast.add({ severity: 'success', summary: 'Éxito', detail: `Proceso ${actionText} correctamente.`, life: 3000 });
    showProcesoModal.value = false;
    await fetchProcesos();
  } catch (error) {
    const detail =
      error?.response?.data?.message ||
      (error?.response?.data?.errors ? Object.values(error.response.data.errors).flat().join(' ') : 'Ocurrió un error al guardar.');
    toast.add({ severity: 'error', summary: 'Error de Validación', detail, life: 5000 });
  }
};

const handleChangeState = async () => {
  try {
    const payload = {
      estado: stateChangeData.value.nuevoEstado,
    };
    if (stateChangeData.value.nuevoEstado === 'Cancelado') {
      payload.descripcion = String(stateChangeData.value.descripcion ?? '');
    }

    await apiClient.post(
      `/admin/procesos/${stateChangeData.value.proceso.id}/cambiar-estado`,
      payload
    );

    toast.add({
      severity: 'info',
      summary: 'Estado Actualizado',
      detail: `El proceso ahora está ${stateChangeData.value.nuevoEstado}.`,
      life: 3000,
    });
    showChangeStateModal.value = false;
    await fetchProcesos();
  } catch (error) {
    const detail =
      error?.response?.data?.message ||
      (error?.response?.data?.errors
        ? JSON.stringify(error.response.data.errors)
        : 'No se pudo cambiar el estado.');
    toast.add({ severity: 'error', summary: 'Error', detail, life: 5000 });
  }
};


const fetchLogs = async (proceso) => {
  selectedProcesoForLogs.value = proceso;
  logs.value = [];
  showLogsModal.value = true;
  try {
    // Cuando exista el endpoint real, reemplazar el mock:
    // const { data } = await apiClient.get(`/admin/procesos/${proceso.id}/logs`);
    // logs.value = Array.isArray(data) ? data : (data?.data ?? []);
    logs.value = [
      { id: 1, created_at: new Date().toISOString(), accion: 'CREACION_PROCESO', descripcion: `Se creó el proceso para el año ${proceso.ano}.`, user: { name: 'Admin User' } },
      { id: 2, created_at: new Date().toISOString(), accion: 'CAMBIO_ESTADO_PROCESO', descripcion: "El proceso cambió de 'Nuevo' a 'Abierto'.", user: { name: 'Admin User' } }
    ];
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el historial.', life: 3000 });
    showLogsModal.value = false;
  }
};

// ====== UI ======
const openNewModal = () => {
  isEditing.value = false;
  procesoForm.value = {
    ano: new Date().getFullYear(),
    desde: null,
    hasta: null,
  };
  showProcesoModal.value = true;
};

const openEditModal = (proceso) => {
  isEditing.value = true;
  procesoForm.value = {
    ...proceso,
    desde: toDateOrNull(proceso.desde),
    hasta: toDateOrNull(proceso.hasta),
  };
  showProcesoModal.value = true;
};

const confirmChangeState = (proceso, nuevoEstado) => {
  stateChangeData.value = { proceso, nuevoEstado, descripcion: '' };
  if (nuevoEstado === ESTADOS.CANCELADO) {
    showChangeStateModal.value = true;
  } else {
    confirm.require({
      message: `¿Estás seguro de que quieres cambiar el estado del proceso a "${nuevoEstado}"?`,
      header: 'Confirmación de Cambio de Estado',
      icon: 'pi pi-info-circle',
      acceptLabel: 'Sí, cambiar',
      rejectLabel: 'Cancelar',
      accept: handleChangeState,
    });
  }
};

const getSeverityForTag = (estado) => ({
  [ESTADOS.NUEVO]: 'info',
  [ESTADOS.ABIERTO]: 'success',
  [ESTADOS.CERRADO]: 'warning',
  [ESTADOS.CONCLUIDO]: 'primary',
  [ESTADOS.CANCELADO]: 'danger',
}[normalizeEstado(estado)] || 'secondary');

const getPossibleActions = (proceso) => {
  const estado = normalizeEstado(proceso.estado);
  const actions = [];
  switch (estado) {
    case ESTADOS.NUEVO:
      actions.push({ label: 'Abrir Proceso',         command: () => confirmChangeState(proceso, ESTADOS.ABIERTO) });
      actions.push({ label: 'Cancelar',               command: () => confirmChangeState(proceso, ESTADOS.CANCELADO) });
      break;
    case ESTADOS.ABIERTO:
      actions.push({ label: 'Cerrar Votación',        command: () => confirmChangeState(proceso, ESTADOS.CERRADO) });
      actions.push({ label: 'Cancelar',               command: () => confirmChangeState(proceso, ESTADOS.CANCELADO) });
      break;
    case ESTADOS.CERRADO:
      actions.push({ label: 'Marcar como Concluido',  command: () => confirmChangeState(proceso, ESTADOS.CONCLUIDO) });
      actions.push({ label: 'Cancelar',               command: () => confirmChangeState(proceso, ESTADOS.CANCELADO) });
      break;
  }
  return actions;
};

onMounted(fetchProcesos);
</script>

<template>
  <Card>
    <template #title>Gestión de Procesos Electorales</template>
    <template #content>
      <Toolbar class="mb-4">
        <template #start>
          <Button label="Nuevo Proceso" icon="pi pi-plus" class="p-button-primary" @click="openNewModal" />
        </template>
      </Toolbar>

      <div v-if="isLoading" class="flex justify-center items-center py-8">
        <ProgressSpinner />
      </div>

      <DataTable v-else :value="procesos" responsiveLayout="scroll">
        <Column field="ano" header="Año" sortable />
        <Column header="Periodo de Votación">
          <template #body="{ data }">
            {{ new Date(data.desde).toLocaleDateString() }} - {{ new Date(data.hasta).toLocaleDateString() }}
          </template>
        </Column>
        <Column field="estado" header="Estado" sortable>
          <template #body="{ data }">
            <Tag :severity="getSeverityForTag(data.estado)" :value="normalizeEstado(data.estado)" rounded />
          </template>
        </Column>
        <Column header="Acciones" style="width: 260px">
          <template #body="{ data }">
            <div class="flex gap-2">
              <Button icon="pi pi-pencil" class="p-button-text" @click="openEditModal(data)" v-tooltip.top="'Editar Fechas'" />
              <Button icon="pi pi-history" class="p-button-text p-button-info" @click="fetchLogs(data)" v-tooltip.top="'Ver Historial'" />
              <SplitButton
                v-if="getPossibleActions(data).length > 0"
                label="Cambiar Estado"
                :model="getPossibleActions(data)"
                class="p-button-sm p-button-raised p-button-secondary"
                icon="pi pi-cog"
              />
            </div>
          </template>
        </Column>
        <template #empty>
          <div class="text-center py-4">No se encontraron procesos.</div>
        </template>
      </DataTable>
    </template>
  </Card>

  <!-- Modal Crear/Editar -->
  <Dialog v-model:visible="showProcesoModal" :header="isEditing ? 'Editar Proceso' : 'Nuevo Proceso'" modal class="w-full max-w-lg">
    <div class="p-fluid space-y-4 pt-4">
      <div>
        <label for="ano">Año del Proceso</label>
        <InputText id="ano" v-model.number="procesoForm.ano" type="number" />
      </div>
      <div>
        <label for="desde">Fecha de Inicio</label>
        <Calendar id="desde" v-model="procesoForm.desde" dateFormat="yy-mm-dd" />
      </div>
      <div>
        <label for="hasta">Fecha de Cierre</label>
        <Calendar id="hasta" v-model="procesoForm.hasta" dateFormat="yy-mm-dd" />
      </div>
    </div>
    <template #footer>
      <Button label="Cancelar" icon="pi pi-times" class="p-button-text" @click="showProcesoModal = false" />
      <Button label="Guardar" icon="pi pi-check" @click="saveProceso" />
    </template>
  </Dialog>

  <!-- Modal Cancelación -->
  <Dialog v-model:visible="showChangeStateModal" header="Cancelar Proceso" modal class="w-full max-w-lg">
    <div class="p-fluid space-y-2 pt-4">
      <p>Para cancelar el proceso, por favor provea una justificación clara.</p>
      <Textarea v-model="stateChangeData.descripcion" rows="5" class="w-full" placeholder="Escriba la razón de la cancelación..." />
    </div>
    <template #footer>
      <Button label="Volver" icon="pi pi-times" class="p-button-text" @click="showChangeStateModal = false" />
      <Button label="Confirmar Cancelación" icon="pi pi-check" class="p-button-danger" @click="handleChangeState" />
    </template>
  </Dialog>

  <!-- Modal Logs -->
  <Dialog v-model:visible="showLogsModal" :header="`Historial del Proceso ${selectedProcesoForLogs?.ano ?? ''}`" modal class="w-full max-w-2xl">
    <DataTable :value="logs">
      <Column field="created_at" header="Fecha y Hora">
        <template #body="{ data }">{{ new Date(data.created_at).toLocaleString() }}</template>
      </Column>
      <Column field="user.name" header="Usuario" />
      <Column field="accion" header="Acción" />
      <Column field="descripcion" header="Descripción" />
    </DataTable>
  </Dialog>
</template>
