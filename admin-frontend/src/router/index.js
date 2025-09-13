import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

// Vistas
import DashboardView from '../views/DashboardView.vue';
import LoginView from '../views/LoginView.vue';

export const routes = [
  {
    path: '/login',
    name: 'Login',
    component: LoginView,
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    name: 'Dashboard',
    component: DashboardView,
    meta: {
      label: 'Inicio',
      icon: 'pi pi-chart-line',
      description: 'Vista general y resultados en vivo',
      requiresAuth: true
    }
  },
  {
    path: '/empleados',
    name: 'Empleados',
    component: () => import('../views/EmpleadosView.vue'),
    meta: {
      label: 'Empleados Hábiles',
      icon: 'pi pi-id-card',
      description: 'Gestionar empleados habilitados para votar',
      requiresAuth: true
    }
  },
  {
    path: '/procesos',
    name: 'Procesos',
    component: () => import('../views/ProcesosView.vue'),
    meta: {
      label: 'Gestión de Procesos',
      icon: 'pi pi-calendar-plus',
      description: 'Crear, iniciar y cerrar votaciones',
      requiresAuth: true
    }
  },
  {
    path: '/votos',
    name: 'Votos',
    component: () => import('../views/VotosView.vue'),
    meta: {
      label: 'Gestión de Votos',
      icon: 'pi pi-check-square',
      description: 'Supervisar, anular y habilitar votos',
      requiresAuth: true
    }
  },
  {
  path: '/pendientes',
  name: 'PendientesVoto',
  component: () => import('@/views/NoVotantesView.vue'),
    meta: { requiresAuth: true, title: 'Pendientes de votar', icon: 'pi pi-user-minus' }
   },
  {
    path: '/resultados',
    name: 'Resultados',
    component: () => import('../views/ResultadosView.vue'),
    meta: {
      label: 'Gestión de Resultados',
      icon: 'pi pi-chart-pie',
      description: 'Ver resultados parciales y finales',
      requiresAuth: true
    }
  },
  {
    path: '/usuarios',
    name: 'Usuarios',
    component: () => import('../views/UsuariosView.vue'),
    meta: {
      label: 'Gestión de Usuarios',
      icon: 'pi pi-users',
      description: 'Administrar usuarios y supervisores',
      requiresAuth: true
    }
  }
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
});

// Guardia global (hidrata desde localStorage por si el store aún no lo hizo)
router.beforeEach((to, from, next) => {
  const auth = useAuthStore();

  // Hidrata token/usuario si el store arranca vacío
  if (!auth.token) {
    const t = localStorage.getItem('token');
    const u = localStorage.getItem('user');
    if (t) auth.token = t;
    if (u) auth.user = JSON.parse(u);
  }

  const needsAuth = to.meta.requiresAuth === true;

  if (needsAuth && !auth.isAuthenticated) {
    next({ name: 'Login', query: { redirect: to.fullPath } });
  } else if (to.name === 'Login' && auth.isAuthenticated) {
    next({ name: 'Dashboard' });
  } else {
    next();
  }
});

export default router;
