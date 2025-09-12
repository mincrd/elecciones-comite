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
import Tag from 'primevue/tag';
import ProgressSpinner from 'primevue/progressspinner';
import Dropdown from 'primevue/dropdown';
import Dialog from 'primevue/dialog';
import Checkbox from 'primevue/checkbox';

const toast = useToast();
const confirm = useConfirm();

// --------- Estado ---------
const votos = ref([]);
const isLoading = ref(true);

const search = ref('');
const estado = ref('todos'); // todos | completo | incompleto
const estadoOptions = [
  { label: 'Todos', value: 'todos' },
  { label: 'Completos', value: 'completo' },
  { label: 'Incompletos', value: 'incompleto' },
];

const rows = ref(10);
const totalRecords = ref(0);
const page = ref(1);   // 1-based Laravel
const first = ref(0);  // 0-based DataTable

// Stats
const stats = ref({ totales: 0, completos: 0, incompletos: 0 });

// Modales
const showDetailModal = ref(false);
const detail = ref(null);

const showAnularCedulaModal = ref(false);
const anularCedulaForm = ref({ cedula: '', all: false });

// --------- Helpers ---------
function parseLaravelIndexPayload(data) {
  if (Array.isArray(data)) return { items: data, total: data.length };
  if (data && Array.isArray(data.data)) {
    return { items: data.data, total: Number(data.total ?? data.data.length) };
  }
  return { items: [], total: 0 };
}

const estadoSeverity = (isCompleto) => (isCompleto ? 'success' : 'warning');
const estadoLabel = (isCompleto) => (isCompleto ? 'Completo' : 'Incompleto');

// --------- API ---------
async function fetchVotos() {
  isLoading.value = true;
  try {
    const { data } = await apiClient.get('/admin/votos', {
      params: {
        per_page: rows.value,
        page: page.value,
        search: search.value || undefined,
        estado: estado.value || 'todos',
      }
    });
    const { items, total } = parseLaravelIndexPayload(data);
    votos.value = items;
    totalRecords.value = total;
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el listado de votos.', life: 3000 });
  } finally {
    isLoading.value = false;
  }
}

async function fetchStats() {
  try {
    const { data } = await apiClient.get('/admin/votos/stats');
    stats.value = {
      totales: data?.totales ?? 0,
      completos: data?.completos ?? 0,
      incompletos: data?.incompletos ?? 0,
    };
  } catch (e) {
    // silencioso
  }
}

async function openDetail(row) {
  try {
    const { data } = await apiClient.get(`/admin/votos/${row.id}`);
    // data NO debe contener info de postulante; se usa solo para mostrar estado
    detail.value = {
      registro: data?.registro ?? null,
      empleado: data?.empleado ?? null,
      completo: !!data?.completo,
    };
    showDetailModal.value = true;
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el detalle.', life: 3000 });
  }
}

function confirmAnularRegistro(row) {
  const isCompleto = !!Number(row.completo);
  if (isCompleto) {
    toast.add({ severity: 'warn', summary: 'No permitido', detail: 'Solo puede anular votos incompletos.', life: 3000 });
    return;
  }

  confirm.require({
    message: `¿Anular el registro incompleto de la cédula ${row.cedula}?`,
    header: 'Confirmar anulación',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Sí, anular',
    rejectLabel: 'Cancelar',
    accept: async () => {
      try {
        const resp = await apiClient.post('/admin/votos/anular-incompleto', {
          registro_voto_id: row.id
        });
        toast.add({ severity: 'success', summary: 'Anulado', detail: resp?.data?.mensaje || 'Registro anulado.', life: 2500 });
        await Promise.all([fetchVotos(), fetchStats()]);
      } catch (e) {
        const msg = e?.response?.data?.message || 'No se pudo anular el registro.';
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 });
      }
    },
  });
}

function openAnularPorCedula() {
  anularCedulaForm.value = { cedula: '', all: false };
  showAnularCedulaModal.value = true;
}

async function anularPorCedula() {
  if (!anularCedulaForm.value.cedula) {
    toast.add({ severity: 'warn', summary: 'Datos faltantes', detail: 'Ingrese una cédula.', life: 2500 });
    return;
  }
  try {
    const resp = await apiClient.post('/admin/votos/anular-incompleto', {
      cedula: anularCedulaForm.value.cedula,
      all: !!anularCedulaForm.value.all,
    });
    toast.add({ severity: 'success', summary: 'Anulado', detail: resp?.data?.mensaje || 'Registros anulados.', life: 2500 });
    showAnularCedulaModal.value = false;
    await Promise.all([fetchVotos(), fetchStats()]);
  } catch (e) {
    const msg = e?.response?.data?.message || 'No se pudo anular.';
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 });
  }
}

// --------- DataTable events ---------
function onPageChange(evt) {
  first.value = evt.first;
  rows.value = evt.rows;
  page.value = Math.floor(evt.first / evt.rows) + 1;
  fetchVotos();
}

function doSearch() {
  page.value = 1;
  first.value = 0;
  fetchVotos();
}

function changeEstado() {
  page.value = 1;
  first.value = 0;
  fetchVotos();
}

function refreshAll() {
  fetchStats();
  fetchVotos();
}

onMounted(async () => {
  await Promise.all([fetchStats(), fetchVotos()]);
});
</script>

