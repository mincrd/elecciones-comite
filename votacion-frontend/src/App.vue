<script setup>
import { ref } from 'vue';
import axios from 'axios';

// Componentes y Servicios de PrimeVue
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import RadioButton from 'primevue/radiobutton';
import Toast from 'primevue/toast';
import ProgressSpinner from 'primevue/progressspinner';
import Chip from 'primevue/chip';
import { useToast } from 'primevue/usetoast';

// --- CONFIGURACIÓN ---
const toast = useToast();
const apiUrl = 'http://127.0.0.1:8000/api/votacion';

// --- ESTADO DE LA APLICACIÓN ---
const currentStep = ref(1); // 1: Identificación, 2: Votación, 3: Agradecimiento
const isLoading = ref(false);
const errorMessage = ref('');

// --- DATOS DEL FORMULARIO ---
const identificacionForm = ref({
    email: '',
    no_empleado: '',
    grupo_ocupacional: null,
});
const grupoOcupacionalOptions = ref(['I', 'II', 'III', 'IV', 'V']);

// --- DATOS DE VOTACIÓN ---
const candidatos = ref([]);
const selectedCandidato = ref(null);

// --- LÓGICA DE API ---

// 1. Identificar al votante y obtener el token de acceso
const handleIdentificacion = async () => {
    if (!identificacionForm.value.email && !identificacionForm.value.no_empleado) {
        toast.add({ severity: 'warn', summary: 'Atención', detail: 'Debe ingresar su email o número de empleado.', life: 3000 });
        return;
    }
    if (!identificacionForm.value.grupo_ocupacional) {
        toast.add({ severity: 'warn', summary: 'Atención', detail: 'Debe seleccionar su grupo ocupacional.', life: 3000 });
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.post(`${apiUrl}/identificar`, identificacionForm.value);
        const token = response.data.token;
        localStorage.setItem('authToken', token); // Guardamos el token

        await fetchCandidatos();
        currentStep.value = 2; // Pasar al siguiente paso
    } catch (error) {
        const message = error.response?.data?.message || 'Error al intentar identificarse. Verifique sus datos.';
        errorMessage.value = message;
        toast.add({ severity: 'error', summary: 'Error de Identificación', detail: message, life: 4000 });
    } finally {
        isLoading.value = false;
    }
};

