<x-app-layout>
<div class="container py-4" data-beneficiario-id="{{ $beneficiarioId ?? '' }}">
  <a href="/s360/psico" class="btn btn-link px-0">← Volver a pacientes</a>
  <h1 class="h5 mb-3">Historial de sesiones</h1>
  <div class="card">
    <div class="card-body" id="timeline"></div>
  </div>
</div>

<script>
  function itemHtml(s) {
    return `<div class="border-start ps-3 position-relative py-2">
      <span class="position-absolute top-0 translate-middle badge rounded-pill bg-primary" style="left:-.6rem;">${s.session_number}</span>
      <div class="d-flex justify-content-between">
        <div><strong>${s.session_date}</strong> ${s.is_first ? '• Primera' : ''}</div>
        <div class="text-muted">${s.psicologo_id ? ('Psicólogo #'+s.psicologo_id) : ''}</div>
      </div>
      ${s.motivo_consulta ? `<div class="small text-muted">${s.motivo_consulta}</div>` : ''}
      ${s.notes ? `<div class="small">${s.notes}</div>` : ''}
    </div>`;
  }
  document.addEventListener('DOMContentLoaded', async () => {
    const id = document.querySelector('[data-beneficiario-id]').dataset.beneficiarioId;
    const r = await fetch(`/s360/psico/sesiones/${id}`);
    const data = await r.json();
    const cont = document.getElementById('timeline');
    cont.innerHTML = (data.items||[]).map(itemHtml).join('');
  });
</script>
</x-app-layout>
