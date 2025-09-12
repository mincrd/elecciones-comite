import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '../api/axios';
import { useToast } from 'primevue/usetoast';

export const useProcesosStore = defineStore('procesos', () => {
  const toast = useToast();
  let poll = null;

  // --- STATE ---
  const procesos = ref([]);
  const postulantes = ref([]);
  const selectedProceso = ref(null);
  const isLoading = ref({ procesos: true, detalles: false });

  // estadísticas de participación del proceso seleccionado
  const stats = ref({
    total_votantes: 0,     // empleados hábiles
    votos_emitidos: 0,     // registros de voto cerrados/emitidos
  });

  // --- GETTERS ---
  const porcentajeParticipacion = computed(() => {
    const total = Number(stats.value.total_votantes) || 0;
    const emitidos = Number(stats.value.votos_emitidos) || 0;
    if (total <= 0) return 0;
    return Math.round((emitidos / total) * 1000) / 10; // 1 decimal
  });

  // Donut: Votaron vs Pendientes
  const chartParticipacion = computed(() => {
    const total = Number(stats.value.total_votantes) || 0;
    const emitidos = Math.min(Number(stats.value.votos_emitidos) || 0, total);
    const pendientes = Math.max(total - emitidos, 0);

    // evita dataset vacío cuando no hay datos
    const data = total > 0 ? [emitidos, pendientes] : [0, 1];

    return {
      labels: ['Votaron', 'Pendientes'],
      datasets: [
        {
          data,
          backgroundColor: ['#10B981', '#93C5FD'],
          hoverBackgroundColor: ['#059669', '#60A5FA'],
          borderWidth: 0,
        },
      ],
    };
  });

  // --- HELPERS ---
  const toISODate = (v) => {
    if (!v) return null;
    const d = v instanceof Date ? v : new Date(v);
    return Number.isNaN(d.getTime()) ? null : d.toISOString().slice(0, 10);
  };

  function stopPolling() {
    if (poll) {
      clearInterval(poll);
      poll = null;
    }
  }

  // --- ACTIONS ---
  async function fetchProcesos() {
    isLoading.value.procesos = true;
    try {
      const { data } = await apiClient.get('/admin/procesos');
      procesos.value = Array.isArray(data) ? data : (data?.data ?? []);
    } catch {
      toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los procesos.', life: 3000 });
    } finally {
      isLoading.value.procesos = false;
    }
  }

  async function fetchEstadisticas(procesoId) {
    try {
      // Endpoint recomendado (del VotosAdminController que propusimos):
      // GET /api/admin/votos/estadisticas?proceso_id=ID
      const { data } = await apiClient.get('/admin/votos/estadisticas', {
        params: { proceso_id: procesoId },
      });

      // Normaliza posibles llaves
      stats.value = {
        total_votantes:
          data.total_votantes ??
          data.totalVotantes ??
          data.total_empleados_habiles ??
          0,
        votos_emitidos:
          data.votos_emitidos ??
          data.totalVotos ??
          data.total_registros_voto ??
          0,
      };
    } catch (e) {
      // si falla, deja stats en cero
      stats.value = { total_votantes: 0, votos_emitidos: 0 };
      const msg = e?.response?.data?.message || 'No se pudieron cargar las estadísticas de participación.';
      toast.add({ severity: 'warn', summary: 'Aviso', detail: msg, life: 3000 });
    }
  }

  async function fetchPostulantes(procesoId) {
    try {
      const { data } = await apiClient.get('/admin/postulantes', {
        params: { proceso_id: procesoId },
      });
      postulantes.value = Array.isArray(data) ? data : (data?.data ?? []);
    } catch {
      postulantes.value = [];
    }
  }

  async function fetchDetallesProceso(procesoId) {
    if (!procesoId) return;
    isLoading.value.detalles = true;
    try {
      await Promise.all([fetchPostulantes(procesoId), fetchEstadisticas(procesoId)]);
    } finally {
      isLoading.value.detalles = false;
    }
  }

  function selectProceso(proceso) {
    selectedProceso.value = proceso;
    postulantes.value = [];
    stats.value = { total_votantes: 0, votos_emitidos: 0 };
    fetchDetallesProceso(proceso?.id);

    stopPolling();
    // refresco cada 15s mientras estés en la vista
    poll = setInterval(() => {
      if (selectedProceso.value && !isLoading.value.detalles) {
        fetchEstadisticas(selectedProceso.value.id);
      }
    }, 15000);
  }

  async function saveProceso(formProceso) {
    try {
      const payload = {
        ...formProceso,
        desde: toISODate(formProceso.desde),
        hasta: toISODate(formProceso.hasta),
      };

      if (formProceso.id) {
        await apiClient.put(`/admin/procesos/${formProceso.id}`, payload);
        toast.add({ severity: 'success', summary: 'Éxito', detail: 'Proceso actualizado.', life: 3000 });
      } else {
        await apiClient.post('/admin/procesos', payload);
        toast.add({ severity: 'success', summary: 'Éxito', detail: 'Proceso creado.', life: 3000 });
      }
      await fetchProcesos();
      return true;
    } catch (e) {
      const message =
        e?.response?.data?.errors
          ? Object.values(e.response.data.errors).flat().join(' ')
          : e?.response?.data?.message || 'No se pudo guardar el proceso.';
      toast.add({ severity: 'error', summary: 'Error', detail: message, life: 4000 });
      return false;
    }
  }

  // Postulantes (opcional)
  async function savePostulante(data) {
    try {
      const payload = { ...data };
      if (payload.id) {
        await apiClient.put(`/admin/postulantes/${payload.id}`, payload);
        toast.add({ severity: 'success', summary: 'Éxito', detail: 'Postulante actualizado.', life: 3000 });
      } else {
        await apiClient.post('/admin/postulantes', payload);
        toast.add({ severity: 'success', summary: 'Éxito', detail: 'Postulante creado.', life: 3000 });
      }
      if (selectedProceso.value?.id) await fetchPostulantes(selectedProceso.value.id);
      return true;
    } catch (e) {
      const message =
        e?.response?.data?.errors
          ? Object.values(e.response.data.errors).flat().join(' ')
          : e?.response?.data?.message || 'No se pudo guardar el postulante.';
      toast.add({ severity: 'error', summary: 'Error', detail: message, life: 4000 });
      return false;
    }
  }

  async function deletePostulante(id) {
    try {
      await apiClient.delete(`/admin/postulantes/${id}`);
      toast.add({ severity: 'warn', summary: 'Eliminado', detail: 'Postulante eliminado.', life: 3000 });
      if (selectedProceso.value?.id) await fetchPostulantes(selectedProceso.value.id);
      return true;
    } catch {
      toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar el postulante.', life: 3000 });
      return false;
    }
  }

  return {
    // state
    procesos,
    postulantes,
    selectedProceso,
    isLoading,
    stats,

    // getters
    porcentajeParticipacion,
    chartParticipacion,

    // actions
    fetchProcesos,
    fetchDetallesProceso,
    fetchPostulantes,
    fetchEstadisticas,
    selectProceso,
    stopPolling,
    saveProceso,
    savePostulante,
    deletePostulante,
  };
});
