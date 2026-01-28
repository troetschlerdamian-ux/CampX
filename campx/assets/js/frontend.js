(function(){
  const pad = (n)=> (n<10? '0'+n : ''+n);
  const fmt = (d)=> `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  const parseISO = (s)=>{
    if(!s) return null;
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
    if(!m) return null;
    return new Date(+m[1], +m[2]-1, +m[3]);
  };
  const today = new Date(); today.setHours(0,0,0,0);

  function ajaxCfg(root){
    const cfg = { url: '/wp-admin/admin-ajax.php', nonce: null };
    const rootEl = root && root.closest ? root.closest('[data-campx-root]') : null;
    if(typeof window.CampXAjax === 'object'){
      if(window.CampXAjax.url)   cfg.url   = window.CampXAjax.url;
      if(window.CampXAjax.nonce) cfg.nonce = window.CampXAjax.nonce;
    }else if(typeof window.campxAjaxUrl === 'string'){
      cfg.url = window.campxAjaxUrl;
    }else if(typeof window.ajaxurl === 'string'){
      cfg.url = window.ajaxurl;
    }
    if(!cfg.nonce && rootEl && rootEl.getAttribute){
      cfg.nonce = rootEl.getAttribute('data-nonce') || rootEl.getAttribute('data-security') || null;
    }
    return cfg;
  }

  function buildRangePicker(wrapper){
    const state = { month: new Date(today.getFullYear(), today.getMonth(), 1), start: null, end: null };
    const root = wrapper.closest('[data-campx-root]') || document;
    const inStart = root.querySelector('input[name="start_date"]');
    const inEnd   = root.querySelector('input[name="end_date"]');
    if(inStart && inStart.value) state.start = parseISO(inStart.value);
    if(inEnd && inEnd.value)     state.end   = parseISO(inEnd.value);

    wrapper.innerHTML = '';
    const nav = document.createElement('div'); nav.className = 'campx-nav';
    const btnPrev = document.createElement('button'); btnPrev.type='button'; btnPrev.className='campx-cal-nav'; btnPrev.setAttribute('aria-label','Vorheriger Monat'); btnPrev.textContent='‹';
    const title = document.createElement('div'); title.className = 'campx-cal-title';
    const btnNext = document.createElement('button'); btnNext.type='button'; btnNext.className='campx-cal-nav'; btnNext.setAttribute('aria-label','Nächster Monat'); btnNext.textContent='›';
    const cal = document.createElement('div'); cal.className = 'cal';
    nav.append(btnPrev, title, btnNext); wrapper.append(nav, cal);

    function monthLabel(d){
      try{ return new Intl.DateTimeFormat(document.documentElement.lang || 'de-CH',{month:'long',year:'numeric'}).format(d); }
      catch(e){ return d.getFullYear()+'-'+pad(d.getMonth()+1); }
    }
    function isBetween(d,a,b){ return a && b && d > a && d < b; }

    function render(){
      cal.innerHTML=''; title.textContent = monthLabel(state.month);
      const y = state.month.getFullYear(), m = state.month.getMonth();
      const monthEl = document.createElement('div'); monthEl.className='campx-month';
      const grid = document.createElement('div'); grid.className='campx-grid';

      ['Mo','Di','Mi','Do','Fr','Sa','So'].forEach(w=>{ const hd=document.createElement('div'); hd.className='campx-hd'; hd.textContent=w; grid.appendChild(hd); });
      const firstDow = (new Date(y,m,1).getDay()+6)%7;
      for(let i=0;i<firstDow;i++){ const blank=document.createElement('div'); blank.className='campx-empty'; grid.appendChild(blank); }

      const lastDay = new Date(y,m+1,0).getDate();
      for(let d=1; d<=lastDay; d++){
        const date = new Date(y,m,d);
        const b = document.createElement('button'); b.type='button'; b.className='campx-day'; b.textContent=''+d; b.dataset.date=fmt(date);
        if(date < today){ b.disabled=true; b.classList.add('is-out'); }
        if(state.start && fmt(state.start)===fmt(date)) b.classList.add('is-start','is-selected','is-inrange');
        if(state.end   && fmt(state.end)===fmt(date))   b.classList.add('is-end','is-selected','is-inrange');
        if(isBetween(date,state.start,state.end))       b.classList.add('is-inrange');
        b.addEventListener('click', ()=>{
          if(!state.start || (state.start && state.end)){ state.start = date; state.end = null; }
          else{
            if(date < state.start){ state.end = state.start; state.start = date; }
            else if(+date === +state.start){ state.end = null; }
            else { state.end = date; }
          }
          if(inStart) inStart.value = state.start ? fmt(state.start) : '';
          if(inEnd)   inEnd.value   = state.end   ? fmt(state.end)   : '';
          render();
          wrapper.dispatchEvent(new CustomEvent('campx:rangechange', {detail:{ start: state.start? fmt(state.start):'', end: state.end? fmt(state.end):'' }}));
        });
        grid.appendChild(b);
      }
      monthEl.appendChild(grid); cal.appendChild(monthEl);
    }
    btnPrev.addEventListener('click', ()=>{ state.month = new Date(state.month.getFullYear(), state.month.getMonth()-1, 1); render(); });
    btnNext.addEventListener('click', ()=>{ state.month = new Date(state.month.getFullYear(), state.month.getMonth()+1, 1); render(); });
    render();
  }

  function looksAvailable(info){
    if(!info) return false;
    const a = info.available;
    if(a === true || a === 1) return true;
    if(typeof a === 'string'){
      const s = a.toLowerCase();
      if(s==='1' || s==='true' || s==='yes' || s==='available' || s==='ok') return true;
    }
    if(info.status && typeof info.status==='string'){
      const s = info.status.toLowerCase();
      if(s==='available' || s==='free' || s==='ok') return true;
    }
    if(typeof info.units_left !== 'undefined'){
      const n = parseInt(info.units_left,10); if(!isNaN(n) && n>0) return true;
    }
    return false;
  }

  async function refreshCatalog(cat, start, end){
    const idStr = (cat.getAttribute('data-ids')||'').trim();
    const rows = cat.querySelectorAll('.item[data-res-id]');
    const ids = idStr ? idStr.split(',').map(s=>s.trim()).filter(Boolean) : Array.from(rows).map(r=>r.getAttribute('data-res-id'));

    function setBadge(row, text, cls){
      const badge = row.querySelector('[data-status]');
      if(badge){ badge.textContent = text; badge.className = 'campx-badge'+(cls?(' '+cls):''); }
    }

    if(!start || !end){
      rows.forEach(row=>{ setBadge(row,'—'); const radio=row.querySelector('input[type="radio"]'); if(radio) radio.disabled=true; });
      return;
    }

    rows.forEach(row=>{ setBadge(row,'prüfe…'); const radio=row.querySelector('input[type="radio"]'); if(radio) radio.disabled=true; });

    try{
      const cfg = ajaxCfg(cat);
      const fd = new FormData();
      fd.append('action','campx_check_availability');
      // Primär
      fd.append('start', start);
      fd.append('end', end);
      fd.append('ids', JSON.stringify(ids));
      // Kompat
      fd.append('start_date', start);
      fd.append('end_date', end);
      ids.forEach(id => fd.append('ids[]', id));
      // Semantik
      const startD = new Date(start), endD = new Date(end);
      const nights = Math.max(0, Math.round((endD - startD) / 86400000));
      fd.append('nights', String(nights));
      fd.append('checkout', end);
      fd.append('inclusive', '0');
      // Nonce Varianten
      if(cfg.nonce){
        fd.append('nonce', cfg.nonce);
        fd.append('_ajax_nonce', cfg.nonce);
        fd.append('security', cfg.nonce);
      }

      const res = await fetch(cfg.url, { method:'POST', body: fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} });
      const text = await res.text();
      let json = null;
      try{ json = JSON.parse(text); }catch(_){ json = null; }
      if(!res.ok){
        console.warn('[CampX] AJAX failed', res.status, text);
      }
      const map = (json && (json.availability || json.data || json)) || {};
      rows.forEach(row=>{
        const id = row.getAttribute('data-res-id');
        const info = map[id];
        const radio = row.querySelector('input[type="radio"]');
        if(looksAvailable(info)){
          setBadge(row, (info && info.units_left!=null)? `verfügbar (${info.units_left})` : 'verfügbar', 'success');
          if(radio) radio.disabled=false;
        }else{
          setBadge(row, 'ausgebucht', 'danger');
          if(radio) radio.disabled=true;
        }
      });
    }catch(e){
      console.error('[CampX] availability error:', e);
      rows.forEach(row=>{ setBadge(row,'—'); const radio=row.querySelector('input[type="radio"]'); if(radio) radio.disabled=true; });
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('[data-campx-range]').forEach(el=> buildRangePicker(el));

    const formWrap = document.querySelector('[data-form-wrapper]');
    if(formWrap){ formWrap.classList.add('hidden'); }

    document.querySelectorAll('[data-campx-range]').forEach(rg=>{
      rg.addEventListener('campx:rangechange', (e)=>{
        const start = e.detail && e.detail.start;
        const end   = e.detail && e.detail.end;
        document.querySelectorAll('[data-campx-catalog]').forEach(cat=> refreshCatalog(cat, start, end));
        const form = document.querySelector('[data-campx-form]');
        if(form){
          const s = form.querySelector('input[name="start_date"]');
          const n = form.querySelector('input[name="end_date"]');
          if(s) s.value = start || '';
          if(n) n.value = end   || '';
        }
      });
    });

    document.querySelectorAll('[data-campx-catalog]').forEach(cat=>{
      cat.addEventListener('change', (e)=>{
        if(e.target && e.target.name==='campx_res_select'){
          const resId = e.target.value;
          const form = document.querySelector('[data-campx-form]');
          if(form){ form.setAttribute('data-res-id', resId); }
          if(formWrap){
            formWrap.classList.remove('hidden');
            try{ formWrap.scrollIntoView({behavior:'smooth', block:'start'}); }catch(_){}
          }
        }
      });
    });

    const sInit = (document.querySelector('input[name="start_date"]')||{}).value || '';
    const eInit = (document.querySelector('input[name="end_date"]')||{}).value || '';
    if(sInit && eInit){
      document.querySelectorAll('[data-campx-catalog]').forEach(cat=> refreshCatalog(cat, sInit, eInit));
    }

    const bookingForm = document.querySelector('[data-campx-form]');
    if(bookingForm){
      const out = bookingForm.querySelector('[data-campx-out]');
      const submitBtn = bookingForm.querySelector('[data-campx-submit]');
      const setOut = (msg, cls)=>{
        if(!out) return;
        out.textContent = msg || '';
        out.className = 'campx-out'+(cls?(' '+cls):'');
      };

      bookingForm.addEventListener('submit', async (e)=>{
        e.preventDefault();
        setOut('');

        const resId = bookingForm.getAttribute('data-res-id') || '';
        if(!resId){
          setOut('Bitte zuerst eine Ressource auswählen.', 'error');
          return;
        }

        const payload = {
          resource_id: resId,
          start_date: bookingForm.querySelector('input[name="start_date"]')?.value || '',
          end_date: bookingForm.querySelector('input[name="end_date"]')?.value || '',
          units: bookingForm.querySelector('input[name="units"]')?.value || '1',
          persons: bookingForm.querySelector('input[name="persons"]')?.value || '1',
          name: bookingForm.querySelector('input[name="name"]')?.value || '',
          email: bookingForm.querySelector('input[name="email"]')?.value || '',
          phone: bookingForm.querySelector('input[name="phone"]')?.value || '',
          notes: bookingForm.querySelector('textarea[name="notes"]')?.value || '',
          company: bookingForm.querySelector('input[name="company"]')?.value || '',
        };

        if(submitBtn){
          submitBtn.disabled = true;
          submitBtn.dataset.originalText = submitBtn.textContent || '';
          submitBtn.textContent = 'Sende…';
        }

        try{
          const endpoint = (window.CampX && window.CampX.rest) ? `${window.CampX.rest}/book` : '/wp-json/campx/v1/book';
          const res = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });
          const json = await res.json().catch(()=> ({}));
          if(!res.ok || !json || json.ok !== true){
            const msg = (json && (json.message || (json.data && json.data.message))) || 'Die Anfrage konnte nicht gespeichert werden.';
            setOut(msg, 'error');
          }else{
            const thanks = json.thankyou || (window.CampX && window.CampX.settings && window.CampX.settings.thankyou_url) || '';
            setOut('Vielen Dank! Ihre Anfrage wurde gespeichert.', 'success');
            bookingForm.reset();
            if(thanks){
              window.location.href = thanks;
            }
          }
        }catch(err){
          console.error('[CampX] booking error', err);
          setOut('Es gab ein Problem beim Senden der Anfrage.', 'error');
        }finally{
          if(submitBtn){
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtn.dataset.originalText || submitBtn.textContent;
          }
        }
      });
    }
  });
})();
