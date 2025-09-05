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
    <Toast position="top-center" />
    <div class="votacion-container">
        <Card class="w-full max-w-2xl shadow-2xl rounded-2xl">
            <template #content>
                <div class="p-4 sm:p-8">
                    <header class="text-center mb-8">
                        <i class="pi pi-check-to-slot text-5xl text-blue-500"></i>
                        <h1 class="text-3xl font-bold text-gray-800 mt-4">Votación del Comité de Ética</h1>
                    </header>

                    <transition name="fade" mode="out-in">
                        <!-- Paso 1: Identificación -->
                        <div v-if="currentStep === 1" key="step1">
                            <p class="text-center text-gray-600 mb-8">Para continuar, por favor ingrese sus datos y seleccione su grupo ocupacional.</p>
                            <div class="space-y-4">
                                <div class="p-fluid">
                                    <label for="email" class="font-semibold">Correo Electrónico</label>
                                    <InputText id="email" v-model="identificacionForm.email" type="email" placeholder="ejemplo@correo.com" class="mt-1" />
                                </div>
                                <div class="divider"><span>O</span></div>
                                <div class="p-fluid">
                                    <label for="no_empleado" class="font-semibold">Número de Empleado</label>
                                    <InputText id="no_empleado" v-model="identificacionForm.no_empleado" placeholder="Ej: 12345" class="mt-1" />
                                </div>
                                <div class="p-fluid mt-4">
                                    <label for="grupo" class="font-semibold">Grupo Ocupacional</label>
                                    <Dropdown id="grupo" v-model="identificacionForm.grupo_ocupacional" :options="grupoOcupacionalOptions" placeholder="Seleccione su grupo" class="mt-1" />
                                </div>
                            </div>
                            <div class="mt-8">
                                <Button label="Ingresar para Votar" icon="pi pi-arrow-right" iconPos="right" class="w-full p-button-lg" @click="handleIdentificacion" :loading="isLoading" />
                            </div>
                        </div>

                        <!-- Paso 2: Votación -->
                        <div v-else-if="currentStep === 2" key="step2">
                            <p class="text-center text-gray-600 mb-8">Seleccione el candidato de su preferencia.</p>
                            <div v-if="candidatos.length > 0" class="space-y-4 candidate-list">
                                <div v-for="candidato in candidatos" :key="candidato.id" 
                                     class="candidate-card"
                                     :class="{'selected': selectedCandidato === candidato.id}"
                                     @click="selectedCandidato = candidato.id">
                                    <RadioButton v-model="selectedCandidato" :inputId="candidato.id.toString()" name="candidato" :value="candidato.id" />
                                    <div class="ml-4 flex-grow">
                                        <label :for="candidato.id.toString()" class="font-bold text-xl text-gray-800">{{ candidato.nombre_completo }}</label>
                                        <p class="text-blue-800 font-medium">{{ candidato.cargo }}</p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <Chip v-for="valor in candidato.valores" :key="valor" :label="valor" class="text-xs custom-chip" />
                                        </div>
                                    </div>
                                    <i v-if="selectedCandidato === candidato.id" class="pi pi-check-circle text-2xl text-white check-icon"></i>
                                </div>
                            </div>
                            <div v-else class="text-center py-8">
                                <p class="text-gray-500">No hay candidatos registrados para su grupo ocupacional en este proceso.</p>
                            </div>
                            <div class="mt-8">
                                <Button label="Confirmar Voto" icon="pi pi-check" class="w-full p-button-success p-button-lg" @click="handleVoto" :loading="isLoading" :disabled="!candidatos.length" />
                            </div>
                        </div>

                        <!-- Paso 3: Agradecimiento -->
                        <div v-else-if="currentStep === 3" key="step3" class="text-center py-8">
                            <i class="pi pi-verified text-8xl text-green-500"></i>
                            <h2 class="mt-6 text-3xl font-bold text-gray-800">¡Gracias por su participación!</h2>
                            <p class="text-gray-600 mt-2 text-lg">Su voto ha sido registrado exitosamente.</p>
                            <Button label="Volver al Inicio" icon="pi pi-home" class="mt-8 p-button-text p-button-lg" @click="reiniciarProceso" />
                        </div>
                    </transition>
                </div>
            </template>
        </Card>
    </div>
</template>

<style scoped>
.votacion-container {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 1rem;
    background: linear-gradient(135deg, #e0f2fe, #f0f9ff);
}

.candidate-card {
    position: relative;
    display: flex;
    align-items: center;
    padding: 1.5rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    background-color: #ffffff;
    overflow: hidden;
}

.candidate-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    border-color: #93c5fd;
}

.candidate-card.selected {
    border-color: #3b82f6;
    background-color: #3b82f6;
    color: white;
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
}

.candidate-card.selected label,
.candidate-card.selected p {
    color: white !important;
}

.check-icon {
    position: absolute;
    top: 50%;
    right: 1.5rem;
    transform: translateY(-50%);
}

.custom-chip {
    background-color: rgba(255, 255, 255, 0.2);
    color: #f0f9ff;
    font-weight: 500;
}

.candidate-card:not(.selected) .custom-chip {
    background-color: #eff6ff;
    color: #3b82f6;
}

.divider {
    display: flex;
    align-items: center;
    text-align: center;
    color: #9ca3af;
    font-weight: 600;
    margin: 1rem 0;
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    border-bottom: 1px solid #d1d5db;
}

.divider:not(:empty)::before {
    margin-right: .5em;
}

.divider:not(:empty)::after {
    margin-left: .5em;
}

/* Transición de desvanecimiento */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>

