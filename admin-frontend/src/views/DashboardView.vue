<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { storeToRefs } from 'pinia';
import { useProcesosStore } from '../stores/procesosStore';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import apiClient from '../api/axios';

// PrimeVue
import Button from 'primevue/button';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressSpinner from 'primevue/progressspinner';
import Chart from 'primevue/chart';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';

// Store
const store = useProcesosStore();
const confirm = useConfirm();
const toast = useToast();

const {
  procesos,
  postulantes,
  selectedProceso,
  isLoading,
} = storeToRefs(store);

const { fetchProcesos, selectProceso } = store;

/* =========================
   Modales y formularios
========================= */
const showProcesoModal = ref(false);
const formProceso = ref({
  id: null,
  ano: new Date().getFullYear(),
  desde: '',
  hasta: '',
  estado: 'Cerrado',
});

const showPostulanteModal = ref(false);
const formPostulante = ref({
  id: null,
  nombre_completo: '',
  cargo: '',
  email: '',
  telefono: '',
  grupo_ocupacional: '',
  valores: [],
  // foto_url llega desde el backend si existe
});

const fotoFile = ref(null);        // File seleccionado
const fotoPreview = ref(null);     // URL.createObjectURL o foto_url existente

// Opciones para formularios
const valoresOptions = ref([
  'Colaboración', 'Compromiso', 'Confiabilidad', 'Disciplina', 'Discreción',
  'Honestidad', 'Honorabilidad', 'Honradez', 'Probidad', 'Rectitud',
  'Responsabilidad', 'Trabajo en equipo', 'Vocación al servicio',
]);
const grupoOcupacionalOptions = ref(['I', 'II', 'III', 'IV', 'V']);

const openProcesoModal = (proceso = null) => {
  formProceso.value = proceso
    ? { ...proceso, desde: new Date(proceso.desde), hasta: new Date(proceso.hasta) }
    : { id: null, ano: new Date().getFullYear(), desde: '', hasta: '', estado: 'Cerrado' };
  showProcesoModal.value = true;
};

const resetFotoPreview = () => {
  if (fotoPreview.value && fotoPreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(fotoPreview.value);
  }
  fotoPreview.value = null;
};

const openPostulanteModal = (postulante = null) => {
  resetFotoPreview();
  fotoFile.value = null;

  if (postulante) {
    formPostulante.value = { ...postulante };
    fotoPreview.value = postulante.foto_url || null;
  } else {
    formPostulante.value = {
      id: null,
      nombre_completo: '',
      cargo: '',
      email: '',
      telefono: '',
      grupo_ocupacional: '',
      valores: [],
    };
    fotoPreview.value = null;
  }
  showPostulanteModal.value = true;
};

function onFotoChange(e) {
  const file = e.target.files?.[0] ?? null;
  if (file && file.size > 2 * 1024 * 1024) {
    toast.add({ severity: 'warn', summary: 'Archivo grande', detail: 'Máximo 2MB.', life: 3000 });
    e.target.value = '';
    return;
  }
  fotoFile.value = file;
  if (file) {
    resetFotoPreview();
    fotoPreview.value = URL.createObjectURL(file);
  } else if (formPostulante.value?.foto_url) {
    fotoPreview.value = formPostulante.value.foto_url;
  } else {
    fotoPreview.value = null;
  }
}

/* =========================
   Guardado Proceso/Postulante
========================= */
const handleSaveProceso = async () => {
  const success = await store.saveProceso(formProceso.value);
  if (success) showProcesoModal.value = false;
};

