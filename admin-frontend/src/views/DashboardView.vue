<script setup>
import { ref, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useProcesosStore } from '../stores/procesosStore';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from 'primevue/usetoast';

// Componentes de PrimeVue usados en esta vista
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

// --- Inicialización del Store y Servicios ---
const store = useProcesosStore();
const confirm = useConfirm();
const toast = useToast();

const { 
  procesos, 
  postulantes, 
  resultados, 
  selectedProceso, 
  isLoading, 
  totalVotos,
  chartData 
} = storeToRefs(store);

const { 
  fetchProcesos, 
  selectProceso 
} = store;

// --- Lógica de UI local (Modales y Formularios) ---
const showProcesoModal = ref(false);
const formProceso = ref({ id: null, ano: new Date().getFullYear(), desde: '', hasta: '', estado: 'Cerrado' });

const showPostulanteModal = ref(false);
const formPostulante = ref({ id: null, nombre_completo: '', cargo: '', email: '', telefono: '', grupo_ocupacional: '', valores: [] });

// Opciones para formularios
const valoresOptions = ref(['Colaboración', 'Compromiso', 'Confiabilidad', 'Disciplina', 'Discreción', 'Honestidad', 'Honorabilidad', 'Honradez', 'Probidad', 'Rectitud', 'Responsabilidad', 'Trabajo en equipo', 'Vocación al servicio']);
const grupoOcupacionalOptions = ref(['I', 'II', 'III', 'IV', 'V']);

const openProcesoModal = (proceso = null) => {
    formProceso.value = proceso 
        ? { ...proceso, desde: new Date(proceso.desde), hasta: new Date(proceso.hasta) } 
        : { id: null, ano: new Date().getFullYear(), desde: '', hasta: '', estado: 'Cerrado' };
    showProcesoModal.value = true;
};

const openPostulanteModal = (postulante = null) => {
    formPostulante.value = postulante 
        ? { ...postulante } 
        : { id: null, nombre_completo: '', cargo: '', email: '', telefono: '', grupo_ocupacional: '', valores: [] };
    showPostulanteModal.value = true;
};

const handleSaveProceso = async () => {
    const success = await store.saveProceso(formProceso.value);
    if (success) {
        showProcesoModal.value = false;
    }
};

const handleSavePostulante = async () => {
    if (!selectedProceso.value) return;
    const data = { ...formPostulante.value, proceso_id: selectedProceso.value.id };
    
    // Aquí puedes llamar a una acción del store similar a saveProceso
    // Por ahora, asumiré que la acción no está en el store y la pongo aquí
    try {
        if (data.id) {
            await axios.put(`http://127.0.0.1:8000/api/admin/postulantes/${data.id}`, data);
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Postulante actualizado.', life: 3000 });
        } else {
            await axios.post(`http://127.0.0.1:8000/api/admin/postulantes`, data);
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Postulante creado.', life: 3000 });
        }
        showPostulanteModal.value = false;
        store.fetchDetallesProceso(selectedProceso.value.id); // Recargamos detalles desde el store
    } catch (error) {
        const message = error.response?.data?.errors ? Object.values(error.response.data.errors).join(' ') : 'No se pudo guardar el postulante.';
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
            // Aquí también debería ir una acción del store
            try {
                await axios.delete(`http://127.0.0.1:8000/api/admin/postulantes/${id}`);
                toast.add({ severity: 'warn', summary: 'Eliminado', detail: 'Postulante eliminado.', life: 3000 });
                store.fetchDetallesProceso(selectedProceso.value.id);
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar el postulante.', life: 3000 });
            }
        },
    });
};


onMounted(() => {
  fetchProcesos();
});

const chartOptions = ref({
    plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, color: '#374151' } }
    },
    elements: { arc: { borderWidth: 0 } },
    cutout: '60%'
});
</script>

