<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';

// Componentes y Servicios de PrimeVue
import Button from 'primevue/button';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';
import Toast from 'primevue/toast';
import Tag from 'primevue/tag';
import ConfirmDialog from 'primevue/confirmdialog';
import ProgressSpinner from 'primevue/progressspinner';
import Chart from 'primevue/chart';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from "primevue/useconfirm";

// --- CONFIGURACIÓN ---
const toast = useToast();
const confirm = useConfirm();
const apiUrl = 'http://127.0.0.1:8000/api/admin';

// --- ESTADO ---
const procesos = ref([]);
const postulantes = ref([]);
const resultados = ref([]);
const selectedProceso = ref(null);
const isLoading = ref({ procesos: true, detalles: false });
let resultadosInterval = null;

// Opciones para formularios
const valoresOptions = ref(['Colaboración', 'Compromiso', 'Confiabilidad', 'Disciplina', 'Discreción', 'Honestidad', 'Honorabilidad', 'Honradez', 'Probidad', 'Rectitud', 'Responsabilidad', 'Trabajo en equipo', 'Vocación al servicio']);
const grupoOcupacionalOptions = ref(['I', 'II', 'III', 'IV', 'V']);

// Formularios
const showProcesoModal = ref(false);
const formProceso = ref({ id: null, ano: new Date().getFullYear(), desde: '', hasta: '', estado: 'Cerrado' });

const showPostulanteModal = ref(false);
const formPostulante = ref({ id: null, nombre_completo: '', cargo: '', email: '', telefono: '', grupo_ocupacional: '', valores: [] });

// --- ESTADO PARA GRÁFICO ---
const chartData = ref();
const chartOptions = ref({
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                color: '#4b5563'
            }
        }
    }
});

// --- LÓGICA DE API ---
const fetchProcesos = async () => {
    isLoading.value.procesos = true;
    try {
        const response = await axios.get(`${apiUrl}/procesos`);
        procesos.value = response.data; 
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los procesos.', life: 3000 });
    } finally {
        isLoading.value.procesos = false;
    }
};

const saveProceso = async () => {
    try {
        const desdeDate = formProceso.value.desde instanceof Date ? formProceso.value.desde : new Date(formProceso.value.desde);
        const hastaDate = formProceso.value.hasta instanceof Date ? formProceso.value.hasta : new Date(formProceso.value.hasta);
        const data = { ...formProceso.value, desde: desdeDate.toISOString().split('T')[0], hasta: hastaDate.toISOString().split('T')[0] };

        if (formProceso.value.id) {
            await axios.put(`${apiUrl}/procesos/${formProceso.value.id}`, data);
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Proceso actualizado.', life: 3000 });
        } else {
            await axios.post(`${apiUrl}/procesos`, data);
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Proceso creado.', life: 3000 });
        }
        showProcesoModal.value = false;
        fetchProcesos();
    } catch (error) {
        const message = error.response?.data?.errors ? Object.values(error.response.data.errors).join(' ') : 'No se pudo guardar el proceso.';
        toast.add({ severity: 'error', summary: 'Error', detail: message, life: 4000 });
    }
};

const fetchDetallesProceso = async (procesoId) => {
    isLoading.value.detalles = true;
    try {
        const [postulantesRes, resultadosRes] = await Promise.all([
            axios.get(`${apiUrl}/postulantes?proceso_id=${procesoId}`),
            axios.get(`${apiUrl}/resultados/${procesoId}`)
        ]);
        postulantes.value = postulantesRes.data.data;
        resultados.value = Array.isArray(resultadosRes.data) ? resultadosRes.data : [];
    } catch (error) {
        postulantes.value = [];
        resultados.value = [];
        toast.add({ severity: 'warn', summary: 'Aviso', detail: 'No se pudieron cargar los detalles del proceso.', life: 3000 });
    } finally {
        isLoading.value.detalles = false;
    }
};

const savePostulante = async () => {
    if (!selectedProceso.value) return;
    try {
        const data = { ...formPostulante.value, proceso_id: selectedProceso.value.id };
        if (formPostulante.value.id) {
            await axios.put(`${apiUrl}/postulantes/${formPostulante.value.id}`, data);
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Postulante actualizado.', life: 3000 });
        } else {
            await axios.post(`${apiUrl}/postulantes`, data);
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Postulante creado.', life: 3000 });
        }
        showPostulanteModal.value = false;
        fetchDetallesProceso(selectedProceso.value.id);
    } catch (error) {
        const message = error.response?.data?.errors ? Object.values(error.response.data.errors).join(' ') : 'No se pudo guardar el postulante.';
        toast.add({ severity: 'error', summary: 'Error', detail: message, life: 4000 });
    }
};

const deletePostulante = (id) => {
    confirm.require({
        message: '¿Estás seguro de que quieres eliminar este postulante?',
        header: 'Confirmación de Eliminación',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Sí, eliminar',
        rejectLabel: 'Cancelar',
        accept: async () => {
            try {
                await axios.delete(`${apiUrl}/postulantes/${id}`);
                toast.add({ severity: 'warn', summary: 'Eliminado', detail: 'Postulante eliminado.', life: 3000 });
                fetchDetallesProceso(selectedProceso.value.id);
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar el postulante.', life: 3000 });
            }
        },
    });
};

