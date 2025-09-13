<script setup>
import { ref, computed, onMounted } from 'vue';
import apiClient from '../api/axios';
import { useToast } from 'primevue/usetoast';

// PrimeVue
import Card from 'primevue/card';
import Toolbar from 'primevue/toolbar';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import ProgressSpinner from 'primevue/progressspinner';
import Message from 'primevue/message';

const toast = useToast();

/* =============== State =============== */
const isLoading = ref(false);
const pendientes = ref([]);        // listado plano
const resumenSrv = ref([]);        // si el backend trae "resumen"
const totalSrv = ref(null);        // si el backend trae "total_pendientes"

const search = ref('');
const filtroGrupo = ref(null);
const grupoOptions = ref(['I','II','III','IV','V']);

/* =============== Fetch =============== */
async function fetchPendientes() {
  isLoading.value = true;
  try {
    const { data } = await apiClient.get('/admin/votantes/pendientes');

    if (Array.isArray(data)) {
      pendientes.value = data;
      resumenSrv.value = [];
      totalSrv.value = null;
    } else {
      pendientes.value = Array.isArray(data.listado) ? data.listado : (Array.isArray(data.data) ? data.data : []);
      resumenSrv.value = Array.isArray(data.resumen) ? data.resumen : [];
      totalSrv.value = typeof data.total_pendientes === 'number' ? data.total_pendientes : null;
    }

    // Normalizar claves más usadas
    pendientes.value = pendientes.value.map(it => ({
      cedula: it.cedula ?? it.CEDULA ?? '',
      nombre_completo: it.nombre_completo ?? it.nombre ?? it.NOMBRE ?? '',
      grupo_ocupacional: it.grupo_ocupacional ?? it.grupo ?? it.GRUPO ?? '',
      cargo: it.cargo ?? it.CARGO ?? '',
      lugar_de_funciones: it.lugar_de_funciones ?? it.lugar_trabajo ?? it.ubicacion ?? '',
      email: it.email ?? '',
      ...it,
    }));
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el listado de pendientes.', life: 3500 });
  } finally {
    isLoading.value = false;
  }
}