// 2. Obtener la lista de candidatos para el grupo del votante
const fetchCandidatos = async () => {
    const token = localStorage.getItem('authToken');
    if (!token) {
        errorMessage.value = 'No se encontró el token de autenticación.';
        return;
    }

    try {
        const response = await axios.get(`${apiUrl}/candidatos`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        candidatos.value = response.data;
    } catch (error) {
        const message = error.response?.data?.message || 'No se pudieron cargar los candidatos.';
        errorMessage.value = message;
        currentStep.value = 1; // Devolver al inicio si hay error
        localStorage.removeItem('authToken');
        toast.add({ severity: 'error', summary: 'Error', detail: message, life: 4000 });
    }
};

// 3. Registrar el voto
const handleVoto = async () => {
    if (!selectedCandidato.value) {
        toast.add({ severity: 'warn', summary: 'Atención', detail: 'Debe seleccionar un candidato para votar.', life: 3000 });
        return;
    }

    isLoading.value = true;
    const token = localStorage.getItem('authToken');

    try {
        await axios.post(`${apiUrl}/votar`, 
            { postulante_id: selectedCandidato.value },
            {
                headers: { 'Authorization': `Bearer ${token}` }
            }
        );
        currentStep.value = 3; // Pasar al paso de agradecimiento
    } catch (error) {
        const message = error.response?.data?.message || 'Ocurrió un error al registrar su voto.';
        toast.add({ severity: 'error', summary: 'Error al Votar', detail: message, life: 4000 });
        // Si el error es por voto duplicado, también lo llevamos a la pantalla final
        if (error.response?.status === 403) {
            currentStep.value = 3;
        }
    } finally {
        isLoading.value = false;
        localStorage.removeItem('authToken'); // Limpiar el token después del intento
    }
};

const reiniciarProceso = () => {
    currentStep.value = 1;
    identificacionForm.value = { email: '', no_empleado: '', grupo_ocupacional: null };
    candidatos.value = [];
    selectedCandidato.value = null;
    errorMessage.value = '';
}

</script>

<template>
    <Toast />
    <div class="flex items-center justify-center min-h-screen bg-gray-100 p-4">
        <Card class="w-full max-w-lg shadow-2xl">
            <template #title>
                <div class="text-center">
                    <h1 class="text-2xl font-bold text-gray-800">Votación del Comité de Ética</h1>
                </div>
            </template>
            <template #content>
                <!-- Paso 1: Identificación -->
                <div v-if="currentStep === 1">
                    <p class="text-center text-gray-600 mb-6">Para continuar, por favor ingrese sus datos y seleccione su grupo ocupacional.</p>
                    <div class="space-y-4">
                        <div class="p-fluid">
                            <label for="email">Correo Electrónico</label>
                            <InputText id="email" v-model="identificacionForm.email" type="email" placeholder="ejemplo@correo.com" />
                        </div>
                        <div class="text-center text-gray-500 font-semibold">O</div>
                        <div class="p-fluid">
                            <label for="no_empleado">Número de Empleado</label>
                            <InputText id="no_empleado" v-model="identificacionForm.no_empleado" placeholder="Ej: 12345" />
                        </div>
                        <div class="p-fluid mt-4">
                            <label for="grupo">Grupo Ocupacional</label>
                            <Dropdown id="grupo" v-model="identificacionForm.grupo_ocupacional" :options="grupoOcupacionalOptions" placeholder="Seleccione su grupo" />
                        </div>
                    </div>
                    <div class="mt-6">
                        <Button label="Ingresar para Votar" icon="pi pi-arrow-right" class="w-full" @click="handleIdentificacion" :loading="isLoading" />
                    </div>
                </div>

                <!-- Paso 2: Votación -->
                <div v-if="currentStep === 2">
                     <p class="text-center text-gray-600 mb-6">Seleccione el candidato de su preferencia.</p>
                     <div v-if="candidatos.length > 0" class="space-y-4">
                        <div v-for="candidato in candidatos" :key="candidato.id" 
                             class="p-4 border rounded-lg flex items-center cursor-pointer transition-colors"
                             :class="{'bg-indigo-50 border-indigo-500': selectedCandidato === candidato.id}"
                             @click="selectedCandidato = candidato.id">
                            <RadioButton v-model="selectedCandidato" :inputId="candidato.id.toString()" name="candidato" :value="candidato.id" />
                            <div class="ml-4">
                                <label :for="candidato.id.toString()" class="font-bold text-lg text-gray-800">{{ candidato.nombre_completo }}</label>
                                <p class="text-gray-600">{{ candidato.cargo }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <Chip v-for="valor in candidato.valores" :key="valor" :label="valor" class="text-xs" />
                                </div>
                            </div>
                        </div>
                     </div>
                     <div v-else class="text-center py-8">
                         <p class="text-gray-500">No hay candidatos registrados para su grupo ocupacional en este proceso.</p>
                     </div>
                     <div class="mt-6">
                        <Button label="Emitir Voto" icon="pi pi-check" class="w-full" @click="handleVoto" :loading="isLoading" :disabled="!candidatos.length" />
                    </div>
                </div>

                <!-- Paso 3: Agradecimiento -->
                <div v-if="currentStep === 3" class="text-center py-8">
                    <i class="pi pi-check-circle text-6xl text-green-500"></i>
                    <h2 class="mt-4 text-2xl font-bold text-gray-800">¡Gracias por su participación!</h2>
                    <p class="text-gray-600 mt-2">Su voto ha sido registrado exitosamente.</p>
                    <Button label="Volver al Inicio" icon="pi pi-home" class="mt-6 p-button-text" @click="reiniciarProceso" />
                </div>
            </template>
        </Card>
    </div>
</template>

<style>
body, #app {
    background-color: #f3f4f6;
}
</style>
