<script setup>
import { ref, onMounted } from 'vue';
// Importamos nuestra nueva instancia centralizada de Axios
import apiClient from '../api/axios';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

// Componentes de PrimeVue
import Button from 'primevue/button';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Toolbar from 'primevue/toolbar';

// --- CONFIGURACIÓN ---
const toast = useToast();
const confirm = useConfirm();
// La variable apiUrl ha sido eliminada de aquí

// --- ESTADO REACTIVO ---
const empleados = ref([]);
const isLoading = ref(true);
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

// --- LÓGICA DE API ---
const fetchEmpleados = async () => {
    isLoading.value = true;
    try {
        // Usamos apiClient y solo especificamos el endpoint relativo
        const response = await apiClient.get('/empleados-habiles');
        console.log(response.data);
        empleados.value = response.data;
    } catch (error) {
        console.log(error);
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los empleados.', life: 3000 });
    } finally {
        isLoading.value = false;
    }
};

const saveEmpleado = async () => {
    try {
        if (isEditing.value) {
            // Actualizar empleado existente
            await apiClient.put(`/empleados-habiles/${empleadoForm.value.id}`, empleadoForm.value);
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Empleado actualizado correctamente.', life: 3000 });
        } else {
            // Crear nuevo empleado
            await apiClient.post('/empleados-habiles', empleadoForm.value);
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Empleado creado correctamente.', life: 3000 });
        }
        showModal.value = false;
        fetchEmpleados(); // Recargar la lista de empleados
    } catch (error) {
        const messages = error.response?.data?.errors 
            ? Object.values(error.response.data.errors).flat().join(' ') 
            : 'Ocurrió un error al guardar el empleado.';
        toast.add({ severity: 'error', summary: 'Error de Validación', detail: messages, life: 5000 });
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
                await apiClient.delete(`/empleados-habiles/${empleado.id}`);
                toast.add({ severity: 'warn', summary: 'Eliminado', detail: 'Empleado eliminado.', life: 3000 });
                fetchEmpleados(); // Recargar la lista
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar el empleado.', life: 3000 });
            }
        },
    });
};

// --- MANEJO DE UI ---
const openNewModal = () => {
    isEditing.value = false;
    empleadoForm.value = { // Resetear el formulario
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
    empleadoForm.value = { ...empleado }; // Copiar datos del empleado al formulario
    showModal.value = true;
};

// --- HOOK DE CICLO DE VIDA ---
onMounted(() => {
    fetchEmpleados();
});
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
                        <Button label="Nuevo Empleado" icon="pi pi-plus" class="p-button-primary" @click="openNewModal" />
                    </template>
                </Toolbar>

                <div v-if="isLoading" class="flex justify-center items-center py-8">
                    <ProgressSpinner />
                </div>

                <DataTable v-else :value="empleados" responsiveLayout="scroll" class="p-datatable-sm" stripedRows paginator :rows="10">
                    <Column field="nombre_completo" header="Nombre Completo" sortable style="min-width: 16rem"></Column>
                    <Column field="cedula" header="Cédula" sortable></Column>
                    <Column field="cargo" header="Cargo" sortable></Column>
                    <Column field="grupo_ocupacional" header="Grupo Ocupacional" sortable></Column>
                    <Column field="lugar_de_funciones" header="Lugar de Funciones" sortable></Column>
                    <Column header="Acciones" style="width: 120px">
                        <template #body="slotProps">
                            <div class="flex space-x-2">
                                <Button icon="pi pi-pencil" class="p-button-text p-button-sm" v-tooltip="'Editar'" @click="openEditModal(slotProps.data)" />
                                <Button icon="pi pi-trash" class="p-button-text p-button-sm p-button-danger" v-tooltip="'Eliminar'" @click="deleteEmpleado(slotProps.data)" />
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

        <!-- Modal para Crear/Editar Empleado -->
        <Dialog v-model:visible="showModal" :header="isEditing ? 'Editar Empleado' : 'Nuevo Empleado'" :modal="true" class="w-full max-w-2xl mx-4">
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

