<x-app-layout>
<div class="container py-4">
  <h1 class="h4 mb-4">Salud360 — Bienestar</h1>

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Pacientes activos</div>
              <div class="display-6" id="b-kpi-asignados">0</div>
            </div>
            <i class="bi bi-people fs-2 text-primary"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Sesiones esta semana</div>
              <div class="display-6" id="b-kpi-sesiones">0</div>
            </div>
            <i class="bi bi-calendar-week fs-2 text-success"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Promedio carga por psicólogo</div>
              <div class="display-6" id="b-kpi-promedio">0</div>
            </div>
            <i class="bi bi-graph-up fs-2 text-warning"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header bg-white">
          <div class="d-flex gap-2 align-items-center">
            <span class="fw-semibold">Psicólogos con más carga</span>
            <select class="form-select form-select-sm" id="b-filtro-enc360">
              <option value="">Encargado 360 (todos)</option>
            </select>
          </div>
        </div>
        <div class="card-body" style="max-height:420px; overflow:auto;">
          <ul class="list-group list-group-flush" id="b-top-psico"></ul>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header bg-white">
          <div class="d-flex gap-2 align-items-center">
            <span class="fw-semibold">Encargados con más asignaciones</span>
            <select class="form-select form-select-sm" id="b-filtro-enc360-2">
              <option value="">Encargado 360 (todos)</option>
            </select>
          </div>
        </div>
        <div class="card-body" style="max-height:420px; overflow:auto;">
          <ul class="list-group list-group-flush" id="b-top-enc"></ul>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', async () => {
    const r = await fetch('{{ route('s360.bienestar.dash') }}');
    if (!r.ok) return;
    const data = await r.json();
    document.getElementById('b-kpi-asignados').textContent = data.asignacionesActivas ?? 0;
    document.getElementById('b-kpi-sesiones').textContent = data.sesionesSemana ?? 0;
    document.getElementById('b-kpi-promedio').textContent = data.promedioCargaPorPsicologo ?? 0;
    const ulP = document.getElementById('b-top-psico');
    ulP.innerHTML = (data.topPsicologos||[]).map(i => `<li class="list-group-item d-flex justify-content-between"><span>${i.name}</span><span class="badge bg-secondary">${i.cargas}</span></li>`).join('');
    const ulE = document.getElementById('b-top-enc');
    ulE.innerHTML = (data.topEncargados||[]).map(i => `<li class="list-group-item d-flex justify-content-between"><span>${i.name}</span><span class="badge bg-secondary">${i.asignaciones}</span></li>`).join('');
  });
</script>
</x-app-layout>
