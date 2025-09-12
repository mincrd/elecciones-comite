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

    <!-- 🔹 Header institucional -->
    <header class="header-logos">
        <div class="header-content flex flec-row">
            <img src="/RD-Cultura.png" alt="Ministerio de Cultura" class="logo" />
            <h1 class="header-title">Votación del Comité de Ética</h1>
        </div>
    </header>

    <!-- 🔹 Contenedor principal -->
    <div class="votacion-container">
        <Card class="votacion-card shadow-2xl rounded-2xl overflow-hidden">
            <template #content>
                 <div class="card-content">
                    <transition name="fade" mode="out-in">
                        <!-- Paso 1 -->
                        <div v-if="currentStep === 1" key="step1" class="step-box">
                            <p class="text-center text-black">Para participar, ingrese su cédula de identidad.</p>

                            <div class="input-wrapper">
                                <div class="p-inputgroup">
                                    <!-- <span class="p-inputgroup-addon"><i class="pi pi-id-card"></i></span> -->
                                    <InputText
                                        id="cedula"
                                        v-model="identificacionForm.cedula"
                                        placeholder="Ingrese su cédula sin guiones"
                                        class="w-1/2"
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

                        <!-- Paso 2 -->
                        <div v-else-if="currentStep === 2" key="step2" class="text-center w-full flex flex-col items-center">
                            <h2 class="text-2xl font-bold mt-4">¡Bienvenido(a), {{ votanteInfo.nombre }}!</h2>

                            <div v-if="votanteInfo" class="votante-info-card mt-6">
                                <div class="flex flex-col gap-2">
                                <p v-if="votanteInfo.cargo">
                                    <strong>Cargo:</strong> {{ votanteInfo.cargo }}
                                </p>
                                <p v-if="votanteInfo.lugar_trabajo">
                                    <strong>Ubicación:</strong> {{ votanteInfo.lugar_trabajo }}
                                </p>
                                <p class="mt-2">
                                    <Chip 
                                    v-if="votanteInfo.yaVoto" 
                                        label="Voto ya registrado" 
                                        icon="pi pi-check-circle" 
                                        class="custom-chip-success" 
                                    />
                                    <Chip 
                                        v-else 
                                        label="Habilitado para votar" 
                                        icon="pi pi-verified" 
                                        class="bg-blue-100 text-blue-800" 
                                    />
                                </p>
                                </div>

                                <div class="mt-4 w-full flex justify-center">
                                <Button 
                                    v-if="votanteInfo.yaVoto" 
                                    label="Finalizar" 
                                    icon="pi pi-home" 
                                    class="p-button-success w-60" 
                                    @click="reiniciarProceso" 
                                />
                                <Button 
                                    v-else 
                                    label="Iniciar Votación" 
                                    icon="pi pi-arrow-right" 
                                    class="p-button-primary w-1/2" 
                                    @click="handleIniciarVotacion" 
                                    :loading="isLoading" 
                                />
                                </div>
                            </div>
                        </div>


                        <!-- Paso 3 -->
                        <div v-else-if="currentStep === 3" key="step3">
                            <p class="text-center text-black mb-5">Seleccione el candidato de su preferencia para su grupo ocupacional.</p>
                            <div v-if="candidatos.length > 0" 
     class="grid grid-cols-3 gap-6 candidate-list">
     
  <div v-for="candidato in candidatos" 
       :key="candidato.id"
       class="candidate-card"
       :class="{'selected': selectedCandidato === candidato.id}"
       @click="selectedCandidato = candidato.id">

      <RadioButton 
        v-model="selectedCandidato" 
        :inputId="candidato.id.toString()" 
        name="candidato" 
        :value="candidato.id" 
      />

      <div class="ml-4 flex-grow">
        <label :for="candidato.id.toString()" 
               class="font-bold text-xl text-gray-800">
          {{ candidato.nombre_completo }}
        </label>
        <p class="text-primary-800 font-medium">{{ candidato.cargo }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <Chip v-for="valor in candidato.valores" 
                :key="valor" 
                :label="valor" 
                class="text-xs custom-chip" />
        </div>
      </div>

      <i v-if="selectedCandidato === candidato.id" 
         class="pi pi-check-circle text-2xl text-white check-icon"></i>
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

                        <!-- Paso 4 -->
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
/* 🔹 Fondo degradado claro */
body {
    background: linear-gradient(to bottom, #e6edfb, #c2d3f7, #7ea0eb);
    margin: 0;
}

/* 🔹 Header sin fondo con contenido centrado */
.header-logos {
    padding: 1.5rem 0;
    margin-bottom: 2rem;
}

.header-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    justify-content: center;
}

.logo {
    height: 80px;
    object-fit: contain;
}

.header-title {
    font-size: 2.5rem;
    font-weight: bold;
    color: #1f2937;
    margin: 0;
    text-align: center;
    flex-grow: 1;
}

/* 🔹 Contenedor principal con ancho máximo */
.votacion-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
}

/* 🔹 Card principal */
.votacion-card {
    width: 100%;
    max-width: 800px;
}

.card-content {
    /* padding: 2rem; */
}

/* 🔹 Caja de info votante */
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

/* 🔹 Cards de candidatos */
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

/* 🔹 Animaciones fade */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

/* 🔹 Espacios inputs y botones */
.p-inputgroup .p-inputgroup-addon {
  margin-right: 8px;
}
.input-wrapper {
  margin: 1rem 0;
}
.button-wrapper {
  margin-top: 1.5rem;
}
.btn-full {
  width: 50%;
}

/* 🔹 Responsive */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .header-title {
        font-size: 2rem;
    }
    
    .votacion-container {
        padding: 0 1rem 2rem;
    }
    
    .card-content {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .header-title {
        font-size: 1.5rem;
    }
    
    .card-content {
        padding: 1rem;
    }
}
</style>
