<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import apiClient from '../api/axios';
import { useToast } from 'primevue/usetoast';

// PrimeVue
import Card from 'primevue/card';
import Toolbar from 'primevue/toolbar';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressBar from 'primevue/progressbar';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';

// PDF (frontend)
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const toast = useToast();

/* ============================
   Estado / helpers
============================ */
const procesos = ref([]);
const isLoadingProcesos = ref(true);
const selectedProcesoId = ref(null);

const grupos = ref([]);                // [{ grupo, total, candidatos: [{nombre, votos, porcentaje}] }]
const isLoadingResultados = ref(false);

const procesosConcluidos = computed(() =>
  procesos.value.filter(p => normalizeEstado(p.estado) === 'Concluido')
);

const totalVotosGlobal = computed(() =>
  grupos.value.reduce((acc, g) => acc + (Number(g.total) || 0), 0)
);

// Normalizador de estado (tolerante a mayúsc/min)
const normalizeEstado = (s) => {
  const t = String(s ?? '').trim().toLowerCase();
  if (t.startsWith('conclu')) return 'Concluido';
  if (t.startsWith('cerr'))   return 'Cerrado';
  if (t.startsWith('abier'))  return 'Abierto';
  if (t.startsWith('cancel')) return 'Cancelado';
  if (t.startsWith('nuev'))   return 'Nuevo';
  return String(s ?? '');
};

// Buscar proceso seleccionado
const procesoSeleccionado = () =>
  procesos.value.find(p => p.id === selectedProcesoId.value) ?? {};

/* ============================
   Carga de procesos
============================ */
async function fetchProcesos() {
  isLoadingProcesos.value = true;
  try {
    const { data } = await apiClient.get('/admin/procesos');
    const lista = Array.isArray(data) ? data : (data?.data ?? []);
    procesos.value = lista;

    // Auto-seleccionar último concluido (por "hasta" o id)
    const concluidos = lista.filter(p => normalizeEstado(p.estado) === 'Concluido');
    if (concluidos.length) {
      concluidos.sort((a, b) => {
        const ah = a.hasta ? new Date(a.hasta).getTime() : 0;
        const bh = b.hasta ? new Date(b.hasta).getTime() : 0;
        if (ah !== bh) return bh - ah;
        return (b.id || 0) - (a.id || 0);
      });
      selectedProcesoId.value = concluidos[0].id;
    } else {
      selectedProcesoId.value = null;
    }
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los procesos.', life: 3000 });
  } finally {
    isLoadingProcesos.value = false;
  }
}

/* ============================
   Normalizador de resultados
============================ */
function parseResultadosPayload(data) {
  // Objetivo: [{ grupo, total, candidatos: [{ nombre, votos }] }]
  if (!data) return [];

  // Forma esperada del backend: { total, grupos: [ { grupo, total, candidatos: [{ nombre, votos }] } ] }
  if (Array.isArray(data.grupos)) {
    return data.grupos.map(g => {
      const grupo = g.grupo ?? g.nombre ?? '—';
      const total = Number(g.total ?? 0);
      const candidatos = (g.candidatos ?? []).map(c => ({
        nombre: c.nombre ?? c.nombre_completo ?? c.postulante?.nombre_completo ?? c.postulante_nombre ?? `#${c.id ?? c.postulante_id ?? '?'}`,
        votos: Number(c.votos ?? 0),
      }));
      return { grupo, total, candidatos };
    });
  }

  // Formas alternativas (por si cambias backend):
  if (Array.isArray(data)) {
    const map = new Map();
    for (const item of data) {
      const grupo = item.grupo ?? item.grupo_ocupacional ?? '—';
      const nombre = item.nombre ?? item.nombre_completo ?? item.postulante?.nombre_completo ?? item.postulante_nombre ?? `#${item.id ?? '?'}`;
      const votos = Number(item.votos ?? 0);
      if (!map.has(grupo)) map.set(grupo, []);
      map.get(grupo).push({ nombre, votos });
    }
    return Array.from(map.entries()).map(([grupo, candidatos]) => ({
      grupo,
      total: candidatos.reduce((a, c) => a + (Number(c.votos) || 0), 0),
      candidatos,
    }));
  }

  if (Array.isArray(data.candidatos)) {
    const arr = data.candidatos.map(c => ({
      grupo: c.grupo ?? c.grupo_ocupacional ?? '—',
      nombre: c.nombre ?? c.nombre_completo ?? c.postulante?.nombre_completo ?? c.postulante_nombre ?? `#${c.id ?? '?'}`,
      votos: Number(c.votos ?? 0),
    }));
    return parseResultadosPayload(arr);
  }

  return [];
}

