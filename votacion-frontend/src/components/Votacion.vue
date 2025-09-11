<script setup>
import { ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useVotacionStore } from '@/stores/votacionStore';
import { useToast } from 'primevue/usetoast';

// Componentes de PrimeVue
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import RadioButton from 'primevue/radiobutton';
import Chip from 'primevue/chip';
import Toast from 'primevue/toast';

const toast = useToast();
const votacionStore = useVotacionStore();
const { currentStep, isLoading, candidatos, votanteInfo } = storeToRefs(votacionStore);

const identificacionForm = ref({ cedula: '' });
const selectedCandidato = ref(null);

// ✅ LÓGICA RESTAURADA
const submitVerificacion = async () => {
    if (!identificacionForm.value.cedula.trim()) {
        toast.add({ severity: 'warn', summary: 'Atención', detail: 'Debe ingresar su número de cédula.', life: 3000 });
        return;
    }
    try {
        await votacionStore.getEstadoVotante(identificacionForm.value.cedula);
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error de Verificación', detail: error.message, life: 4000 });
    }
};

const handleIniciarVotacion = async () => {
    if (!votanteInfo.value) return;
    try {
        await votacionStore.fetchCandidatos(votanteInfo.value.grupo_ocupacional);
    } catch (error) {
         toast.add({ severity: 'error', summary: 'Error', detail: error.message, life: 4000 });
    }
}

const submitVoto = async () => {
    if (!selectedCandidato.value || !votanteInfo.value) {
        toast.add({ severity: 'warn', summary: 'Atención', detail: 'Debe seleccionar un candidato.', life: 3000 });
        return;
    }
    try {
        // Corregido para enviar la cédula correcta
        await votacionStore.handleVoto({
            cedula: identificacionForm.value.cedula,
            postulanteId: selectedCandidato.value
        });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error al Votar', detail: error.message, life: 4000 });
    }
};

// ✅ LÓGICA RESTAURADA
const reiniciarProceso = () => {
    votacionStore.resetStore();
    identificacionForm.value = { cedula: '' };
    selectedCandidato.value = null;
};
</script>
<template>
    <Toast position="top-center" />
    <div class="votacion-container">
        <Card class="w-full max-w-2xl shadow-2xl rounded-2xl overflow-hidden">
            <template #content>
                 <div class="p-4 sm:p-8">
                    <header class="text-center mb-8">
                        <i class="pi pi-check-to-slot text-5xl text-primary"></i>
                        <h1 class="text-3xl font-bold text-gray-800 mt-4">Votación del Comité de Ética</h1>
                    </header>

                    <transition name="fade" mode="out-in">
                        <div v-if="currentStep === 1" key="step1" class="step-box">
  <p class="text-center">Para participar, ingrese su cédula de identidad.</p>

  <div class="input-wrapper">
    <div class="p-inputgroup">
      <span class="p-inputgroup-addon"><i class="pi pi-id-card"></i></span>
      <InputText
        id="cedula"
        v-model="identificacionForm.cedula"
        placeholder="Ingrese su cédula sin guiones"
        @keyup.enter="submitVerificacion"
      />
    </div>
  </div>

  <div class="button-wrapper">
    <Button
      label="Verificar Identidad"
      icon="pi pi-user-check"
      iconPos="right"
      class="btn-full"
      @click="submitVerificacion"
      :loading="isLoading"
    />
  </div>
