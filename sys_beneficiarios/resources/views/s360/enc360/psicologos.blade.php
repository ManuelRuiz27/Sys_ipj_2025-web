<x-app-layout>
<div class="container py-4">
  <h1 class="h5 mb-3">Psicólogos</h1>

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label small">Buscar</label>
          <input id="q" type="text" class="form-control form-control-sm" placeholder="Nombre o correo">
        </div>
        <div class="col-auto">
          <button id="btn-search" class="btn btn-sm btn-primary">Buscar</button>
        </div>
        <div class="col ms-auto text-end">
          <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalNew">Nuevo psicólogo</button>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light"><tr><th>Nombre</th><th>Correo</th><th>Carga</th><th style="width:140px">Acciones</th></tr></thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <div id="pager" class="small text-muted"></div>
    </div>
  </div>
</div>

<!-- Modal nuevo psicólogo -->
<div class="modal fade" id="modalNew" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Nuevo psicólogo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Nombre</label><input id="n-name" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Correo</label><input id="n-email" type="email" class="form-control"></div>
        <div class="text-danger small" id="n-err"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button id="btn-create" class="btn btn-primary">Crear</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  async function load(page=1){
    const q = document.getElementById('q').value.trim();
    const p = new URLSearchParams({page}); if (q) p.append('q', q);
    showSpinnerIn(document.getElementById('pager'));
    let data = {};
    try {
      const res = await fetch(`/s360/enc360/psicologos/list?${p.toString()}`);
      data = await res.json();
    } catch (e) { showToast('Error cargando psicólogos','danger'); return; }
    const tbody = document.getElementById('rows');
    tbody.innerHTML = (data.data||[]).map(r => `<tr>
      <td>${r.name}</td>
      <td>${r.email}</td>
      <td><span class='badge bg-secondary'>${r.cargas}</span></td>
      <td>
        <a class='btn btn-sm btn-outline-primary' href='/s360/enc360/asignaciones?psicologo_id=${r.id}'>Ver pacientes</a>
      </td>
    </tr>`).join('');
    const pager = document.getElementById('pager');
    const cur = data.current_page||1; const last = data.last_page||1; const total = data.total||0;
    pager.innerHTML = `<div class="d-flex justify-content-between align-items-center"><div>Página ${cur} de ${last} • ${total} psicólogos</div><div class="btn-group btn-group-sm" role="group"><button class="btn btn-outline-secondary" ${cur<=1?'disabled':''} id="pg-prev">« Anterior</button><button class="btn btn-outline-secondary" ${cur>=last?'disabled':''} id="pg-next">Siguiente »</button></div></div>`;
    const prev = document.getElementById('pg-prev'); const next = document.getElementById('pg-next');
    if (prev) prev.onclick = ()=>load(cur-1);
    if (next) next.onclick = ()=>load(cur+1);
  }
  document.addEventListener('DOMContentLoaded', ()=>{
    load();
    document.getElementById('btn-search').addEventListener('click', ()=>load());
    document.getElementById('q').addEventListener('keydown', (e)=>{ if (e.key==='Enter') { e.preventDefault(); load(); }});
    const btnCreate = document.getElementById('btn-create');
    document.getElementById('btn-create').addEventListener('click', async ()=>{
      const name = document.getElementById('n-name').value.trim();
      const email = document.getElementById('n-email').value.trim();
      const err = document.getElementById('n-err'); err.textContent='';
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!name) { err.textContent='Nombre es obligatorio'; return; }
      if (!email || !re.test(email)) { err.textContent='Correo no válido'; return; }
      btnCreate.setAttribute('disabled','disabled');
      const res = await fetch('/s360/enc360/psicologos', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify({name,email})});
      if (!res.ok) { const j = await res.json().catch(()=>({message:'Error'})); err.textContent = j.message || 'Error al crear'; btnCreate.removeAttribute('disabled'); return; }
      bootstrap.Modal.getInstance(document.getElementById('modalNew')).hide();
      showToast('Psicólogo creado','success');
      btnCreate.removeAttribute('disabled');
      load();
    });
  });
</script>
@endpush
</x-app-layout>

