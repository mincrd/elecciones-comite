<script setup>
import { ref, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useVotacionStore } from '@/stores/votacionStore';

// PrimeVue
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import RadioButton from 'primevue/radiobutton';
import Chip from 'primevue/chip';
import Message from 'primevue/message';

const votacionStore = useVotacionStore();
const { currentStep, isLoading, candidatos, votanteInfo } = storeToRefs(votacionStore);

const identificacionForm = ref({ cedula: '' });
const selectedCandidato = ref(null);

// Validación paso 1
const cedulaError = ref('');
const hasError = ref(false);

// ¿Puede iniciar votación?
const puedeIniciar = computed(() => {
  const v = votanteInfo.value;
  if (!v) return false;
  // Sólo si está hábil y NO ha votado
  return !!(v.esHabil && !v.yaVoto);
});

// Verificación de cédula (Paso 1)
const submitVerificacion = async () => {
  cedulaError.value = '';
  hasError.value = false;

  if (!identificacionForm.value.cedula.trim()) {
    cedulaError.value = 'Debe ingresar su número de cédula.';
    hasError.value = true;
    return;
  }

  try {
    await votacionStore.getEstadoVotante(identificacionForm.value.cedula);
    // Si el store decide no avanzar, aquí no forzamos nada; la pantalla 2 mostrará el mensaje si ya votó.
  } catch (error) {
    cedulaError.value = 'No se pudo verificar la cédula. Verifique que sea correcta.';
    hasError.value = true;
  }
};

// Iniciar votación (paso 2 → paso 3)
const handleIniciarVotacion = async () => {
  if (!votanteInfo.value) return;

  // Bloqueo explícito si ya votó o no es hábil
  if (!puedeIniciar.value) return;

  try {
    await votacionStore.fetchCandidatos(votanteInfo.value.grupo_ocupacional);
  } catch {
    // manejo silencioso
  }
};

// Enviar voto (paso 3 → paso 4)
const submitVoto = async () => {
  if (!selectedCandidato.value || !votanteInfo.value) return;

  // Defensa extra por si alguien manipula la UI:
  if (votanteInfo.value.yaVoto) return;

  try {
    await votacionStore.handleVoto({
      cedula: identificacionForm.value.cedula,
      postulanteId: selectedCandidato.value,
    });
  } catch {
    // manejo silencioso
  }
};

// Reiniciar proceso
const reiniciarProceso = () => {
  votacionStore.resetStore();
  identificacionForm.value = { cedula: '' };
  selectedCandidato.value = null;
  cedulaError.value = '';
  hasError.value = false;
};

// Limpiar errores al escribir cédula
const onCedulaInput = () => {
  if (hasError.value) {
    cedulaError.value = '';
    hasError.value = false;
  }
};
</script>