function enriquecerConPorcentajes(lista) {
  return lista.map(g => {
    // Evitar mezcla ?? con || sin paréntesis
    let total = Number(g.total);
    if (!(total > 0)) {
      if (Array.isArray(g.candidatos)) {
        total = g.candidatos.reduce((a, c) => a + (Number(c.votos) || 0), 0);
      } else {
        total = 0;
      }
    }
    const ordenados = Array.isArray(g.candidatos) ? [...g.candidatos].sort((a, b) => b.votos - a.votos) : [];
    return {
      grupo: g.grupo,
      total,
      candidatos: ordenados.map(c => {
        const pct = total > 0 ? Math.round((Number(c.votos) / total) * 1000) / 10 : 0;
        return { ...c, porcentaje: pct };
      }),
    };
  });
}

/* ============================
   Carga de resultados
============================ */
async function fetchResultados() {
  grupos.value = [];
  if (!selectedProcesoId.value) return;

  const ps = procesoSeleccionado();
  if (!ps || normalizeEstado(ps.estado) !== 'Concluido') {
    toast.add({ severity: 'info', summary: 'Proceso no concluido', detail: 'Esta pantalla solo se habilita para procesos Concluidos.', life: 3500 });
    return;
  }

  isLoadingResultados.value = true;
  try {
    const { data } = await apiClient.get(`/admin/resultados/${selectedProcesoId.value}`);
    const normalizado = parseResultadosPayload(data);
    grupos.value = enriquecerConPorcentajes(normalizado);
  } catch (e) {
    const status = e?.response?.status;
    if (status === 403) {
      toast.add({ severity: 'warn', summary: 'No disponible', detail: 'El backend indica que el proceso no está Concluido.', life: 3500 });
    } else {
      toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los resultados.', life: 3000 });
    }
  } finally {
    isLoadingResultados.value = false;
  }
}

/* ============================
   UI helpers
============================ */
function refresh() {
  fetchProcesos().then(fetchResultados);
}

function ganadorBadge(candidatos, idx) {
  if (idx !== 0 || !Array.isArray(candidatos) || candidatos.length === 0) return false;
  const top = Number(candidatos[0].votos || 0);
  return top > 0; // “Ganador” solo si hay votos
}

/* ============================
   PDF (frontend) - Firmantes y exportación
============================ */
const FIRMANTES = [
  {
    nombre: '',
    cargo:
      'Responsable de Acceso a la Información.',
  },
  {
    nombre: '',
    cargo:
      'Responsable del Área de Recursos Humanos.',
  },
  {
    nombre: '',
    cargo:
      'Responsable del Área de Tecnología de la Información.',
  },
  {
    nombre: '',
    cargo:
      'Responsable del Área de Comunicación Institucional.',
  },
  {
    nombre: '',
    cargo:
      'Responsable del Área de Calidad Institucional.',
  },
];