</div>


                        <div v-else-if="currentStep === 2" key="step2" class="text-center">
                            <div v-if="votanteInfo">
                                <i class="pi pi-user text-4xl text-primary"></i>
                                <h2 class="text-2xl font-bold mt-4">¡Bienvenido(a), {{ votanteInfo.nombre }}!</h2>
                                
                                <div class="votante-info-box">
                                    <p v-if="votanteInfo.cargo">
                                        <i class="pi pi-briefcase" style="font-size: 1rem; margin-right: 8px;"></i>
                                        <strong>Cargo: </strong> {{ votanteInfo.cargo }}
                                    </p>
                                    <p v-if="votanteInfo.lugar_trabajo">
                                        <i class="pi pi-building" style="font-size: 1rem; margin-right: 8px;"></i>
                                        <strong>Ubicación: </strong> {{ votanteInfo.lugar_trabajo }}
                                    </p>
                                </div>
                                
                                <div v-if="votanteInfo.yaVoto" class="mt-4">
                                <!-- Chip -->
                                <Chip 
                                    label="Voto ya registrado" 
                                    icon="pi pi-check-circle"
                                    class="custom-chip-success"
                                />

                                <!-- Texto -->
                                <p class="text-gray-600 mt-4">
                                    Usted ya ha participado en este proceso de votación. 
                                    Gracias por su contribución.
                                </p>

                                <!-- Botón -->
                                <Button 
                                    label="Finalizar" 
                                    icon="pi pi-home" 
                                    class="mt-6 p-button-secondary custom-btn-finalizar"
                                    @click="reiniciarProceso" 
                                />
                                </div>

                                <div v-else class="mt-4">
                                     <Chip label="Habilitado para Votar" icon="pi pi-verified" class="bg-blue-100 text-blue-800" />
                                     <p class="text-gray-600 mt-4">Hemos confirmado sus datos. Presione el botón para continuar y ver los candidatos.</p>
                                     <Button label="Iniciar Votación" icon="pi pi-arrow-right" class="mt-6" @click="handleIniciarVotacion" :loading="isLoading" />
                                </div>
                            </div>
                        </div>

                        <div v-else-if="currentStep === 3" key="step3">
                            <p class="text-center text-gray-600 mb-8">Seleccione el candidato de su preferencia para su grupo ocupacional.</p>
                            <div v-if="candidatos.length > 0" class="space-y-4 candidate-list">
                                <div v-for="candidato in candidatos" :key="candidato.id"
                                     class="candidate-card"
                                     :class="{'selected': selectedCandidato === candidato.id}"
                                     @click="selectedCandidato = candidato.id">
                                    <RadioButton v-model="selectedCandidato" :inputId="candidato.id.toString()" name="candidato" :value="candidato.id" />
                                    <div class="ml-4 flex-grow">
                                        <label :for="candidato.id.toString()" class="font-bold text-xl text-gray-800">{{ candidato.nombre_completo }}</label>
                                        <p class="text-primary-800 font-medium">{{ candidato.cargo }}</p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <Chip v-for="valor in candidato.valores" :key="valor" :label="valor" class="text-xs custom-chip" />
                                        </div>
                                    </div>
                                    <i v-if="selectedCandidato === candidato.id" class="pi pi-check-circle text-2xl text-white check-icon"></i>
                                </div>
                            </div>
                            <div v-else class="text-center py-8">
                                <i class="pi pi-info-circle text-4xl text-gray-400"></i>
                                <p class="text-gray-500 mt-4">No hay candidatos registrados para su grupo ocupacional en este proceso.</p>
                            </div>
                            <div class="mt-8">
                                <Button label="Confirmar Voto" icon="pi pi-check" class="w-full p-button-success p-button-lg" @click="submitVoto" :loading="isLoading" :disabled="!candidatos.length || !selectedCandidato" />
                            </div>
                        </div>

                        <div v-else-if="currentStep === 4" key="step4" class="text-center py-8">
                             <i class="pi pi-verified text-8xl text-green-500 animate-bounce-in"></i>
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
    background-color: #f0f4f8;
}

.votante-info-box {
    background-color: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1rem;
    margin: 1.5rem auto;
    max-width: 400px;
    text-align: left;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.votante-info-box p {
    margin: 0;
    color: #4b5563;
    display: flex;
    align-items: center;
}
.candidate-card {
    position: relative;
    display: flex;
    align-items: center;
    padding: 1.5rem;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    cursor: pointer;
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    background-color: #ffffff;
    overflow: hidden;
}

.candidate-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.07);
    border-color: var(--primary-color);
}
.candidate-card.selected {
    border-color: var(--primary-color);
    background: var(--primary-color);
    color: white;
    box-shadow: 0 12px 30px rgba(59, 130, 246, 0.4);
    transform: translateY(-2px) scale(1.01);
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
    color: var(--primary-color);
}
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

/* Espacio entre ícono y input */
.p-inputgroup .p-inputgroup-addon {
  margin-right: 8px; /* separa el icono del input */
}

/* Wrapper del input */
.input-wrapper {
  margin: 1rem 0; /* separa del texto superior */
}

/* Wrapper del botón */
.button-wrapper {
  margin-top: 1.5rem; /* separa el botón del input */
}

/* Botón ocupa todo el ancho */
.btn-full {
  width: 100%;
}


</style>