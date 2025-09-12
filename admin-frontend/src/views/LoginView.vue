<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { useRouter, useRoute } from 'vue-router';

import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';

const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const isLoading = ref(false);
const error = ref(null);

const handleLogin = async () => {
  if (!email.value || !password.value) {
    error.value = 'Completa correo y contraseña.';
    return;
  }

  isLoading.value = true;
  error.value = null;

  try {
    const ok = await authStore.login({ email: email.value, password: password.value });

    if (ok) {
      const redirect = (route.query.redirect && String(route.query.redirect)) || null;
      const target = redirect || (router.hasRoute('Dashboard') ? { name: 'Dashboard' } : '/');
      await router.replace(target);
    } else {
      error.value = 'Las credenciales son incorrectas. Por favor, inténtelo de nuevo.';
    }
  } catch (e) {
    error.value = e?.message ?? 'Error inesperado al iniciar sesión.';
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-100">
    <Card class="w-full max-w-md shadow-2xl">
      <template #header>
        <div class="bg-slate-900 text-white p-6 rounded-t-lg text-center">
          <i class="pi pi-shield text-4xl mb-2" />
          <h1 class="text-2xl font-bold">Panel de Administración</h1>
          <p class="text-slate-300">Por favor, inicie sesión para continuar</p>
        </div>
      </template>

      <template #content>
        <form @submit.prevent="handleLogin" class="p-fluid space-y-6 px-2">
          <div class="p-float-label">
            <InputText id="email" v-model="email" type="email" class="w-full" autocomplete="username" />
            <label for="email">Correo Electrónico</label>
          </div>
          <div class="p-float-label">
            <InputText id="password" v-model="password" type="password" class="w-full" autocomplete="current-password" />
            <label for="password">Contraseña</label>
          </div>

          <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

          <Button type="submit" label="Iniciar Sesión" icon="pi pi-sign-in" class="w-full p-button-primary" :loading="isLoading" />
        </form>
      </template>
    </Card>
  </div>
</template>
