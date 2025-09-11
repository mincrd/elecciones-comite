import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';

// El primer argumento es un ID único para el store
export const useProcesosStore = defineStore('procesos', () => {
    // --- Dependencias ---
    const toast = useToast();
    const apiUrl = 'https://api-votacion-backend.azurewebsites.net/api/admin';
    let resultadosInterval = null;

    // --- STATE (Estado) ---
    // Equivalente a tus ref() en el componente
    const procesos = ref([]);
    const postulantes = ref([]);
    const resultados = ref([]);
    const selectedProceso = ref(null);
    const isLoading = ref({ procesos: true, detalles: false });

    // --- GETTERS (Propiedades Computadas) ---
    // Equivalente a tus computed()
    const totalVotos = computed(() => {
        if (!Array.isArray(resultados.value)) return 0;
        return resultados.value.reduce((total, item) => total + (item.total_votos || 0), 0);
    });

    const chartData = computed(() => {
        if (resultados.value && resultados.value.length > 0) {
            return {
                labels: resultados.value.map(r => r.nombre_completo),
                datasets: [{
                    data: resultados.value.map(r => r.total_votos),
                    backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'],
                    hoverBackgroundColor: ['#2563EB', '#059669', '#D97706', '#DC2626', '#7C3AED', '#DB2777', '#0891B2', '#65A30D'],
                    borderWidth: 0
                }]
            };
        }
        return null;
    });

    // --- ACTIONS (Acciones / Métodos) ---
    // Equivalente a tus funciones
    const fetchProcesos = async () => {
        isLoading.value.procesos = true;
        try {
            const response = await axios.get(`${apiUrl}/procesos`);
            procesos.value = response.data;
        } catch (error) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los procesos.', life: 3000 });
        } finally {
            isLoading.value.procesos = false;
        }
    };

    const fetchDetallesProceso = async (procesoId) => {
        if (!procesoId) return;
        if (postulantes.value.length < 1 && resultados.value.length < 1) {
            isLoading.value.detalles = true;
        }
        try {
            const [postulantesRes, resultadosRes] = await Promise.all([
                axios.get(`${apiUrl}/postulantes?proceso_id=${procesoId}`),
                axios.get(`${apiUrl}/resultados/${procesoId}`)
            ]);
            postulantes.value = postulantesRes.data;
            resultados.value = Array.isArray(resultadosRes.data) ? resultadosRes.data : [];
        } catch (error) {
            postulantes.value = [];
            resultados.value = [];
            toast.add({ severity: 'warn', summary: 'Aviso', detail: 'No se pudieron cargar los detalles del proceso.', life: 3000 });
        } finally {
            isLoading.value.detalles = false;
        }
    };

    const selectProceso = (proceso) => {
        selectedProceso.value = proceso;
        postulantes.value = [];
        resultados.value = [];

        fetchDetallesProceso(proceso.id);

        if (resultadosInterval) clearInterval(resultadosInterval);
        resultadosInterval = setInterval(() => {
            if (selectedProceso.value && !isLoading.value.detalles) {
                fetchDetallesProceso(selectedProceso.value.id);
            }
        }, 15000);
    };

    const saveProceso = async (formProceso) => {
        try {
            const desdeDate = formProceso.desde instanceof Date ? formProceso.desde : new Date(formProceso.desde);
            const hastaDate = formProceso.hasta instanceof Date ? formProceso.hasta : new Date(formProceso.hasta);
            const data = { ...formProceso, desde: desdeDate.toISOString().split('T')[0], hasta: hastaDate.toISOString().split('T')[0] };

            if (formProceso.id) {
                await axios.put(`${apiUrl}/procesos/${formProceso.id}`, data);
                toast.add({ severity: 'success', summary: 'Éxito', detail: 'Proceso actualizado.', life: 3000 });
            } else {
                await axios.post(`${apiUrl}/procesos`, data);
                toast.add({ severity: 'success', summary: 'Éxito', detail: 'Proceso creado.', life: 3000 });
            }
            fetchProcesos();
            return true; // Indicar éxito para cerrar el modal
        } catch (error) {
            const message = error.response?.data?.errors ? Object.values(error.response.data.errors).join(' ') : 'No se pudo guardar el proceso.';
            toast.add({ severity: 'error', summary: 'Error', detail: message, life: 4000 });
            return false;
        }
    };
    
    // ... aquí irían savePostulante y deletePostulante ...
    // (Son muy similares a saveProceso, solo asegúrate de llamar a fetchDetallesProceso al final)


    // Exponer el estado, getters y acciones
    return {
        // State
        procesos,
        postulantes,
        resultados,
        selectedProceso,
        isLoading,
        // Getters
        totalVotos,
        chartData,
        // Actions
        fetchProcesos,
        fetchDetallesProceso,
        selectProceso,
        saveProceso,
        // ...exportar savePostulante, deletePostulante
    };
});