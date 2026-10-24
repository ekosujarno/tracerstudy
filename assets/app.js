'use strict';
document.querySelectorAll('[data-toggle-sidebar]').forEach(button=>button.addEventListener('click',()=>document.body.classList.toggle('sidebar-open')));
document.querySelectorAll('[data-toggle-public]').forEach(button=>button.addEventListener('click',()=>document.querySelector('.public-links')?.classList.toggle('opened')));
document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm||'Lanjutkan tindakan?'))e.preventDefault();}));
document.querySelectorAll('[data-status-source]').forEach(el=>{
  function change(){document.querySelectorAll('[data-only-status]').forEach(g=>{let shown=g.dataset.onlyStatus.split(',').includes(el.value);g.hidden=!shown;g.querySelectorAll('input,select,textarea').forEach(inp=>inp.disabled=!shown);});}
  el.addEventListener('change',change);change();
});
document.querySelectorAll('[data-file-name]').forEach(el=>el.addEventListener('change',()=>{const target=document.querySelector(el.dataset.fileName);if(target)target.textContent=el.files?.[0]?.name||'Belum memilih berkas';}));
document.querySelectorAll('[data-show-password]').forEach(btn=>btn.addEventListener('click',()=>{let t=document.querySelector(btn.dataset.showPassword);if(t)t.type=t.type==='password'?'text':'password';}));

document.querySelectorAll('[data-print]').forEach(btn=>btn.addEventListener('click',()=>window.print()));
