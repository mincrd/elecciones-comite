<script setup>
import { ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useVotacionStore } from '@/stores/votacionStore';

// Componentes de PrimeVue
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import RadioButton from 'primevue/radiobutton';
import Chip from 'primevue/chip';

const votacionStore = useVotacionStore();
const { currentStep, isLoading, candidatos, votanteInfo } = storeToRefs(votacionStore);

const identificacionForm = ref({ cedula: '' });
const selectedCandidato = ref(null);

// Estados de validación para el paso 1
const cedulaError = ref('');
const hasError = ref(false);

// ✅ Verificación de cédula
const submitVerificacion = async () => {
  // Reset error states
  cedulaError.value = '';
  hasError.value = false;

  // Validación local
  if (!identificacionForm.value.cedula.trim()) {
    cedulaError.value = 'Debe ingresar su número de cédula.';
    hasError.value = true;
    return;
  }

  try {
    await votacionStore.getEstadoVotante(identificacionForm.value.cedula);
  } catch (error) {
    cedulaError.value = error.message || 'No se pudo verificar la cédula. Verifique que sea correcta.';
    hasError.value = true;
  }
};

// ✅ Iniciar votación
const handleIniciarVotacion = async () => {
  if (!votanteInfo.value) return;
  try {
    await votacionStore.fetchCandidatos(votanteInfo.value.grupo_ocupacional);
  } catch (error) {
    // Handle error silently or add custom handling here
  }
};

// ✅ Enviar voto
const submitVoto = async () => {
  if (!selectedCandidato.value || !votanteInfo.value) {
    return;
  }
  try {
    await votacionStore.handleVoto({
      cedula: identificacionForm.value.cedula,
      postulanteId: selectedCandidato.value,
    });
  } catch (error) {
    // Handle error silently or add custom handling here
  }
};

// ✅ Reiniciar proceso
const reiniciarProceso = () => {
  votacionStore.resetStore();
  identificacionForm.value = { cedula: '' };
  selectedCandidato.value = null;
  cedulaError.value = '';
  hasError.value = false;
};

// Limpiar errores cuando el usuario empiece a escribir
const onCedulaInput = () => {
  if (hasError.value) {
    cedulaError.value = '';
    hasError.value = false;
  }
};
</script>