async function exportarActaFront() {
  if (!selectedProcesoId.value || !grupos.value.length) {
    toast.add({
      severity: 'warn',
      summary: 'Sin datos',
      detail: 'Seleccione un proceso concluido y espere a que carguen los resultados.',
      life: 3500,
    });
    return;
  }

  const proceso = procesoSeleccionado();
  const fecha = new Date();
  const fechaStr = fecha.toLocaleDateString();
  const horaStr = fecha.toLocaleTimeString();

  const doc = new jsPDF({ unit: 'pt', format: 'letter' });
  const pageW = doc.internal.pageSize.getWidth();
  const pageH = doc.internal.pageSize.getHeight();
  const marginX = 40;
  let y = 60;

  // Título
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(14);
  doc.text('ACTA DE CIERRE Y PROCLAMACIÓN DE RESULTADOS', pageW / 2, y, { align: 'center' });
  y += 18;

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(10);
  doc.text(`Proceso ${proceso.ano ?? selectedProcesoId.value} — ${fechaStr} ${horaStr}`, pageW / 2, y, { align: 'center' });
  y += 22;

  // Resumen
  doc.setFontSize(12);
  doc.text(`Total de votos computados: ${totalVotosGlobal.value}`, marginX, y);
  y += 12;

  // Grupos
  for (const g of grupos.value) {
    if (y > pageH - 200) {
      doc.addPage();
      y = 60;
    }

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(12);
    doc.text(`Grupo ${g.grupo} (Total: ${g.total})`, marginX, y);
    y += 6;

    const top = Math.max(0, ...g.candidatos.map(c => Number(c.votos || 0)));
    const rows = g.candidatos.map(c => {
      const votos = Number(c.votos || 0);
      const pct = g.total > 0 ? Math.round((votos / g.total) * 1000) / 10 : 0;
      return [c.nombre, votos, `${pct.toFixed(1)}%`, top > 0 && votos === top ? 'Ganador' : ''];
    });

    autoTable(doc, {
      startY: y + 6,
      head: [['Candidato', 'Votos', '%', 'Observación']],
      body: rows,
      theme: 'grid',
      styles: { fontSize: 10, cellPadding: 4 },
      headStyles: { fillColor: [247, 247, 247], textColor: 20 },
      columnStyles: { 1: { halign: 'right', cellWidth: 70 }, 2: { halign: 'right', cellWidth: 60 }, 3: { cellWidth: 140 } },
      margin: { left: marginX, right: marginX },
    });

    y = doc.lastAutoTable.finalY + 14;
  }

  // Firmantes
  if (y > pageH - 240) {
    doc.addPage();
    y = 60;
  }

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(13);
  doc.text('Firmantes', marginX, y);
  y += 12;

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(10);

  for (const f of FIRMANTES) {
    const nombre = f.nombre && f.nombre.trim() !== '' ? f.nombre : '________________________';
    const lineTop = y + 18;

    doc.setLineWidth(0.5);
    doc.line(marginX, lineTop, pageW - marginX, lineTop);
    doc.text(nombre, marginX, lineTop - 4);

    const cargoLines = doc.splitTextToSize(f.cargo, pageW - marginX * 2);
    doc.setFontSize(9);
    doc.setTextColor(90);
    doc.text(cargoLines, marginX, lineTop + 14);
    doc.setTextColor(0);

    y = lineTop + 14 + cargoLines.length * 12 + 8;

    if (y > pageH - 120) {
      doc.addPage();
      y = 60;
    }
  }

  const nombreArchivo = `acta-resultados-${proceso.ano ?? selectedProcesoId.value}.pdf`;
  doc.save(nombreArchivo);
}

