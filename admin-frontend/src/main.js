import { createApp } from 'vue'
import App from './App.vue'

// Importaciones de PrimeVue
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import 'primeicons/primeicons.css';
import ConfirmationService from 'primevue/confirmationservice';
// --- PASO 1: IMPORTAR EL SERVICIO DE TOAST ---
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';

const app = createApp(App);

// Configuración de PrimeVue
app.use(PrimeVue, {
  theme: {
      preset: Aura,
      options: {
        unstyled: false
      }
  }
});

// --- PASO 2: REGISTRAR EL SERVICIO ---
// Esta línea le dice a tu aplicación que el servicio de Toast está disponible
app.use(ToastService);
app.use(ConfirmationService);
app.directive('tooltip', Tooltip);
app.mount('#app');