/* =============== Derived =============== */
const pendientesFiltrados = computed(() => {
  const q = search.value.trim().toLowerCase();
  return pendientes.value.filter(p => {
    const passGrupo = !filtroGrupo.value || (p.grupo_ocupacional || '').toString() === filtroGrupo.value;
    if (!q) return passGrupo;
    const hay = [
      p.cedula,
      p.nombre_completo,
      p.cargo,
      p.lugar_de_funciones,
      p.email
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
      .includes(q);
    return passGrupo && hay;
  });
});

// Si el backend no trae total, lo calculamos de la lista
const totalPendientes = computed(() => {
  return typeof totalSrv.value === 'number' ? totalSrv.value : pendientes.value.length;
});

// Resumen por grupo (usa el del backend si viene, si no, lo calcula)
const resumenPorGrupo = computed(() => {
  if (resumenSrv.value.length) return resumenSrv.value;
  const map = new Map();
  for (const p of pendientes.value) {
    const g = p.grupo_ocupacional || '—';
    map.set(g, (map.get(g) || 0) + 1);
  }
  return [...map.entries()]
    .map(([grupo_ocupacional, pendientes]) => ({ grupo_ocupacional, pendientes }))
    .sort((a,b) => (a.grupo_ocupacional > b.grupo_ocupacional ? 1 : -1));
});

/* =============== Helpers =============== */
function sevByGrupo(g) {
  // Tonos distintos por grupo (opcional)
  return ({
    I: 'info',
    II: 'success',
    III: 'warning',
    IV: 'danger',
    V: 'secondary',
  }[g]) || 'secondary';
}

function exportCSV() {
  const rows = pendientesFiltrados.value;
  if (!rows.length) {
    toast.add({ severity: 'info', summary: 'Sin datos', detail: 'No hay filas para exportar.', life: 2500 });
    return;
  }

  const headers = ['cedula','nombre_completo','grupo_ocupacional','cargo','lugar_de_funciones','email'];
  const csv = [
    headers.join(','),
    ...rows.map(r => headers.map(h => {
      const raw = r[h] ?? '';
      const clean = String(raw).replace(/"/g, '""');
      return `"${clean}"`;
    }).join(','))
  ].join('\n');

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `pendientes_${new Date().toISOString().slice(0,10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

function printListado() {
  const rows = pendientesFiltrados.value;
  const htmlRows = rows.map(r => `
    <tr>
      <td>${r.cedula || ''}</td>
      <td>${r.nombre_completo || ''}</td>
      <td>${r.grupo_ocupacional || ''}</td>
      <td>${r.cargo || ''}</td>
      <td>${r.lugar_de_funciones || ''}</td>
      <td>${r.email || ''}</td>
    </tr>`).join('');

  const w = window.open('', '_blank');
  if (!w) return;
  w.document.write(`
    <html>
      <head>
        <meta charset="utf-8" />
        <title>Pendientes de votar</title>
        <style>
          body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding: 16px; }
          h1 { margin-top: 0; }
          table { width: 100%; border-collapse: collapse; font-size: 12px; }
          th, td { border: 1px solid #ddd; padding: 6px 8px; }
          th { background: #f3f4f6; text-align: left; }
        </style>
      </head>
      <body>
        <h1>Pendientes de votar</h1>
        <p>Total: ${rows.length}</p>
        <table>
          <thead>
            <tr>
              <th>Cédula</th>
              <th>Nombre</th>
              <th>Grupo</th>
              <th>Cargo</th>
              <th>Ubicación</th>
              <th>Email</th>
            </tr>
          </thead>
          <tbody>${htmlRows}</tbody>
        </table>
      </body>
    </html>
  `);
  w.document.close();
  w.focus();
  w.print();
}

/* =============== Lifecycle =============== */
onMounted(fetchPendientes);
</script>

<template>
  <div class="space-y-6">
    <!-- Toolbar / Filtros -->
    <Card>
      <template #title>Pendientes de votar</template>
      <template #content>
        <Toolbar class="mb-4">
          <template #start>
            <div class="flex items-center gap-3">
              <Dropdown
                v-model="filtroGrupo"
                :options="grupoOptions"
                placeholder="Filtrar por grupo"
                class="w-56"
                showClear
              />
              <span class="p-input-icon-left w-72">
                <i class="pi pi-search" />
                <InputText
                  v-model="search"
                  class="w-full"
                  placeholder="Buscar por cédula, nombre, cargo, ubicación"
                />
              </span>
              <Button icon="pi pi-refresh" label="Actualizar" class="p-button-text" @click="fetchPendientes" />
            </div>
          </template>
          <template #end>
            <div class="flex items-center gap-2">
              <Button icon="pi pi-download" label="Exportar CSV" class="p-button-outlined" @click="exportCSV" />
              <Button icon="pi pi-print" label="Imprimir" class="p-button-outlined" @click="printListado" />
            </div>
          </template>
        </Toolbar>

        <div v-if="isLoading" class="flex items-center gap-3 text-gray-600">
          <ProgressSpinner style="width:24px;height:24px" strokeWidth="4" />
          Cargando pendientes…
        </div>

        <template v-else>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="bg-gray-50 rounded-lg p-4 border">
              <div class="text-sm text-gray-600">Total pendientes</div>
              <div class="text-3xl font-bold">{{ totalPendientes }}</div>
            </div>

            <div class="md:col-span-2 bg-gray-50 rounded-lg p-4 border">
              <div class="text-sm text-gray-600 mb-2">Pendientes por grupo</div>
              <div class="flex flex-wrap gap-2">
                <Tag
                  v-for="r in resumenPorGrupo"
                  :key="r.grupo_ocupacional"
                  :value="`Grupo ${r.grupo_ocupacional}: ${r.pendientes}`"
                  :severity="sevByGrupo(r.grupo_ocupacional)"
                  rounded
                />
                <span v-if="!resumenPorGrupo.length" class="text-gray-500 text-sm">—</span>
              </div>
            </div>
          </div>

          <Message v-if="!pendientesFiltrados.length" severity="info" :closable="false">
            No hay registros que coincidan con el filtro.
          </Message>

          <DataTable
            v-else
            :value="pendientesFiltrados"
            class="p-datatable-sm"
            responsiveLayout="scroll"
            stripedRows
            :paginator="pendientesFiltrados.length > 10"
            :rows="10"
            :rowsPerPageOptions="[10,20,50,100]"
          >
            <Column field="cedula" header="Cédula" sortable style="width: 160px" />
            <Column field="nombre_completo" header="Nombre" sortable />
            <Column field="grupo_ocupacional" header="Grupo" sortable style="width: 120px">
              <template #body="{ data }">
                <Tag :value="data.grupo_ocupacional" :severity="sevByGrupo(data.grupo_ocupacional)" rounded />
              </template>
            </Column>
            <Column field="cargo" header="Cargo" sortable />
            <Column field="lugar_de_funciones" header="Ubicación" sortable />
            <Column field="email" header="Email" sortable />
          </DataTable>
        </template>
      </template>
    </Card>
  </div>
</template>
