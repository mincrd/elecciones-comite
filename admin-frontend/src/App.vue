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
const sidebarOpen = ref(false);
const activeSection = ref('Inicio');
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
                color: '#374151',
                font: {
                    size: 12,
                    family: 'Inter, sans-serif'
                }
            }
        }
    },
    elements: {
        arc: {
            borderWidth: 0
        }
    },
    cutout: '60%'
});

// Navegación del sidebar
const menuItems = ref([
    {
        id: 'Inicio',
        label: 'Inicio',
        icon: 'pi-chart-line',
        description: 'Vista general del sistema'
    },

    {
        id: 'usuarios',
        label: 'Administración de Usuarios',
        icon: 'pi-users',
        description: 'Gestión de usuarios del sistema'
    },
]);

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
        console.log("procesoId:", procesoId);
        const [postulantesRes, resultadosRes] = await Promise.all([
            axios.get(`${apiUrl}/postulantes?proceso_id=${procesoId}`),
            axios.get(`${apiUrl}/resultados/${procesoId}`)
        ]);
        postulantes.value = postulantesRes.data;
        resultados.value = Array.isArray(resultadosRes.data) ? resultadosRes.data : [];
        console.log("resultados:", resultados.value);
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

const setActiveSection = (sectionId) => {
    activeSection.value = sectionId;
    sidebarOpen.value = false;
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
                backgroundColor: [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
                    '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'
                ],
                hoverBackgroundColor: [
                    '#2563EB', '#059669', '#D97706', '#DC2626',
                    '#7C3AED', '#DB2777', '#0891B2', '#65A30D'
                ],
                borderWidth: 0
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
    <div class="flex min-h-screen bg-gray-50">
        <Toast position="top-center" />
        <ConfirmDialog />
        
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 z-50 w-80 bg-slate-900 shadow-xl transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0"
             :class="{ '-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen }">
            
            <!-- Header del Sidebar -->
            <div class="flex items-center justify-between p-6 border-b border-slate-700">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="pi pi-shield text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-white">Administrador</h2>
                        <p class="text-xs text-slate-400">Panel de Control</p>
                    </div>
                </div>
                <button @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-white">
                    <i class="pi pi-times text-xl"></i>
                </button>
            </div>

            <!-- Navegación -->
            <nav class="p-4 space-y-2">
                <div v-for="item in menuItems" :key="item.id"
                     @click="setActiveSection(item.id)"
                     class="flex items-center p-3 rounded-lg cursor-pointer transition-all duration-200"
                     :class="activeSection === item.id 
                        ? 'bg-blue-600 text-white shadow-lg' 
                        : 'text-slate-300 hover:bg-slate-800 hover:text-white'">
                    <i :class="`pi ${item.icon} text-lg`"></i>
                    <div class="ml-3 flex-1">
                        <div class="font-medium">{{ item.label }}</div>
                        <div class="text-xs opacity-75">{{ item.description }}</div>
                    </div>
                </div>
            </nav>

            <!-- Footer del Sidebar -->
            <div class="absolute bottom-4 left-4 right-4">
                <div class="p-3 bg-slate-800 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                            <i class="pi pi-user text-white text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">Administrador</p>
                            <p class="text-xs text-slate-400 truncate">admin@comite.com</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overlay para móvil -->
        <div v-if="sidebarOpen" @click="sidebarOpen = false" 
             class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

        <!-- Contenido Principal -->
        <div class="flex-1 lg:ml-0">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100">
                            <i class="pi pi-bars text-xl"></i>
                        </button>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">
                                {{ menuItems.find(item => item.id === activeSection)?.label || 'Inicio' }}
                            </h1>
                            <p class="text-sm text-gray-600">
                                {{ menuItems.find(item => item.id === activeSection)?.description || 'Vista general del sistema' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <Button icon="pi pi-bell" class="p-button-text p-button-rounded" />
                        <Button icon="pi pi-cog" class="p-button-text p-button-rounded" />
                    </div>
                </div>
            </header>

            <!-- Contenido dinámico -->
            <main class="p-6">
                <!-- Inicio -->
                <div v-if="activeSection === 'Inicio'" class="space-y-6">
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
                        <!-- Lista de Procesos -->
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

                        <!-- Detalles del Proceso -->
                        <div class="xl:col-span-3 space-y-6">
                            <div v-if="selectedProceso">
                                <div v-if="isLoading.detalles" class="flex items-center justify-center h-96">
                                    <ProgressSpinner />
                                </div>
                                <div v-else class="space-y-6">
                                    <!-- Resultados -->
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
                                                                    @click="deletePostulante(slotProps.data.id)" />
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
                            
                            <!-- Estado vacío -->
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
                    <!-- Gráfico de tendencias -->
                    <!-- <Card class="bg-white shadow-sm border-0">
                        <template #title>
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">Actividad Reciente</h3>
                                <Button label="Ver Todo" class="p-button-text" />
                            </div>
                        </template>
                        <template #content>
                            <div class="h-64 flex items-center justify-center text-gray-500">
                                <div class="text-center">
                                    <i class="pi pi-chart-line text-4xl mb-2"></i>
                                    <p>Gráfico de actividad del sistema</p>
                                </div>
                            </div>
                        </template>
                    </Card> -->
                </div>

                <!-- Sección de Usuarios -->
                <div v-else-if="activeSection === 'usuarios'" class="space-y-6">
                    <Card class="bg-white shadow-sm border-0">
                        <template #title>
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">Administración de Usuarios</h3>
                                <Button icon="pi pi-user-plus" label="Nuevo Usuario" class="p-button-primary" />
                            </div>
                        </template>
                        <template #content>
                            <div class="text-center py-12">
                                <i class="pi pi-users text-6xl text-gray-200 mb-4"></i>
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">Gestión de Usuarios</h4>
                                <p class="text-gray-600">Administra usuarios, roles y permisos del sistema</p>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Sección de Votantes -->
                <div v-else-if="activeSection === 'votantes'" class="space-y-6">
                    <Card class="bg-white shadow-sm border-0">
                        <template #title>
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">Votantes Registrados</h3>
                                <Button icon="pi pi-upload" label="Importar Lista" class="p-button-primary" />
                            </div>
                        </template>
                        <template #content>
                            <div class="text-center py-12">
                                <i class="pi pi-user-plus text-6xl text-gray-200 mb-4"></i>
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">Lista de Votantes</h4>
                                <p class="text-gray-600">Gestiona la lista de personas habilitadas para votar</p>
                            </div>
                        </template>
                    </Card>
                </div>
            </main>
        </div>

        <!-- Modales -->
        <Dialog v-model:visible="showProcesoModal" 
                :header="formProceso.id ? 'Editar Proceso' : 'Nuevo Proceso'" 
                :modal="true" 
                class="w-full max-w-md mx-4">
            <div class="space-y-4 p-2">
                <div>
                    <label for="ano" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="pi pi-calendar mr-1"></i> Año del Proceso
                    </label>
                    <InputText id="ano" v-model.number="formProceso.ano" type="number" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="2024" />
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="desde" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="pi pi-calendar mr-1"></i> Fecha de Inicio
                        </label>
                        <Calendar id="desde" v-model="formProceso.desde" dateFormat="yy-mm-dd"
                                  class="w-full" placeholder="Seleccionar fecha" />
                    </div>
                    <div>
                        <label for="hasta" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="pi pi-calendar mr-1"></i> Fecha de Cierre
                        </label>
                        <Calendar id="hasta" v-model="formProceso.hasta" dateFormat="yy-mm-dd"
                                  class="w-full" placeholder="Seleccionar fecha" />
                    </div>
                </div>
                
                <div>
                    <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="pi pi-flag mr-1"></i> Estado del Proceso
                    </label>
                    <Dropdown id="estado" v-model="formProceso.estado" 
                              :options="['Cerrado', 'Abierto']" 
                              placeholder="Seleccione un estado"
                              class="w-full" />
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end space-x-3 pt-4">
                    <Button label="Cancelar" icon="pi pi-times" 
                            class="p-button-text" 
                            @click="showProcesoModal = false" />
                    <Button label="Guardar" icon="pi pi-check" 
                            class="p-button-primary"
                            @click="saveProceso" />
                </div>
            </template>
        </Dialog>

        <Dialog v-model:visible="showPostulanteModal" 
                :header="formPostulante.id ? 'Editar Postulante' : 'Nuevo Postulante'" 
                :modal="true" 
                class="w-full max-w-2xl mx-4">
            <div class="space-y-4 p-2">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="pi pi-user mr-1"></i> Nombre Completo
                        </label>
                        <InputText id="nombre" v-model="formPostulante.nombre_completo"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="Nombre y apellidos completos" />
                    </div>
                    <div>
                        <label for="grupo" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="pi pi-tags mr-1"></i> Grupo
                        </label>
                        <Dropdown id="grupo" v-model="formPostulante.grupo_ocupacional" 
                                  :options="grupoOcupacionalOptions" 
                                  placeholder="Grupo"
                                  class="w-full" />
                    </div>
                </div>
                
                <div>
                    <label for="cargo" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="pi pi-briefcase mr-1"></i> Cargo o Posición
                    </label>
                    <InputText id="cargo" v-model="formPostulante.cargo"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Cargo actual o posición" />
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="pi pi-envelope mr-1"></i> Correo Electrónico
                        </label>
                        <InputText id="email" v-model="formPostulante.email" type="email"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="correo@ejemplo.com" />
                    </div>
                    <div>
                        <label for="telefono" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="pi pi-phone mr-1"></i> Teléfono
                        </label>
                        <InputText id="telefono" v-model="formPostulante.telefono"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="+1 (555) 123-4567" />
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="pi pi-heart mr-1"></i> Valores Representados
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-48 overflow-y-auto">
                        <div v-for="valor in valoresOptions" :key="valor" 
                             class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <Checkbox v-model="formPostulante.valores" 
                                      :inputId="valor" 
                                      name="valor" 
                                      :value="valor"
                                      class="mr-3" />
                            <label :for="valor" class="text-sm text-gray-700 cursor-pointer flex-1">
                                {{ valor }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end space-x-3 pt-4">
                    <Button label="Cancelar" icon="pi pi-times" 
                            class="p-button-text" 
                            @click="showPostulanteModal = false" />
                    <Button label="Guardar" icon="pi pi-check" 
                            class="p-button-primary"
                            @click="savePostulante" />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
/* Estilos adicionales si es necesario */
.transition-all {
    transition: all 0.3s ease;
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}
</style>