<template>
  <div class="space-y-6">
    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <Card>
        <template #title>Total registros</template>
        <template #content>
          <div class="text-3xl font-bold">{{ stats.totales }}</div>
          <div class="text-gray-500 text-sm">RegistroVoto en base</div>
        </template>
      </Card>
      <Card>
        <template #title>Completos</template>
        <template #content>
          <div class="text-3xl font-bold">{{ stats.completos }}</div>
          <div class="text-gray-500 text-sm">Con voto asociado</div>
        </template>
      </Card>
      <Card>
        <template #title>Incompletos</template>
        <template #content>
          <div class="text-3xl font-bold">{{ stats.incompletos }}</div>
          <div class="text-gray-500 text-sm">Sin voto asociado</div>
        </template>
      </Card>
    </div>

    <Card class="bg-white shadow-sm border-0">
      <template #title>
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold text-gray-900">Gestión de Votos</h2>
        </div>
      </template>

      <template #content>
        <Toolbar class="mb-4 bg-gray-50 border-gray-200 rounded-lg">
          <template #start>
            <div class="flex items-center gap-2">
              <InputText v-model="search" placeholder="Buscar por cédula, nombre, cargo..." @keyup.enter="doSearch" />
              <Dropdown v-model="estado" :options="estadoOptions" optionLabel="label" optionValue="value" class="w-44" @change="changeEstado" />
              <Button icon="pi pi-search" label="Buscar" class="p-button-secondary" @click="doSearch" />
              <Button icon="pi pi-refresh" label="Actualizar" class="p-button-text" @click="refreshAll" />
            </div>
          </template>
          <template #end>
            <Button icon="pi pi-user-minus" label="Anular por cédula" class="p-button-danger" @click="openAnularPorCedula" />
          </template>
        </Toolbar>

        <div v-if="isLoading" class="flex justify-center items-center py-10">
          <ProgressSpinner />
        </div>

        <DataTable
          v-else
          :value="votos"
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
          <Column field="id" header="ID" style="width: 80px" sortable />
          <Column field="cedula" header="Cédula" sortable />
          <Column field="nombre_completo" header="Nombre" sortable />
          <Column field="emp_grupo_ocupacional" header="Grupo" sortable />
          <Column field="emp_cargo" header="Cargo" sortable />
          <Column field="emp_lugar" header="Lugar" sortable />
          <Column field="created_at" header="Fecha/Hora" sortable>
            <template #body="{ data }">{{ new Date(data.created_at).toLocaleString() }}</template>
          </Column>
          <Column header="Estado" field="completo" sortable>
            <template #body="{ data }">
              <Tag :value="estadoLabel(!!Number(data.completo))" :severity="estadoSeverity(!!Number(data.completo))" rounded />
            </template>
          </Column>
          <Column header="Acciones" style="width: 220px">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button icon="pi pi-search" class="p-button-text p-button-sm" v-tooltip.top="'Detalle'" @click="openDetail(data)" />
                <Button
                  icon="pi pi-undo"
                  class="p-button-text p-button-sm p-button-danger"
                  :disabled="!!Number(data.completo)"
                  v-tooltip.top="'Anular (solo incompletos)'"
                  @click="confirmAnularRegistro(data)"
                />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="text-center py-8">
              <i class="pi pi-database text-4xl text-gray-300 mb-2"></i>
              <p class="text-gray-500">No hay registros.</p>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Modal Detalle (sin revelar a quién votó) -->
    <Dialog v-model:visible="showDetailModal" header="Detalle de registro" modal class="w-full max-w-2xl">
      <div v-if="detail" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <div class="text-xs text-gray-500">Cédula</div>
            <div class="font-medium">{{ detail.empleado?.cedula ?? detail.registro?.cedula }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Nombre</div>
            <div class="font-medium">{{ detail.empleado?.nombre_completo ?? '—' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Grupo</div>
            <div class="font-medium">{{ detail.empleado?.grupo_ocupacional ?? detail.registro?.grupo_ocupacional ?? '—' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Cargo</div>
            <div class="font-medium">{{ detail.empleado?.cargo ?? '—' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Lugar</div>
            <div class="font-medium">{{ detail.empleado?.lugar_de_funciones ?? '—' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Fecha/Hora</div>
            <div class="font-medium">{{ new Date(detail.registro?.created_at).toLocaleString() }}</div>
          </div>
        </div>

        <div class="pt-2">
          <Tag :value="detail.completo ? 'Completo' : 'Incompleto'" :severity="detail.completo ? 'success' : 'warning'" rounded />
        </div>

        <div v-if="detail.completo" class="mt-2 p-3 rounded-lg bg-green-50 text-green-900">
          Voto registrado. <b>(Detalles del voto ocultos por secreto del sufragio)</b>
        </div>
        <div v-else class="mt-2 p-3 rounded-lg bg-yellow-50 text-yellow-900">
          Aún no existe voto asociado (incompleto).
        </div>
      </div>
      <template #footer>
        <Button label="Cerrar" class="p-button-text" icon="pi pi-times" @click="showDetailModal = false" />
      </template>
    </Dialog>

    <!-- Modal Anular por Cédula -->
    <Dialog v-model:visible="showAnularCedulaModal" header="Anular por cédula" modal class="w-full max-w-md">
      <div class="space-y-3">
        <div>
          <label class="block text-sm text-gray-600 mb-1">Cédula</label>
          <InputText v-model.trim="anularCedulaForm.cedula" class="w-full" placeholder="Digite la cédula" />
        </div>
        <div class="flex items-center gap-2">
          <Checkbox v-model="anularCedulaForm.all" :binary="true" inputId="anularAll" />
          <label for="anularAll">Anular todos los registros incompletos de esta cédula</label>
        </div>
        <p class="text-sm text-gray-500">
          Si no marcas la opción, solo se anulará el <b>último</b> registro incompleto encontrado.
        </p>
      </div>
      <template #footer>
        <Button label="Cancelar" class="p-button-text" icon="pi pi-times" @click="showAnularCedulaModal = false" />
        <Button label="Anular" class="p-button-danger" icon="pi pi-undo" @click="anularPorCedula" />
      </template>
    </Dialog>
  </div>
</template>