const handleSavePostulante = async () => {
  if (!selectedProceso.value) {
    toast.add({ severity: 'warn', summary: 'Seleccione un proceso', detail: 'Debes seleccionar un proceso para asociar el postulante.', life: 3000 });
    return;
  }
  if (!formPostulante.value.nombre_completo?.trim()) {
    toast.add({ severity: 'warn', summary: 'Falta nombre', detail: 'El nombre completo es obligatorio.', life: 3000 });
    return;
  }
  if (!formPostulante.value.grupo_ocupacional) {
    toast.add({ severity: 'warn', summary: 'Falta grupo', detail: 'Selecciona el grupo ocupacional.', life: 3000 });
    return;
  }

  try {
    const fd = new FormData();

    if (formPostulante.value.id) {
      fd.append('_method', 'PUT');
    }

    fd.append('proceso_id', String(selectedProceso.value.id));
    fd.append('nombre_completo', formPostulante.value.nombre_completo ?? '');
    if (formPostulante.value.cargo != null) fd.append('cargo', formPostulante.value.cargo);
    if (formPostulante.value.email != null) fd.append('email', formPostulante.value.email);
    if (formPostulante.value.telefono != null) fd.append('telefono', formPostulante.value.telefono);
    fd.append('grupo_ocupacional', formPostulante.value.grupo_ocupacional ?? '');
    if (Array.isArray(formPostulante.value.valores)) {
      fd.append('valores', JSON.stringify(formPostulante.value.valores));
    }
    if (fotoFile.value) {
      fd.append('foto', fotoFile.value);
    }

    if (formPostulante.value.id) {
      await apiClient.post(`/admin/postulantes/${formPostulante.value.id}`, fd);
      toast.add({ severity: 'success', summary: 'Postulante actualizado', life: 2500 });
    } else {
      await apiClient.post('/admin/postulantes', fd);
      toast.add({ severity: 'success', summary: 'Postulante creado', life: 2500 });
    }

    showPostulanteModal.value = false;
    await store.fetchDetallesProceso(selectedProceso.value.id);
  } catch (error) {
    const message = error?.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(' ')
      : 'No se pudo guardar el postulante.';
    toast.add({ severity: 'error', summary: 'Error', detail: message, life: 4000 });
  }
};

const handleDeletePostulante = (id) => {
  confirm.require({
    message: '¿Estás seguro de que quieres eliminar este postulante?',
    header: 'Confirmación de Eliminación',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Sí, eliminar',
    rejectLabel: 'Cancelar',
    accept: async () => {
      try {
        await apiClient.delete(`/admin/postulantes/${id}`);
        toast.add({ severity: 'warn', summary: 'Eliminado', detail: 'Postulante eliminado.', life: 2500 });
        if (selectedProceso.value?.id) {
          await store.fetchDetallesProceso(selectedProceso.value.id);
        }
      } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar el postulante.', life: 3000 });
      }
    },
  });
};

/* =========================
   Participación (votos emitidos vs padrón)
========================= */
const participacion = ref({
  total_votantes: 0,
  votos_emitidos: 0,
  pendientes: 0,
  porcentaje: 0,
  por_grupo: [],
});
const isLoadingParticipacion = ref(false);

async function fetchParticipacion(procesoId) {
  if (!procesoId) return;
  isLoadingParticipacion.value = true;
  try {
    const { data } = await apiClient.get('/admin/votos/estadisticas', {
      params: { proceso_id: procesoId },
    });
    participacion.value = {
      total_votantes: Number(data.total_votantes ?? 0),
      votos_emitidos: Number(data.votos_emitidos ?? 0),
      pendientes: Number(data.pendientes ?? 0),
      porcentaje: Number(data.porcentaje ?? 0),
      por_grupo: Array.isArray(data.por_grupo) ? data.por_grupo : [],
    };
  } catch (e) {
    participacion.value = { total_votantes: 0, votos_emitidos: 0, pendientes: 0, porcentaje: 0, por_grupo: [] };
  } finally {
    isLoadingParticipacion.value = false;
  }
}

const participacionChartData = computed(() => {
  const em = participacion.value.votos_emitidos;
  const pen = Math.max(participacion.value.total_votantes - em, 0);
  return {
    labels: ['Votos emitidos', 'Pendientes'],
    datasets: [
      {
        data: [em, pen],
        backgroundColor: ['#10B981', '#E5E7EB'],
        hoverBackgroundColor: ['#059669', '#D1D5DB'],
        borderWidth: 0,
      },
    ],
  };
});

// Chart opciones (donut)
const chartOptions = ref({
  plugins: {
    legend: { position: 'bottom', labels: { usePointStyle: true, color: '#374151' } }
  },
  elements: { arc: { borderWidth: 0 } },
  cutout: '60%',
});

/* =========================
   Lifecycle
========================= */
onMounted(() => {
  fetchProcesos();
});

watch(selectedProceso, (p) => {
  if (p?.id) fetchParticipacion(p.id);
});

