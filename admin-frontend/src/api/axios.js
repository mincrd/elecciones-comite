import axios from 'axios';

// 1. Creamos una instancia de Axios con configuración centralizada.
//    Lee la URL base directamente desde las variables de entorno (.env).
const apiClient = axios.create({
  baseURL: 'http://127.0.0.1:8000/api/admin',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// 2. (Opcional pero recomendado para el futuro) Aquí podrías añadir "interceptors"
//    para manejar automáticamente tokens de autenticación en todas las peticiones.
/*
apiClient.interceptors.request.use(config => {
  const token = localStorage.getItem('authToken'); // O desde donde gestiones el token
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
*/

export default apiClient;

