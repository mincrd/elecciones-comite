import { createApp } from 'vue';
import { createPinia } from 'pinia' 
import App from './App.vue';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';

// Importación del nuevo sistema de temas y estilos base
import Lara from '@primevue/themes/lara';
import 'primeicons/primeicons.css';

const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        preset: Lara,
        options: {
            darkModeSelector: '.dark-mode', // Opcional si quieres modo oscuro
        }
    }
});
app.use(ToastService);
app.use(createPinia()) ;
app.mount('#app');
