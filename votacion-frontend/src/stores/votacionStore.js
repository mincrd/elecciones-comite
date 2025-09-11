// src/stores/votacionStore.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

// Usa variable de entorno en vez de 127.0.0.1
const apiUrl =
  (import.meta.env.VITE_API_URL ?? 'https://api-votacion-backend.azurewebsites.net') + '/api/votacion';

export const useVotacionStore = defineStore('votacion', () => {
  const currentStep = ref(1);
  const isLoading = ref(false);
  const candidatos = ref([]);
  const votanteInfo = ref(null);

  // Solo verifica estado
  const getEstadoVotante = async (cedula) => {
    isLoading.value = true;
    votanteInfo.value = null;
    try {
      const { data } = await axios.get(`${apiUrl}/estado-votante/${cedula}`);
      votanteInfo.value = data;
      currentStep.value = 2; // Paso de estado/bienvenida
    } finally {
      isLoading.value = false;
    }
  };

  // Obtiene candidatos (NECESITA grupo ocupacional)
  const fetchCandidatos = async (grupoOcupacional) => {
    if (!grupoOcupacional) throw new Error('Falta grupo ocupacional');
    isLoading.value = true;
    try {
      const { data } = await axios.get(`${apiUrl}/candidatos/${grupoOcupacional}`);
      candidatos.value = data;
      currentStep.value = 3; // Paso de votación
    } finally {
      isLoading.value = false;
    }
  };

  // Inicia sesión (si aplica) y CARGA candidatos del grupo del votante
  const iniciarSesionDeVoto = async (cedula) => {
    isLoading.value = true;
    try {
      // (opcional) si tu backend emite token; si no, elimina este bloque
      const { data } = await axios.post(`${apiUrl}/registrar-sesion`, { cedula });
      localStorage.setItem('authToken', data.token);

      // Usa el grupo que ya trajiste en getEstadoVotante
      const grupo = votanteInfo.value?.grupo_ocupacional;
      await fetchCandidatos(grupo);
    } finally {
      isLoading.value = false;
    }
  };

  // Envía voto
  const handleVoto = async ({ cedula, postulanteId }) => {
    isLoading.value = true;
    try {
      await axios.post(`${apiUrl}/votar`, { cedula, postulante_id: postulanteId });
      currentStep.value = 4; // Gracias
    } catch (error) {
      if (error.response?.status === 403) {
        // Ya votó → solo muestra “Gracias” sin lanzar error
        currentStep.value = 4;
        return;
      }
      throw new Error(error.response?.data?.message || 'Ocurrió un error al registrar su voto.');
    } finally {
      isLoading.value = false;
    }
  };

  const resetStore = () => {
    currentStep.value = 1;
    isLoading.value = false;
    candidatos.value = [];
    votanteInfo.value = null;
  };

  return {
    currentStep,
    isLoading,
    candidatos,
    votanteInfo,
    getEstadoVotante,
    iniciarSesionDeVoto,
    fetchCandidatos, // <— ahora sí expuesto
    handleVoto,
    resetStore,
  };
});
