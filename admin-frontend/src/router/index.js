import { createRouter, createWebHistory } from 'vue-router';
import DashboardView from '../views/DashboardView.vue';

export const routes = [
  {
    path: '/',
    name: 'Dashboard',
    component: DashboardView,
    meta: {
      label: 'Inicio',
      icon: 'pi pi-chart-line',
      description: 'Vista general y resultados en vivo'
    }
  },
  {
    path: '/empleados',
    name: 'Empleados',
    // Debes crear este archivo: src/views/EmpleadosView.vue
    component: () => import('../views/EmpleadosView.vue'),
    meta: {
      label: 'Empleados Hábiles',
      icon: 'pi pi-id-card',
      description: 'Gestionar empleados habilitados para votar'
    }
  },
  {
    path: '/procesos',
    name: 'Procesos',
    component: () => import('../views/ProcesosView.vue'),
    meta: {
      label: 'Gestión de Procesos',
      icon: 'pi pi-calendar-plus',
      description: 'Crear, iniciar y cerrar votaciones'
    }
  },
  {
    path: '/votos',
    name: 'Votos',
    component: () => import('../views/VotosView.vue'),
    meta: {
      label: 'Gestión de Votos',
      icon: 'pi pi-check-square',
      description: 'Supervisar, anular y habilitar votos'
    }
  },
  {
    path: '/resultados',
    name: 'Resultados',
    component: () => import('../views/ResultadosView.vue'),
    meta: {
      label: 'Gestión de Resultados',
      icon: 'pi pi-chart-pie',
      description: 'Ver resultados parciales y finales'
    }
  },
  {
    path: '/usuarios',
    name: 'Usuarios',
    component: () => import('../views/UsuariosView.vue'),
    meta: {
      label: 'Gestión de Usuarios',
      icon: 'pi pi-users',
      description: 'Administrar usuarios y supervisores'
    }
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;