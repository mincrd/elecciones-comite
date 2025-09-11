import { createApp } from 'vue';
import { createPinia } from 'pinia'; // <-- 1. IMPORTACIÓN DE PINIA (Faltaba)
import App from './App.vue';
import router from './router'; // <-- 2. IMPORTACIÓN DEL ROUTER (Faltaba)
import './index.css'; // Para Tailwind CSS

// Importaciones de PrimeVue
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
//import 'primevue/resources/primevue.min.css'; // <-- 3. CSS BASE DE PRIMEVUE (Faltaba)
import 'primeicons/primeicons.css';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';

// Crear la instancia de la aplicación
const app = createApp(App);

// Crear la instancia de Pinia
const pinia = createPinia();

// Usar los plugins
app.use(pinia);
app.use(router);

// Configuración de PrimeVue
app.use(PrimeVue, {
  theme: {
      preset: Aura,
      options: {
        unstyled: false // Asegúrate de que esto sea false cuando usas temas predefinidos
      }
  }
});

// Registrar los servicios de PrimeVue
app.use(ToastService);
app.use(ConfirmationService);

// Registrar directivas
app.directive('tooltip', Tooltip);

// Montar la aplicación
app.mount('#app');