<template>
  <!-- Paso 1 -->
  <div v-if="currentStep === 1" class="h-screen flex justify-center items-center px-6">
    <div class="grid xl:grid-cols-2 grid-cols-1 bg-white shadow-2xl rounded-lg overflow-hidden max-w-4xl w-full">
      <!-- Imagen lateral -->
      <div class="hidden xl:block bg-cover bg-center" style="background-image: url('/votacion.jpg');"></div>

      <!-- Formulario -->
      <div class="w-full p-8 sm:p-12">
        <div class="mb-8">
          <img src="/RD-Cultura.png" alt="Logo" class="h-24 mx-auto mb-6" />
          <h1 class="text-2xl font-bold text-gray-800 text-center">Votación del Comité de Ética</h1>
        </div>

        <div class="mb-6">
          <p class="text-center text-black">Para participar, ingrese su cédula de identidad.</p>

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

  <!-- Paso 2+ -->
  <div v-else>
    <header class="header-logos">
      <div class="header-content flex flec-row">
        <img src="/RD-Cultura.png" alt="Ministerio de Cultura" class="logo" />
        <h1 class="header-title">Votación del Comité de Ética</h1>
      </div>
    </header>

    <div class="votacion-container">
      <Card class="votacion-card shadow-2xl rounded-2xl overflow-hidden">
        <template #content>
          <div class="card-content">
            <transition name="fade" mode="out-in">
              <!-- Paso 2 -->
              <div v-if="currentStep === 2" key="step2" class="text-center w-full flex flex-col items-center">
                <h2 class="text-2xl font-bold mt-4">¡Bienvenido(a), {{ votanteInfo?.nombre }}!</h2>

                <div v-if="votanteInfo" class="votante-info-card mt-6 max-w-lg w-full">
                  <!-- Mensajes de estado -->
                  <Message
                    v-if="votanteInfo.yaVoto"
                    severity="warn"
                    :closable="false"
                    class="mb-3"
                  >
                    Ya existe un voto registrado para esta cédula. No podrá iniciar una nueva votación.
                  </Message>

                  <div class="flex flex-col gap-2 text-left">
                    <p v-if="votanteInfo.cargo"><strong>Cargo:</strong> {{ votanteInfo.cargo }}</p>
                    <p v-if="votanteInfo.lugar_trabajo"><strong>Ubicación:</strong> {{ votanteInfo.lugar_trabajo }}</p>
                    <p class="mt-2">
                      <Chip
                        v-if="!votanteInfo.yaVoto"
                        label="Habilitado para votar"
                        icon="pi pi-verified"
                        class="bg-blue-100 text-blue-800"
                      />
                      <Chip
                        v-else
                        label="Ya votó"
                        icon="pi pi-ban"
                        class="bg-red-100 text-red-800"
                      />
                    </p>
                  </div>

                  <div class="mt-4 w-full flex justify-center">
                    <Button
                      label="Iniciar Votación"
                      icon="pi pi-arrow-right"
                      class="p-button-primary w-1/2"
                      @click="handleIniciarVotacion"
                      :loading="isLoading"
                      :disabled="!puedeIniciar"
                    />
                  </div>
                </div>
              </div>

              <!-- Paso 3 -->
              <div v-else-if="currentStep === 3" key="step3">
                <p class="text-center text-black mb-5">
                  Seleccione el candidato de su preferencia para su grupo ocupacional.
                </p>

                <div v-if="candidatos.length > 0" class="grid grid-cols-3 gap-6 candidate-list">
                  <div
                    v-for="candidato in candidatos"
                    :key="candidato.id"
                    class="candidate-card relative"
                    :class="{ selected: selectedCandidato === candidato.id }"
                    @click="selectedCandidato = candidato.id"
                  >
                    <!-- Imagen / Avatar -->
                    <div class="candidate-image">
                      <img
                        v-if="candidato.foto_url"
                        :src="candidato.foto_url"
                        alt="Foto candidato"
                        class="rounded-full w-24 h-24 mx-auto object-cover"
                      />
                      <div v-else class="rounded-full w-24 h-24 mx-auto flex items-center justify-center bg-gray-300">
                        <i class="pi pi-user text-4xl text-white"></i>
                      </div>
                    </div>

                    <!-- Info -->
                    <div class="candidate-info mt-4">
                      <label :for="candidato.id.toString()" class="font-semibold text-lg text-gray-800 block">
                        {{ candidato.nombre_completo }}
                      </label>
                      <p class="text-primary-800 font-medium text-sm">
                        {{ candidato.cargo }}
                      </p>
                      <div class="mt-3 flex flex-wrap gap-2 justify-center">
                        <Chip
                          v-for="valor in candidato.valores"
                          :key="valor"
                          :label="valor"
                          class="text-xs custom-chip"
                        />
                      </div>
                    </div>

                    <!-- Icono selección -->
                    <i
                      v-if="selectedCandidato === candidato.id"
                      class="pi pi-check-circle check-icon text-green-500 text-2xl absolute top-2 right-2"
                    ></i>
                    <i
                      v-else
                      class="pi pi-circle-off text-gray-400 text-2xl absolute top-2 right-2"
                    ></i>
                  </div>
                </div>

                <div v-else class="text-center py-8">
                  <i class="pi pi-info-circle text-4xl text-gray-400"></i>
                  <p class="text-gray-500 mt-4">
                    No hay candidatos registrados para su grupo ocupacional en este proceso.
                  </p>
                </div>

                <div class="mt-8">
                  <Button
                    label="Confirmar Voto"
                    icon="pi pi-check"
                    class="w-full p-button-success p-button-lg confirm-button"
                    @click="submitVoto"
                    :loading="isLoading"
                    :disabled="!candidatos.length || !selectedCandidato"
                  />
                </div>
              </div>

              <!-- Paso 4 -->
              <div v-else-if="currentStep === 4" key="step4" class="text-center py-8">
                <i class="pi pi-verified text-8xl text-green-500 animate-bounce-in"></i>
                <h2 class="mt-6 text-3xl font-bold text-gray-800">¡Gracias por su participación!</h2>
                <p class="text-gray-600 mt-2 text-lg">Su voto ha sido registrado exitosamente.</p>
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
/* Fondo degradado */
body {
  background: linear-gradient(to bottom, #e6edfb, #c2d3f7, #7ea0eb);
  margin: 0;
}

/* Header */
.header-logos { padding: 1.5rem 0; margin-bottom: 2rem; }
.header-content {
  max-width: 1200px; margin: 0 auto; padding: 0 2rem;
  display: flex; align-items: center; gap: 2rem; flex-wrap: wrap; justify-content: center;
}
.logo { height: 80px; object-fit: contain; }
.header-title { font-size: 2.5rem; font-weight: bold; color: #1f2937; margin: 0; text-align: center; flex-grow: 1; }

/* Contenedor principal */
.votacion-container { max-width: 1200px; margin: 0 auto; padding: 0 2rem 2rem; display: flex; flex-direction: column; align-items: center; }

/* Card principal */
.votacion-card { width: 100%; max-width: 800px; }
.card-content {}

/* Cards de candidatos */
.candidate-card {
  position: relative; display: flex; flex-direction: column; align-items: center;
  padding: 1.5rem; border: 2px solid #e5e7eb; border-radius: 16px; cursor: pointer;
  transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease, background .3s ease;
  background-color: #fff; overflow: hidden; text-align: center;
}
.candidate-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,.07); border-color: var(--primary-color); }
.candidate-card.selected { border-color: var(--primary-color); background: linear-gradient(135deg,#e0edff,#fff); box-shadow: 0 12px 30px rgba(59,130,246,.3); transform: translateY(-2px) scale(1.01); }
.candidate-card.selected label { color: var(--primary-color) !important; }
.candidate-card.selected p { color: #1e3a8a !important; }
.check-icon { position: absolute; top: 1rem; right: 1rem; font-size: 1.8rem; color: var(--primary-color); }
.custom-chip { background-color: #eff6ff; color: var(--primary-color); font-weight: 500; }
.candidate-card.selected .custom-chip { background-color: var(--primary-color); color: #fff; }

/* Botón deshabilitado */
.confirm-button:disabled { cursor: not-allowed !important; opacity: .7; }

/* Animaciones */
.fade-enter-active, .fade-leave-active { transition: opacity .3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Responsive */
@media (max-width: 768px) {
  .header-content { flex-direction: column; text-align: center; gap: 1rem; }
  .header-title { font-size: 2rem; }
  .votacion-container { padding: 0 1rem 2rem; }
  .card-content { padding: 1.5rem; }
}
@media (max-width: 480px) {
  .header-title { font-size: 1.5rem; }
  .card-content { padding: 1rem; }
}
</style>
