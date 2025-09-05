<script setup>
import { ref, onMounted, computed } from 'vue';
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
import ProgressBar from 'primevue/progressbar';
import ConfirmDialog from 'primevue/confirmdialog';
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
let resultadosInterval = null;

// Opciones para formularios
const valoresOptions = ref(['Colaboración', 'Compromiso', 'Confiabilidad', 'Disciplina', 'Discreción', 'Honestidad', 'Honorabilidad', 'Honradez', 'Probidad', 'Rectitud', 'Responsabilidad', 'Trabajo en equipo', 'Vocación al servicio']);
const grupoOcupacionalOptions = ref(['I', 'II', 'III', 'IV', 'V']);

// Formularios
const showProcesoModal = ref(false);
const formProceso = ref({ id: null, ano: new Date().getFullYear(), desde: '', hasta: '', estado: 'Cerrado' });

const showPostulanteModal = ref(false);
const formPostulante = ref({ id: null, nombre_completo: '', cargo: '', email: '', telefono: '', grupo_ocupacional: '', valores: [] });

// --- LÓGICA DE API ---
const fetchProcesos = async () => {
    try {
        const response = await axios.get(`${apiUrl}/procesos`);
        procesos.value = response.data.data;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los procesos.', life: 3000 });
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
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar el proceso.', life: 3000 });
    }
};

const fetchPostulantes = async (procesoId) => {
    try {
        const response = await axios.get(`${apiUrl}/postulantes?proceso_id=${procesoId}`);
        postulantes.value = response.data.data;
    } catch (error) {
        postulantes.value = [];
    }
};

// ** FUNCIÓN DE CARGA DE DATOS REFORZADA **
const fetchResultados = async (procesoId) => {
    try {
        const response = await axios.get(`${apiUrl}/resultados/${procesoId}`);
        // Se asegura de que la respuesta sea un array antes de asignarla.
        if (response && Array.isArray(response.data)) {
            resultados.value = response.data;
        } else {
            // Si no, asigna un array vacío para evitar errores.
            resultados.value = [];
        }
    } catch (error) {
        // En cualquier caso de error, siempre será un array vacío.
        resultados.value = [];
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los resultados.', life: 3000 });
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
        fetchPostulantes(selectedProceso.value.id);
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar el postulante.', life: 3000 });
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
                fetchPostulantes(selectedProceso.value.id);
                fetchResultados(selectedProceso.value.id);
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar el postulante.', life: 3000 });
            }
        },
    });
};

// --- MANEJO DE UI ---
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

const selectProceso = (proceso) => {
    selectedProceso.value = proceso;
    postulantes.value = [];
    resultados.value = [];

    fetchPostulantes(proceso.id);
    fetchResultados(proceso.id);

    if (resultadosInterval) clearInterval(resultadosInterval);
    resultadosInterval = setInterval(() => {
        if (selectedProceso.value) fetchResultados(selectedProceso.value.id);
    }, 10000);
};

// --- COMPUTED PROPERTIES ---
const totalVotos = computed(() => {
    if (!Array.isArray(resultados.value)) return 0;
    return resultados.value.reduce((total, item) => total + (item.total_votos || 0), 0);
});

const getPorcentajeVotos = (votos) => {
    if (totalVotos.value === 0) return 0;
    return (votos / totalVotos.value) * 100;
};

// --- LIFECYCLE HOOKS ---
onMounted(fetchProcesos);
</script>

<template>
    <Toast />
    <ConfirmDialog />
    <div class="p-4 sm:p-8 max-w-7xl mx-auto bg-gray-50 min-h-screen">
        <header class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800">Panel de Administración</h1>
            <p class="text-gray-500">Gestión del Comité de Ética</p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Columna de Procesos -->
            <div class="lg:col-span-1">
                <Card>
                    <template #title>
                        <div class="flex justify-between items-center">
                            <span>Procesos</span>
                            <Button icon="pi pi-plus" label="Nuevo" @click="openProcesoModal()" />
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-3">
                            <div v-for="proceso in procesos" :key="proceso.id"
                                @click="selectProceso(proceso)"
                                class="p-4 border rounded-lg cursor-pointer transition-all hover:shadow-md"
                                :class="{'bg-indigo-50 border-indigo-500': selectedProceso?.id === proceso.id}">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-gray-800">Año: {{ proceso.ano }}</p>
                                        <p class="text-sm text-gray-500">{{ proceso.desde }} al {{ proceso.hasta }}</p>
                                    </div>
                                    <Tag :value="proceso.estado" :severity="proceso.estado === 'Abierto' ? 'success' : 'danger'" />
                                </div>
                                <div class="mt-3 text-right">
                                    <Button label="Editar" icon="pi pi-pencil" class="p-button-text p-button-sm" @click.stop="openProcesoModal(proceso)" />
                                </div>
                            </div>
                            <p v-if="!procesos.length" class="text-center text-gray-500 py-4">No hay procesos creados.</p>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Columna de Detalles -->
            <div class="lg:col-span-2 space-y-8">
                <div v-if="selectedProceso">
                    <!-- Postulantes -->
                    <Card>
                        <template #title>
                            <div class="flex justify-between items-center">
                                <span>Postulantes del Proceso {{ selectedProceso.ano }}</span>
                                <Button icon="pi pi-user-plus" label="Agregar" @click="openPostulanteModal()" severity="secondary"/>
                            </div>
                        </template>
                        <template #content>
                            <DataTable :value="postulantes" responsiveLayout="scroll" size="small">
                                <Column field="nombre_completo" header="Nombre"></Column>
                                <Column field="grupo_ocupacional" header="Grupo"></Column>
                                <Column field="email" header="Email"></Column>
                                <Column header="Acciones">
                                    <template #body="slotProps">
                                        <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click="openPostulanteModal(slotProps.data)" />
                                        <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click="deletePostulante(slotProps.data.id)" />
                                    </template>
                                </Column>
                                <template #empty>No hay postulantes para este proceso.</template>
                            </DataTable>
                        </template>
                    </Card>
                    <!-- Resultados -->
                    <Card>
                        <template #title>
                            <div class="flex justify-between items-center">
                                <span>Resultados en Tiempo Real</span>
                                <Button icon="pi pi-refresh" class="p-button-rounded p-button-text" @click="fetchResultados(selectedProceso.id)" />
                            </div>
                        </template>
                        <template #content>
                            <!-- ** PLANTILLA REFACTORIZADA Y MÁS SEGURA ** -->
                            <div class="space-y-4">
                                <!-- El v-for simplemente no se ejecutará si `resultados` está vacío, evitando el error. -->
                                <div v-for="resultado in resultados" :key="resultado.id">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="font-semibold">{{ resultado.nombre_completo }}</p>
                                        <p class="font-bold text-indigo-600">{{ resultado.total_votos }} Votos</p>
                                    </div>
                                    <ProgressBar :value="getPorcentajeVotos(resultado.total_votos)" />
                                </div>
                            </div>
                             <!-- El mensaje de "no hay votos" se muestra por separado, verificando la longitud de forma segura. -->
                            <p v-if="!resultados || resultados.length === 0" class="text-center text-gray-500 py-4">Aún no hay votos registrados.</p>
                        </template>
                    </Card>
                </div>
                <Card v-else>
                    <template #content>
                        <div class="flex items-center justify-center h-64">
                            <p class="text-gray-500 text-lg">Seleccione un proceso para ver los detalles.</p>
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
.field {
    margin-bottom: 1rem;
}
</style>