<template>
  <!-- 🔹 Paso 1 (login estilo inspirado) -->
  <div
    v-if="currentStep === 1"
    class="h-screen flex justify-center items-center px-6 py-12"
  >
    <div
      class="grid xl:grid-cols-2 grid-cols-1 bg-white shadow-2xl rounded-lg overflow-hidden max-w-4xl w-full"
    >
      <!-- Imagen lateral -->
      <div
        class="hidden xl:block bg-cover bg-center"
        style="background-image: url('/votacion.jpg');"
      ></div>

      <!-- Formulario -->
      <div class="w-full p-8 sm:p-12">
        <div class="mb-8">
          <img src="/RD-Cultura.png" alt="Logo" class="h-24 mx-auto mb-6" />
          <h1 class="text-2xl font-bold text-gray-800 text-center">
            Votación del Comité de Ética
          </h1>
        </div>

        <div class="mb-6">
          <p class="text-center text-black">
            Para participar, ingrese su cédula de identidad.
          </p>

          <div class="input-wrapper mt-4 flex justify-center">
            <div class="p-inputgroup w-3/4">
              <InputText
                id="cedula"
                v-model="identificacionForm.cedula"
                placeholder="Ingrese su cédula sin guiones"
                class="w-full"
                :class="{ 'p-invalid': hasError }"
                @keyup.enter="submitVerificacion"
                @input="onCedulaInput"
              />
            </div>
          </div>

          <!-- Mensaje de error -->
          <div v-if="cedulaError" class="flex justify-center mt-2">
            <div class="w-3/4">
              <small class="p-error text-red-500">
                <i class="pi pi-exclamation-triangle mr-1"></i>
                {{ cedulaError }}
              </small>
            </div>
          </div>

          <div class="button-wrapper mt-6 flex justify-center">
            <Button
              label="Verificar Identidad"
              icon="pi pi-user-check"
              iconPos="right"
              class="w-3/4"
              @click="submitVerificacion"
              :loading="isLoading"
            />
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 🔹 Paso 2 en adelante -->
  <div v-else>
    <!-- Header -->
    <header class="header-logos">
      <div class="header-content flex flec-row">
        <img
          src="/RD-Cultura.png"
          alt="Ministerio de Cultura"
          class="logo"
        />
        <h1 class="header-title">Votación del Comité de Ética</h1>
      </div>
    </header>

    <!-- Contenido de pasos 2, 3 y 4 -->
    <div class="votacion-container">
      <Card class="votacion-card shadow-2xl rounded-2xl overflow-hidden">
        <template #content>
          <div class="card-content">
            <transition name="fade" mode="out-in">
              <!-- Paso 2 -->
              <div
                v-if="currentStep === 2"
                key="step2"
                class="text-center w-full flex flex-col items-center"
              >
                <h2 class="text-2xl font-bold mt-4">
                  ¡Bienvenido(a), {{ votanteInfo.nombre }}!
                </h2>
                <div
                  v-if="votanteInfo"
                  class="votante-info-card mt-6"
                >
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
                <p class="text-center text-black mb-5">
                  Seleccione el candidato de su preferencia para su grupo
                  ocupacional.
                </p>
                <div
                  v-if="candidatos.length > 0"
                  class="grid grid-cols-3 gap-6 candidate-list"
                >
                  <div
                    v-for="candidato in candidatos"
                    :key="candidato.id"
                    class="candidate-card"
                    :class="{ selected: selectedCandidato === candidato.id }"
                    @click="selectedCandidato = candidato.id"
                  >
                    <RadioButton
                      v-model="selectedCandidato"
                      :inputId="candidato.id.toString()"
                      name="candidato"
                      :value="candidato.id"
                    />
                    <div class="ml-4 flex-grow">
                      <label
                        :for="candidato.id.toString()"
                        class="font-bold text-xl text-gray-800"
                      >
                        {{ candidato.nombre_completo }}
                      </label>
                      <p class="text-primary-800 font-medium">
                        {{ candidato.cargo }}
                      </p>
                      <div class="mt-3 flex flex-wrap gap-2">
                        <Chip
                          v-for="valor in candidato.valores"
                          :key="valor"
                          :label="valor"
                          class="text-xs custom-chip"
                        />
                      </div>
                    </div>
                    <i
                      v-if="selectedCandidato === candidato.id"
                      class="pi pi-check-circle text-2xl text-green-500 check-icon"
                    ></i>
                  </div>
                </div>

                <div v-else class="text-center py-8">
                  <i class="pi pi-info-circle text-4xl text-gray-400"></i>
                  <p class="text-gray-500 mt-4">
                    No hay candidatos registrados para su grupo ocupacional en
                    este proceso.
                  </p>
                </div>

                <div class="mt-8">
                  <Button
                    label="Confirmar Voto"
                    icon="pi pi-check"
                    class="w-full p-button-success p-button-lg"
                    @click="submitVoto"
                    :loading="isLoading"
                    :disabled="!candidatos.length || !selectedCandidato"
                  />
                </div>
              </div>

              <!-- Paso 4 -->
              <div
                v-else-if="currentStep === 4"
                key="step4"
                class="text-center py-8"
              >
                <i
                  class="pi pi-verified text-8xl text-green-500 animate-bounce-in"
                ></i>
                <h2 class="mt-6 text-3xl font-bold text-gray-800">
                  ¡Gracias por su participación!
                </h2>
                <p class="text-gray-600 mt-2 text-lg">
                  Su voto ha sido registrado exitosamente.
                </p>
                <Button
                  label="Volver al Inicio"
                  icon="pi pi-home"
                  class="mt-8 p-button-text p-button-lg"
                  @click="reiniciarProceso"
                />
              </div>
            </transition>
          </div>
        </template>
      </Card>
    </div>
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
    align-items: flex-start;
    padding: 1.5rem;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    cursor: pointer;
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease, background 0.3s ease;
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
    background: linear-gradient(135deg, #e0edff, #ffffff);
    box-shadow: 0 12px 30px rgba(59, 130, 246, 0.3);
    transform: translateY(-2px) scale(1.01);
}
.candidate-card.selected label {
    color: var(--primary-color) !important;
}
.candidate-card.selected p {
    color: #1e3a8a !important;
}
.check-icon {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.8rem;
    color: var(--primary-color);
}
.custom-chip {
    background-color: #eff6ff;
    color: var(--primary-color);
    font-weight: 500;
}
.candidate-card.selected .custom-chip {
    background-color: var(--primary-color);
    color: #ffffff;
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