<template>
  <div class="space-y-6">
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
                        <p class="text-gray-600 text-sm font-medium">Total Votos</p>
                        <p class="text-3xl font-bold text-gray-900">{{ totalVotos }}</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="pi pi-chart-pie text-purple-600 text-xl"></i>
                    </div>
                </div>
            </template>
        </Card>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
        <div class="xl:col-span-1">
            <Card class="bg-white shadow-sm border-0 h-full">
                <template #title>
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Procesos</h3>
                        <Button icon="pi pi-plus" class="p-button-primary p-button-rounded" 
                                @click="openProcesoModal()" 
                                v-tooltip.left="'Crear nuevo proceso'" />
                    </div>
                </template>
                <template #content>
                    <div v-if="isLoading.procesos" class="flex flex-col items-center justify-center py-8 space-y-3">
                        <ProgressSpinner style="width: 40px; height: 40px" />
                        <p class="text-gray-500 text-sm">Cargando procesos...</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="proceso in procesos" :key="proceso.id"
                            @click="selectProceso(proceso)"
                            class="p-4 border rounded-lg cursor-pointer transition-all duration-200 hover:shadow-md"
                            :class="selectedProceso?.id === proceso.id 
                                ? 'border-blue-500 bg-blue-50' 
                                : 'border-gray-200 hover:border-gray-300'">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Año: {{ proceso.ano }}</p>
                                    <p class="text-sm text-gray-600">{{ proceso.desde }} - {{ proceso.hasta }}</p>
                                </div>
                                <Tag :value="proceso.estado" 
                                     :severity="proceso.estado === 'Abierto' ? 'success' : 'danger'" 
                                     rounded />
                            </div>
                            <div class="mt-2 flex justify-end">
                                <Button icon="pi pi-pencil" 
                                        class="p-button-text p-button-sm" 
                                        @click.stop="openProcesoModal(proceso)" />
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

        <div class="xl:col-span-3 space-y-6">
            <div v-if="selectedProceso">
                <div v-if="isLoading.detalles" class="flex items-center justify-center h-96">
                    <ProgressSpinner />
                </div>
                <div v-else class="space-y-6">
                    <Card class="bg-white shadow-sm border-0">
                        <template #title>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <i class="pi pi-chart-pie text-xl text-emerald-600"></i>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Resultados</h3>
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
                            <div v-if="totalVotos > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                                <div class="flex justify-center">
                                    <div class="relative">
                                        <Chart type="doughnut" :data="chartData" :options="chartOptions" class="w-80 h-80" />
                                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                                            <div class="text-3xl font-bold text-gray-900">{{ totalVotos }}</div>
                                            <div class="text-sm text-gray-600">Votos</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <h4 class="font-semibold text-gray-900 mb-4">Distribución de Votos</h4>
                                    <div v-for="(res, index) in resultados" :key="res.id" 
                                         class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-4 h-4 rounded-full" 
                                                 :style="{ backgroundColor: chartData.datasets[0].backgroundColor[index] }"></div>
                                            <div>
                                                <div class="font-medium text-gray-900">{{ res.nombre_completo }}</div>
                                                <div class="text-sm text-gray-600">
                                                    {{ totalVotos > 0 ? Math.round((res.total_votos / totalVotos) * 100) : 0 }}%
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-xl font-bold text-gray-900">{{ res.total_votos }}</div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12">
                                <i class="pi pi-chart-bar text-6xl text-gray-200 mb-4"></i>
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">Sin votos registrados</h4>
                                <p class="text-gray-600">Los resultados aparecerán cuando se registren los primeros votos</p>
                            </div>
                        </template>
                    </Card>

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
                                <Button icon="pi pi-user-plus" label="Agregar" 
                                        class="p-button-primary"
                                        @click="openPostulanteModal()" />
                            </div>
                        </template>
                        <template #content>
                            <DataTable :value="postulantes" 
                                      class="p-datatable-sm" 
                                      responsiveLayout="scroll" 
                                      stripedRows
                                      :paginator="postulantes.length > 5"
                                      :rows="5">
                                <Column field="nombre_completo" header="Nombre Completo" class="font-medium"></Column>
                                <Column field="grupo_ocupacional" header="Grupo">
                                    <template #body="slotProps">
                                        <Tag :value="`Grupo ${slotProps.data.grupo_ocupacional}`" severity="info" />
                                    </template>
                                </Column>
                                <Column field="email" header="Contacto">
                                    <template #body="slotProps">
                                        <div>
                                            <div class="text-sm text-gray-900">{{ slotProps.data.email }}</div>
                                            <div class="text-xs text-gray-500">{{ slotProps.data.telefono }}</div>
                                        </div>
                                    </template>
                                </Column>
                                <Column header="Acciones" style="width: 120px">
                                    <template #body="slotProps">
                                        <div class="flex space-x-2">
                                            <Button icon="pi pi-pencil" 
                                                    class="p-button-text p-button-sm"
                                                    v-tooltip="'Editar'"
                                                    @click="openPostulanteModal(slotProps.data)" />
                                            <Button icon="pi pi-trash" 
                                                    class="p-button-text p-button-sm p-button-danger"
                                                    v-tooltip="'Eliminar'"
                                                    @click="handleDeletePostulante(slotProps.data.id)" />
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
    
    <Dialog v-model:visible="showProcesoModal" 
            :header="formProceso.id ? 'Editar Proceso' : 'Nuevo Proceso'" 
            :modal="true" 
            class="w-full max-w-md mx-4">
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

    <Dialog v-model:visible="showPostulanteModal" 
            :header="formPostulante.id ? 'Editar Postulante' : 'Nuevo Postulante'" 
            :modal="true" 
            class="w-full max-w-2xl mx-4">
        <div class="space-y-4 p-2">
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
        <template #footer>
            <Button label="Cancelar" icon="pi pi-times" class="p-button-text" @click="showPostulanteModal = false" />
            <Button label="Guardar" icon="pi pi-check" class="p-button-primary" @click="handleSavePostulante" />
        </template>
    </Dialog>
  </div>
</template>