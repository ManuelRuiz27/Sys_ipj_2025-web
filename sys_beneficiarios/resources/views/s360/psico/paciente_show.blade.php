<x-app-layout>
<div class="container py-4" data-beneficiario-id="{{ $beneficiarioId ?? '' }}">
  <a href="/s360/psico" class="btn btn-link px-0">← Volver a pacientes</a>
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <div class="h5 mb-1" id="p-nombre">Beneficiario</div>
        <div class="text-muted" id="p-sub">Edad • Teléfono • Correo</div>
      </div>
      <a class="btn btn-outline-secondary" id="btn-historial">Ver historial</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card" id="card-primera">
        <div class="card-header">Primera consulta</div>
        <div class="card-body">
          <form id="form-primera">
            @csrf
            <input type="hidden" name="beneficiario_id" id="b1">
            <input type="hidden" name="is_first" value="true">
            <div class="mb-3">
              <label class="form-label">Fecha</label>
              <input type="date" class="form-control" name="session_date" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Motivo de consulta</label>
              <textarea class="form-control" name="motivo_consulta" required></textarea>
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Riesgo suicida</label>
                <select class="form-select" name="riesgo_suicida" required>
                  <option value="">Seleccione…</option>
                  <option value="0">No</option>
                  <option value="1">Sí</option>
                </select>
              </div>
              <div class="col">
                <label class="form-label">Uso de sustancias</label>
                <select class="form-select" name="uso_sustancias" required>
                  <option value="">Seleccione…</option>
                  <option value="0">No</option>
                  <option value="1">Sí</option>
                </select>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary" type="submit">Guardar</button>
              <div class="text-danger small" id="err-primera"></div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card" id="card-cont">
        <div class="card-header">Continuidad</div>
        <div class="card-body">
          <form id="form-cont">
            @csrf
            <input type="hidden" name="beneficiario_id" id="b2">
            <div class="mb-3">
              <label class="form-label">Fecha</label>
              <input type="date" class="form-control" name="session_date" required>
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Próxima cita</label>
                <input type="date" class="form-control" name="next_session_date">
              </div>
              <div class="col">
                <label class="form-label">Objetivo siguiente</label>
                <input type="text" class="form-control" name="next_objective">
              </div>
            </div>
            <div class="mb-3 mt-2">
              <label class="form-label">Notas</label>
              <textarea class="form-control" name="notes"></textarea>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary" type="submit">Guardar</button>
              <div class="text-danger small" id="err-cont"></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  async function fetchJson(url, opts={}) { const r = await fetch(url, opts); const j = await r.json().catch(()=>({})); return {ok:r.ok, data:j}; }
  document.addEventListener('DOMContentLoaded', async () => {
    const id = document.querySelector('[data-beneficiario-id]').dataset.beneficiarioId;
    document.getElementById('b1').value = id;
    document.getElementById('b2').value = id;
    document.getElementById('btn-historial').href = `/s360/psico/sesiones/${id}/show`;

    const info = await fetchJson(`/s360/psico/paciente/${id}`);
    if (info.ok) {
      const d = info.data; document.getElementById('p-nombre').textContent = d.nombre;
      const sub = [d.edad?`${d.edad} años`:'' , d.telefono||'', d.correo||''].filter(Boolean).join(' • ');
      document.getElementById('p-sub').textContent = sub;
    }

    const hist = await fetchJson(`/s360/psico/sesiones/${id}`);
    const hasFirst = (hist.data.items||[]).some(s => s.session_number === 1);
    document.getElementById('card-primera').style.display = hasFirst ? 'none' : 'block';
    document.getElementById('card-cont').style.display = hasFirst ? 'block' : 'none';

    async function submitForm(formId, errId, extra={}) {
      const form = document.getElementById(formId); const err = document.getElementById(errId);
      form.addEventListener('submit', async (e) => {
        e.preventDefault(); err.textContent='';
        const fd = new FormData(form);
        for (const [k,v] of Object.entries(extra)) fd.set(k,v);
        const r = await fetch('/s360/psico/sesiones', {method:'POST', headers:{'X-CSRF-TOKEN': fd.get('_token')}, body: fd});
        if (!r.ok) { const j = await r.json().catch(()=>({message:'Error'})); err.textContent = j.message || 'Error de validación'; return; }
        location.href = `/s360/psico/sesiones/${id}/show`;
      });
    }
    submitForm('form-primera', 'err-primera', {is_first:'true'});
    submitForm('form-cont', 'err-cont');
  });
</script>
</x-app-layout>