// --- MANEJO DE UI ---
const openProcesoModal = (proceso = null) => {
    formProceso.value = proceso ? { ...proceso, desde: new Date(proceso.desde), hasta: new Date(proceso.hasta) } : { id: null, ano: new Date().getFullYear(), desde: '', hasta: '', estado: 'Cerrado' };
    showProcesoModal.value = true;
};

const openPostulanteModal = (postulante = null) => {
    formPostulante.value = postulante ? { ...postulante } : { id: null, nombre_completo: '', cargo: '', email: '', telefono: '', grupo_ocupacional: '', valores: [] };
    showPostulanteModal.value = true;
};

const selectProceso = (proceso) => {
    selectedProceso.value = proceso;
    postulantes.value = []; 
    resultados.value = [];
    
    fetchDetallesProceso(proceso.id);

    if (resultadosInterval) clearInterval(resultadosInterval);
    resultadosInterval = setInterval(() => {
        if (selectedProceso.value && !isLoading.value.detalles) {
            fetchDetallesProceso(selectedProceso.value.id);
        }
    }, 15000);
};

// --- COMPUTED PROPERTIES & WATCHERS ---
const totalVotos = computed(() => {
    if (!Array.isArray(resultados.value)) return 0;
    return resultados.value.reduce((total, item) => total + (item.total_votos || 0), 0);
});

watch(resultados, (newResultados) => {
    if (newResultados && newResultados.length > 0) {
        chartData.value = {
            labels: newResultados.map(r => r.nombre_completo),
            datasets: [{
                data: newResultados.map(r => r.total_votos),
                backgroundColor: ['#42A5F5', '#66BB6A', '#FFA726', '#26A69A', '#AB47BC', '#78909C', '#EC407A'],
                hoverBackgroundColor: ['#64B5F6', '#81C784', '#FFB74D', '#4DB6AC', '#BA68C8', '#90A4AE', '#F06292']
            }]
        };
    } else {
        chartData.value = null;
    }
}, { deep: true });

// --- LIFECYCLE HOOKS ---
onMounted(fetchProcesos);
</script>