onBeforeUnmount(() => {
  resetFotoPreview();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Tarjetas resumen -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <Card class="bg-white shadow-sm border-0 hover:shadow-md transition-shadow">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Total Procesos</p>
              <p class="text-3xl font-bold text-gray-900">{{ procesos.length }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
              <i class="pi pi-calendar text-blue-600 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="bg-white shadow-sm border-0 hover:shadow-md transition-shadow">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Postulantes</p>
              <p class="text-3xl font-bold text-gray-900">{{ postulantes.length }}</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <i class="pi pi-users text-green-600 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="bg-white shadow-sm border-0 hover:shadow-md transition-shadow">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Votos emitidos</p>
              <p class="text-3xl font-bold text-gray-900">
                {{ participacion.votos_emitidos }}
              </p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
              <i class="pi pi-chart-pie text-purple-600 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
      <!-- Lista de procesos -->
      <div class="xl:col-span-1">
        <Card class="bg-white shadow-sm border-0 h-full">
          <template #title>
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-900">Procesos</h3>
              <Button
                icon="pi pi-plus"
                class="p-button-primary p-button-rounded"
                @click="openProcesoModal()"
                v-tooltip.left="'Crear nuevo proceso'"
              />
            </div>
          </template>
          <template #content>
            <div v-if="isLoading.procesos" class="flex flex-col items-center justify-center py-8 space-y-3">
              <ProgressSpinner style="width: 40px; height: 40px" />
              <p class="text-gray-500 text-sm">Cargando procesos...</p>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="proceso in procesos"
                :key="proceso.id"
                @click="selectProceso(proceso)"
                class="p-4 border rounded-lg cursor-pointer transition-all duration-200 hover:shadow-md"
                :class="selectedProceso?.id === proceso.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
              >
                <div class="flex justify-between items-start">
                  <div class="flex-1">
                    <p class="font-semibold text-gray-900">Año: {{ proceso.ano }}</p>
                    <p class="text-sm text-gray-600">{{ proceso.desde }} - {{ proceso.hasta }}</p>
                  </div>
                  <Tag
                    :value="proceso.estado"
                    :severity="({ Nuevo:'info', Abierto:'success', Cerrado:'warning', Concluido:'primary', Cancelado:'danger' }[proceso.estado]) || 'secondary'"
                    rounded
                  />
                </div>
                <div class="mt-2 flex justify-end">
                  <Button
                    icon="pi pi-pencil"
                    class="p-button-text p-button-sm"
                    @click.stop="openProcesoModal(proceso)"
                  />
                </div>
              </div>

              <div v-if="!procesos?.length" class="text-center py-8">
                <i class="pi pi-inbox text-4xl text-gray-300 mb-2"></i>
                <p class="text-gray-500">No hay procesos creados</p>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Panel derecho -->
      <div class="xl:col-span-3 space-y-6">
        <div v-if="selectedProceso">
          <div v-if="isLoading.detalles" class="flex items-center justify-center h-96">
            <ProgressSpinner />
          </div>

          <div v-else class="space-y-6">
            <!-- Participación (reemplaza “Resultados”) -->
            <Card class="bg-white shadow-sm border-0">
              <template #title>
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-3">
                    <i class="pi pi-chart-pie text-xl text-emerald-600"></i>
                    <div>
                      <h3 class="text-lg font-semibold text-gray-900">Participación</h3>
                      <p class="text-sm text-gray-600">Proceso {{ selectedProceso.ano }}</p>
                    </div>
                  </div>
                  <div class="flex items-center space-x-2 px-3 py-1 bg-green-100 rounded-full">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-green-700 text-sm font-medium">En vivo</span>
                  </div>
                </div>
              </template>

              <template #content>
                <div v-if="isLoadingParticipacion" class="flex items-center justify-center py-10">
                  <ProgressSpinner />
                </div>

                <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                  <div class="flex justify-center">
                    <div class="relative">
                      <Chart
                        type="doughnut"
                        :data="participacionChartData"
                        :options="chartOptions"
                        class="w-80 h-80"
                      />
                      <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <div class="text-3xl font-bold text-gray-900">{{ participacion.porcentaje }}%</div>
                        <div class="text-sm text-gray-600">Participación</div>
                      </div>
                    </div>
                  </div>

                  <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                      <div class="bg-gray-50 rounded-lg p-4 border">
                        <div class="text-sm text-gray-600">Votantes hábiles</div>
                        <div class="text-2xl font-bold text-gray-900">{{ participacion.total_votantes }}</div>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border">
                        <div class="text-sm text-gray-600">Votos emitidos</div>
                        <div class="text-2xl font-bold text-gray-900">{{ participacion.votos_emitidos }}</div>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border">
                        <div class="text-sm text-gray-600">Pendientes</div>
                        <div class="text-2xl font-bold text-gray-900">{{ participacion.pendientes }}</div>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-4 border">
                        <div class="text-sm text-gray-600">Participación</div>
                        <div class="text-2xl font-bold text-gray-900">{{ participacion.porcentaje }}%</div>
                      </div>
                    </div>

                    <!-- (Opcional) Desglose por grupo -->
                    <!--
                    <div class="mt-4">
                      <h4 class="font-semibold text-gray-900 mb-2">Por grupo</h4>
                      <div class="space-y-2">
                        <div
                          v-for="g in participacion.por_grupo"
                          :key="g.grupo"
                          class="flex items-center justify-between bg-gray-50 p-2 rounded"
                        >
                          <span class="text-sm">Grupo {{ g.grupo }}</span>
                          <span class="text-sm text-gray-700">
                            {{ g.votos_emitidos }}/{{ g.total_votantes }} ({{ g.porcentaje }}%)
                          </span>
                        </div>
                      </div>
                    </div>
                    -->
                  </div>
                </div>
              </template>
            </Card>

            <!-- Postulantes -->
            <Card class="bg-white shadow-sm border-0">
              <template #title>
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-3">
                    <i class="pi pi-users text-xl text-blue-600"></i>
                    <div>
                      <h3 class="text-lg font-semibold text-gray-900">Postulantes</h3>
                      <p class="text-sm text-gray-600">{{ postulantes.length }} candidatos registrados</p>
                    </div>
                  </div>
                  <Button
                    icon="pi pi-user-plus"
                    label="Agregar"
                    class="p-button-primary"
                    @click="openPostulanteModal()"
                  />
                </div>
              </template>

              <template #content>
                <DataTable
                  :value="postulantes"
                  class="p-datatable-sm"
                  responsiveLayout="scroll"
                  stripedRows
                  :paginator="postulantes.length > 5"
                  :rows="5"
                >
                  <Column header="Foto" style="width: 80px">
                    <template #body="slotProps">
                      <img
                        :src="slotProps.data.foto_url || '/img/avatar-placeholder.png'"
                        alt="Foto"
                        style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;"
                      />
                    </template>
                  </Column>

                  <Column field="nombre_completo" header="Nombre Completo" class="font-medium" />

                  <Column field="grupo_ocupacional" header="Grupo">
                    <template #body="slotProps">
                      <Tag :value="`Grupo ${slotProps.data.grupo_ocupacional}`" severity="info" />
                    </template>
                  </Column>

                  <Column field="valores" header="Valores">
                    <template #body="slotProps">
                      <div class="flex flex-wrap gap-1">
                        <Tag
                          v-for="valor in (slotProps.data.valores || [])"
                          :key="valor"
                          :value="valor"
                          severity="secondary"
                          rounded
                        />
                        <span v-if="!slotProps.data.valores?.length" class="text-gray-400 text-sm">—</span>
                      </div>
                    </template>
                  </Column>

                  <Column header="Acciones" style="width: 140px">
                    <template #body="slotProps">
                      <div class="flex space-x-2">
                        <Button
                          icon="pi pi-pencil"
                          class="p-button-text p-button-sm"
                          v-tooltip="'Editar'"
                          @click="openPostulanteModal(slotProps.data)"
                        />
                        <Button
                          icon="pi pi-trash"
                          class="p-button-text p-button-sm p-button-danger"
                          v-tooltip="'Eliminar'"
                          @click="handleDeletePostulante(slotProps.data.id)"
                        />
                      </div>
                    </template>
                  </Column>

                  <template #empty>
                    <div class="text-center py-8">
                      <i class="pi pi-user-plus text-4xl text-gray-200 mb-2"></i>
                      <p class="text-gray-500">No hay postulantes registrados</p>
                    </div>
                  </template>
                </DataTable>
              </template>
            </Card>
          </div>
        </div>

        <Card v-else class="bg-white shadow-sm border-0 h-96 flex items-center justify-center">
          <template #content>
            <div class="text-center">
              <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4 mx-auto">
                <i class="pi pi-search text-3xl text-gray-400"></i>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 mb-2">Selecciona un Proceso</h3>
              <p class="text-gray-600">Elige un proceso electoral para ver sus detalles y gestionar postulantes.</p>
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- Modal Proceso -->
    <Dialog
      v-model:visible="showProcesoModal"
      :header="formProceso.id ? 'Editar Proceso' : 'Nuevo Proceso'"
      :modal="true"
      class="w-full max-w-md mx-4"
    >
      <div class="space-y-4 p-2">
        <div>
          <label for="ano" class="block text-sm font-medium text-gray-700 mb-2">Año del Proceso</label>
          <InputText id="ano" v-model.number="formProceso.ano" type="number" class="w-full" />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="desde" class="block text-sm font-medium text-gray-700 mb-2">Fecha de Inicio</label>
            <Calendar id="desde" v-model="formProceso.desde" dateFormat="yy-mm-dd" class="w-full" />
          </div>
          <div>
            <label for="hasta" class="block text-sm font-medium text-gray-700 mb-2">Fecha de Cierre</label>
            <Calendar id="hasta" v-model="formProceso.hasta" dateFormat="yy-mm-dd" class="w-full" />
          </div>
        </div>
        <div>
          <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
          <Dropdown id="estado" v-model="formProceso.estado" :options="['Cerrado', 'Abierto']" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" icon="pi pi-times" class="p-button-text" @click="showProcesoModal = false" />
        <Button label="Guardar" icon="pi pi-check" class="p-button-primary" @click="handleSaveProceso" />
      </template>
    </Dialog>

    <!-- Modal Postulante (con foto) -->
    <Dialog
      v-model:visible="showPostulanteModal"
      :header="formPostulante.id ? 'Editar Postulante' : 'Nuevo Postulante'"
      :modal="true"
      class="w-full max-w-2xl mx-4"
    >
      <div class="space-y-4 p-2">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Columna foto -->
          <div class="md:col-span-1">
            <div class="flex flex-col items-center">
              <div class="w-32 h-32 rounded-lg border border-gray-200 overflow-hidden bg-gray-50 mb-3">
                <img
                  v-if="fotoPreview"
                  :src="fotoPreview"
                  alt="Previsualización"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                  <i class="pi pi-image text-3xl"></i>
                </div>
              </div>
              <label class="p-button p-button-outlined p-button-sm cursor-pointer">
                <i class="pi pi-upload mr-2"></i>
                <span>Seleccionar foto</span>
                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  class="hidden"
                  @change="onFotoChange"
                />
              </label>
              <p class="text-xs text-gray-500 mt-2">JPG/PNG/WebP máx. 2MB</p>
            </div>
          </div>

          <!-- Columna formulario -->
          <div class="md:col-span-2 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="md:col-span-2">
                <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre Completo</label>
                <InputText id="nombre" v-model="formPostulante.nombre_completo" class="w-full" />
              </div>
              <div>
                <label for="grupo" class="block text-sm font-medium text-gray-700 mb-2">Grupo</label>
                <Dropdown id="grupo" v-model="formPostulante.grupo_ocupacional" :options="grupoOcupacionalOptions" class="w-full" />
              </div>
            </div>

            <div>
              <label for="cargo" class="block text-sm font-medium text-gray-700 mb-2">Cargo o Posición</label>
              <InputText id="cargo" v-model="formPostulante.cargo" class="w-full" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Correo Electrónico</label>
                <InputText id="email" v-model="formPostulante.email" type="email" class="w-full" />
              </div>
              <div>
                <label for="telefono" class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                <InputText id="telefono" v-model="formPostulante.telefono" class="w-full" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-3">Valores Representados</label>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-48 overflow-y-auto">
                <div v-for="valor in valoresOptions" :key="valor" class="flex items-center">
                  <Checkbox v-model="formPostulante.valores" :inputId="valor" name="valor" :value="valor" class="mr-2" />
                  <label :for="valor">{{ valor }}</label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Cancelar" icon="pi pi-times" class="p-button-text" @click="showPostulanteModal = false" />
        <Button label="Guardar" icon="pi pi-check" class="p-button-primary" @click="handleSavePostulante" />
      </template>
    </Dialog>
  </div>
</template>
