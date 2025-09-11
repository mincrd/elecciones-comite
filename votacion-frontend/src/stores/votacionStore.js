import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

const apiUrl = 'https://api-votacion-backend.azurewebsites.net/api/votacion';

export const useVotacionStore = defineStore('votacion', () => {
    // --- STATE ---
    const currentStep = ref(1); // 1: Formulario, 2: Estado/Bienvenida, 3: Votación, 4: Agradecimiento
    const isLoading = ref(false);
    const candidatos = ref([]);
    const votanteInfo = ref(null); // NUEVO: Para guardar los datos del votante verificado

    // --- ACTIONS ---

    // NUEVA ACCIÓN: Solo verifica el estado del votante
// src/stores/votacionStore.js
 const getEstadoVotante = async (cedula) => {
        isLoading.value = true;
        votanteInfo.value = null;
        try {
            const response = await axios.get(`${apiUrl}/estado-votante/${cedula}`);
            votanteInfo.value = response.data;

            // --- LÍNEAS DE DEPURACIÓN ---
            console.log('[STORE] Respuesta de API recibida:', votanteInfo.value);
            console.log('[STORE] Intentando cambiar de paso... Valor actual:', currentStep.value);
            currentStep.value = 2;
            console.log('[STORE] ¡Paso cambiado! Nuevo valor:', currentStep.value);
            // --- FIN DE LÍNEAS DE DEPURACIÓN ---

        } catch (error) {
            const message = error.response?.data?.message || 'Error al verificar la cédula.';
            throw new Error(message);
        } finally {
            isLoading.value = false;
        }
    };

    // NUEVA ACCIÓN: Inicia la sesión y trae los candidatos
    const iniciarSesionDeVoto = async (cedula) => {
        isLoading.value = true;
        try {
            // 1. Registrar sesión y obtener token
            const response = await axios.post(`${apiUrl}/registrar-sesion`, { cedula });
            const token = response.data.token;
            localStorage.setItem('authToken', token);

            // 2. Obtener candidatos con el nuevo token
            await fetchCandidatos();
            currentStep.value = 3; // Avanzar al paso de votación
        } catch (error) {
             const message = error.response?.data?.message || 'No se pudo iniciar la sesión de votación.';
            throw new Error(message);
        } finally {
            isLoading.value = false;
        }
    };

    const fetchCandidatos = async () => {
        const token = localStorage.getItem('authToken');
        if (!token) throw new Error('Token no encontrado.');

        const response = await axios.get(`${apiUrl}/candidatos`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        candidatos.value = response.data;
    };
    
     const handleVoto = async (postulanteId) => {
        isLoading.value = true;
        try {
            // 1. Obtener el token de la sesión guardado en Local Storage
            const token = localStorage.getItem('authToken');
            if (!token) {
                throw new Error('No se encontró sesión de votación activa.');
            }

            // 2. Enviar la petición al backend con el ID del postulante y el token
            await axios.post(
                `${apiUrl}/votar`, 
                { postulante_id: postulanteId }, // El cuerpo de la petición
                { 
                    headers: { 'Authorization': `Bearer ${token}` } // El encabezado de autorización
                }
            );
            
            // 3. Si la petición es exitosa, avanzar al paso final
            currentStep.value = 4;

        } catch (error) {
            const message = error.response?.data?.message || 'Ocurrió un error al registrar su voto.';
            // Si el error es porque el voto ya fue emitido, igual lo llevamos a la pantalla final
            if (error.response?.status === 403) {
                 currentStep.value = 4;
            }
            throw new Error(message);
        } finally {
            // 4. Limpiar el token usado, ya que fue invalidado en el backend
            localStorage.removeItem('authToken');
            isLoading.value = false;
        }
    };

    const resetStore = () => {
        currentStep.value = 1;
        isLoading.value = false;
        candidatos.value = [];
        votanteInfo.value = null; // Limpiar también la info del votante
    };

    return {
        currentStep, isLoading, candidatos, votanteInfo, // Exponer el nuevo estado
        getEstadoVotante, iniciarSesionDeVoto, handleVoto, resetStore // Exponer las nuevas acciones
    };
});