// (Opcional) descargar PDF desde backend si ya implementaste /acta.pdf
async function descargarActaBackend() {
  if (!selectedProcesoId.value) return;
  try {
    const { data, headers } = await apiClient.get(
      `/admin/resultados/${selectedProcesoId.value}/acta.pdf`,
      { responseType: 'blob' }
    );
    const blob = new Blob([data], { type: headers['content-type'] || 'application/pdf' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `acta-resultados-${selectedProcesoId.value}.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo descargar el PDF del servidor.', life: 4000 });
  }
}

/* ============================
   Lifecycle
============================ */
watch(selectedProcesoId, () => { fetchResultados(); });

onMounted(async () => {
  await fetchProcesos();
  await fetchResultados();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Selector de Proceso (solo Concluidos) -->
    <Card>
      <template #title>Resultados del Proceso</template>
      <template #content>
        <Toolbar class="mb-4">
          <template #start>
            <div class="flex items-center gap-3">
              <Dropdown
                :options="procesosConcluidos"
                optionLabel="ano"
                optionValue="id"
                v-model="selectedProcesoId"
                placeholder="Seleccione un proceso Concluido"
                class="w-64"
                :disabled="isLoadingProcesos"
              />
              <Button icon="pi pi-refresh" label="Actualizar" class="p-button-text" @click="refresh" />
            </div>
          </template>
          <template #end>
            <div class="flex items-center gap-3">
              <Button
                icon="pi pi-file"
                label="Generar Acta (Front)"
                class="p-button-secondary p-button-outlined"
                :disabled="!selectedProcesoId || isLoadingResultados || !grupos.length"
                @click="exportarActaFront"
              />
              <Button
                icon="pi pi-file-pdf"
                label="Descargar Acta (Backend)"
                class="p-button-danger p-button-outlined"
                :disabled="!selectedProcesoId || isLoadingResultados"
                @click="descargarActaBackend"
              />
              <span class="text-sm text-gray-500">
                Esta pantalla solo se habilita cuando el proceso está <b>Concluido</b>.
              </span>
            </div>
          </template>
        </Toolbar>

        <div v-if="isLoadingProcesos" class="flex items-center gap-3 text-gray-600">
          <ProgressSpinner style="width:24px;height:24px" strokeWidth="4" />
          Cargando procesos…
        </div>

        <Message v-else-if="!procesosConcluidos.length" severity="info" :closable="false" class="mt-2">
          No hay procesos Concluidos aún.
        </Message>
      </template>
    </Card>

    <!-- Resultados por grupo -->
    <Card v-if="selectedProcesoId && procesosConcluidos.length">
      <template #title>Resumen</template>
      <template #content>
        <div v-if="isLoadingResultados" class="flex items-center gap-3 text-gray-600">
          <ProgressSpinner style="width:24px;height:24px" strokeWidth="4" />
          Cargando resultados…
        </div>

        <template v-else>
          <Message v-if="!grupos.length" severity="warn" :closable="false">
            No hay resultados para este proceso (o el backend no devolvió datos).
          </Message>

          <div v-else class="space-y-8">
            <!-- Total global -->
            <div class="bg-gray-50 rounded-lg p-4 border">
              <div class="text-sm text-gray-600">Total de votos computados</div>
              <div class="text-3xl font-bold">{{ totalVotosGlobal }}</div>
            </div>

            <!-- Por grupo -->
            <div v-for="g in grupos" :key="g.grupo" class="space-y-3">
              <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">
                  Grupo {{ g.grupo }}
                </h3>
                <Tag :value="`${g.total} votos`" severity="info" rounded />
              </div>

              <DataTable :value="g.candidatos" class="p-datatable-sm" responsiveLayout="scroll" stripedRows>
                <Column header="#" style="width: 80px">
                  <template #body="{ index }">
                    <Tag v-if="ganadorBadge(g.candidatos, index)" value="Ganador" severity="success" rounded />
                    <span v-else>{{ index + 1 }}</span>
                  </template>
                </Column>
                <Column field="nombre" header="Candidato" sortable />
                <Column field="votos" header="Votos" sortable style="width: 120px" />
                <Column header="Porcentaje" style="min-width: 220px">
                  <template #body="{ data }">
                    <div class="flex items-center gap-2">
                      <div class="w-40">
                        <ProgressBar :value="data.porcentaje" :showValue="false" />
                      </div>
                      <span class="tabular-nums">{{ Number(data.porcentaje || 0).toFixed(1) }}%</span>
                    </div>
                  </template>
                </Column>
              </DataTable>
            </div>
          </div>
        </template>
      </template>
    </Card>
  </div>
</template>
