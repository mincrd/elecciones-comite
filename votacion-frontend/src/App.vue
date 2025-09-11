<script setup>
// 1. Importa el componente principal que quieres mostrar.
import Votacion from './components/Votacion.vue';

// 2. Importa el componente Toast de PrimeVue.
//    Es necesario tenerlo en el template para que useToast() funcione.
import Toast from 'primevue/toast';
</script>

<template>
  <Toast />

  <main>
    <Votacion />
  </main>
</template>

<style>
/* 6. Es una buena práctica añadir algunos estilos globales aquí. */

/* Reseteo básico para consistencia entre navegadores */
*,
*::before,
*::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

/* Establece una fuente base y un color de fondo para toda la aplicación */
body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
  line-height: 1.5;
  background-color: #f8fafc; /* Un gris muy claro que combina bien */
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Asegúrate de que el div #app ocupe toda la altura */
#app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

main {
  flex-grow: 1; /* Permite que el contenido principal crezca y ocupe el espacio disponible */
}
</style>