<template>
    <Toast position="top-center" />
    <ConfirmDialog />
    <div class="admin-container">
        <header class="admin-header">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Panel de Administración</h1>
                <p class="text-gray-500">Gestión del Comité de Ética</p>
            </div>
            <i class="pi pi-shield text-5xl text-blue-500"></i>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Columna de Procesos -->
            <div class="lg:col-span-1">
                <Card class="h-full shadow-lg">
                    <template #title>
                        <div class="flex justify-between items-center">
                            <span class="text-xl font-semibold">Procesos Electorales</span>
                            <Button icon="pi pi-plus" rounded @click="openProcesoModal()" v-tooltip.left="'Crear nuevo proceso'" />
                        </div>
                    </template>
                    <template #content>
                        <div v-if="isLoading.procesos" class="flex justify-center py-8">
                            <ProgressSpinner style="width: 40px; height: 40px" />
                        </div>
                        <div v-else class="space-y-3">
                            <div v-for="proceso in procesos" :key="proceso.id"
                                @click="selectProceso(proceso)"
                                class="proceso-card"
                                :class="{'selected': selectedProceso?.id === proceso.id}">
                                <div class="flex-grow">
                                    <p class="font-bold text-gray-800">Año: {{ proceso.ano }}</p>
                                    <p class="text-sm text-gray-500">{{ proceso.desde }} al {{ proceso.hasta }}</p>
                                </div>
                                <Tag :value="proceso.estado" :severity="proceso.estado === 'Abierto' ? 'success' : 'danger'" rounded />
                                <Button icon="pi pi-pencil" text rounded class="edit-btn" @click.stop="openProcesoModal(proceso)" />
                            </div>
                            <p v-if="!procesos?.length" class="text-center text-gray-500 py-4">No hay procesos creados.</p>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Columna de Detalles -->
            <div class="lg:col-span-2 space-y-8">
                <div v-if="selectedProceso">
                    <div v-if="isLoading.detalles" class="flex justify-center items-center h-96">
                        <ProgressSpinner />
                    </div>
                    <div v-else class="space-y-8">
                        <!-- Resultados -->
                        <Card class="shadow-lg">
                             <template #title>
                                <span class="text-xl font-semibold">Resultados: Proceso {{ selectedProceso.ano }}</span>
                            </template>
                            <template #content>
                                <div v-if="totalVotos > 0" class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                                    <div class="flex justify-center">
                                        <Chart type="doughnut" :data="chartData" :options="chartOptions" class="w-full max-w-xs" />
                                    </div>
                                    <div class="space-y-2">
                                        <div class="font-bold text-lg text-gray-700">Total de Votos: {{ totalVotos }}</div>
                                        <div v-for="res in resultados" :key="res.id" class="text-sm">
                                            <span class="font-medium">{{res.nombre_completo}}:</span> 
                                            <span class="text-blue-600 font-semibold ml-2">{{res.total_votos}} votos</span>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-center text-gray-500 py-8">Aún no hay votos registrados para este proceso.</p>
                            </template>
                        </Card>
                         <!-- Postulantes -->
                        <Card class="shadow-lg">
                            <template #title>
                                <div class="flex justify-between items-center">
                                    <span class="text-xl font-semibold">Postulantes</span>
                                    <Button icon="pi pi-user-plus" label="Agregar" @click="openPostulanteModal()" severity="secondary"/>
                                </div>
                            </template>
                            <template #content>
                                <DataTable :value="postulantes" responsiveLayout="scroll" size="small" stripedRows>
                                    <Column field="nombre_completo" header="Nombre"></Column>
                                    <Column field="grupo_ocupacional" header="Grupo"></Column>
                                    <Column field="email" header="Email"></Column>
                                    <Column header="Acciones" style="width: 8rem; text-align: center;">
                                        <template #body="slotProps">
                                            <Button icon="pi pi-pencil" text rounded @click="openPostulanteModal(slotProps.data)" />
                                            <Button icon="pi pi-trash" text rounded severity="danger" @click="deletePostulante(slotProps.data.id)" />
                                        </template>
                                    </Column>
                                    <template #empty>No hay postulantes para este proceso.</template>
                                </DataTable>
                            </template>
                        </Card>
                    </div>
                </div>
                <Card v-else class="shadow-lg">
                    <template #content>
                        <div class="flex flex-col items-center justify-center h-96 text-center">
                            <i class="pi pi-inbox text-6xl text-gray-300"></i>
                            <p class="text-gray-500 text-lg mt-4">Seleccione un proceso para ver sus detalles.</p>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Modales -->
        <Dialog v-model:visible="showProcesoModal" :header="formProceso.id ? 'Editar Proceso' : 'Nuevo Proceso'" :modal="true" class="p-fluid" style="width: 450px">
            <div class="field">
                <label for="ano">Año</label>
                <InputText id="ano" v-model.number="formProceso.ano" type="number" />
            </div>
            <div class="field">
                <label for="desde">Desde</label>
                <Calendar id="desde" v-model="formProceso.desde" dateFormat="yy-mm-dd" />
            </div>
            <div class="field">
                <label for="hasta">Hasta</label>
                <Calendar id="hasta" v-model="formProceso.hasta" dateFormat="yy-mm-dd" />
            </div>
            <div class="field">
                <label for="estado">Estado</label>
                <Dropdown id="estado" v-model="formProceso.estado" :options="['Cerrado', 'Abierto']" placeholder="Seleccione un estado" />
            </div>
            <template #footer>
                <Button label="Cancelar" icon="pi pi-times" class="p-button-text" @click="showProcesoModal = false" />
                <Button label="Guardar" icon="pi pi-check" @click="saveProceso" />
            </template>
        </Dialog>

        <Dialog v-model:visible="showPostulanteModal" :header="formPostulante.id ? 'Editar Postulante' : 'Nuevo Postulante'" :modal="true" class="p-fluid" style="width: 500px">
            <div class="field">
                <label for="nombre">Nombre Completo</label>
                <InputText id="nombre" v-model="formPostulante.nombre_completo" />
            </div>
            <div class="field">
                <label for="cargo">Cargo</label>
                <InputText id="cargo" v-model="formPostulante.cargo" />
            </div>
            <div class="field">
                <label for="email">Email</label>
                <InputText id="email" v-model="formPostulante.email" type="email" />
            </div>
            <div class="field">
                <label for="telefono">Teléfono</label>
                <InputText id="telefono" v-model="formPostulante.telefono" />
            </div>
            <div class="field">
                <label for="grupo">Grupo Ocupacional</label>
                <Dropdown id="grupo" v-model="formPostulante.grupo_ocupacional" :options="grupoOcupacionalOptions" placeholder="Seleccione un grupo" />
            </div>
            <div class="field">
                <label>Valores</label>
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <div v-for="valor in valoresOptions" :key="valor" class="flex items-center">
                        <Checkbox v-model="formPostulante.valores" :inputId="valor" name="valor" :value="valor" />
                        <label :for="valor" class="ml-2">{{ valor }}</label>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" icon="pi pi-times" class="p-button-text" @click="showPostulanteModal = false" />
                <Button label="Guardar" icon="pi pi-check" @click="savePostulante" />
            </template>
        </Dialog>
    </div>
</template>

<style>
body, .admin-container {
    background-color: #f8fafc; /* Un gris más claro y suave */
    font-family: 'Inter', sans-serif;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 2rem;
}

.proceso-card {
    position: relative;
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    background-color: #ffffff;
}

.proceso-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    border-color: #c7d2fe;
}

.proceso-card.selected {
    border-color: #6366f1;
    background-color: #eef2ff;
    box-shadow: 0 0 0 2px #c7d2fe;
}

.proceso-card .edit-btn {
    opacity: 0;
    transition: opacity 0.2s ease;
}
.proceso-card:hover .edit-btn {
    opacity: 1;
}

.field {
    margin-bottom: 1rem;
}
</style>

