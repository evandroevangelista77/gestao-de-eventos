/**
 * Gestão de Eventos — pop-up com o resumo de cada etapa do passo a passo (o que está em execução,
 * pendente e concluído) e cor nas abas nativas "Evento" e "Todos".
 */
(() => {
    let box = null;
    const ensure = () => box || (box = Object.assign(document.body.appendChild(document.createElement('div')), {className: 'ge-pop', hidden: true}));
    const hide = () => { if (box) box.hidden = true; };
    const show = (step) => {
        const src = step.querySelector('.ge-pop-src');
        if (!src) return;
        const b = ensure();
        b.innerHTML = src.innerHTML;
        b.hidden = false;
        const r = step.getBoundingClientRect();
        const w = b.offsetWidth; const h = b.offsetHeight;
        let x = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
        let y = r.bottom + 8;
        if (y + h > window.innerHeight - 8) y = Math.max(8, r.top - h - 8);
        b.style.left = x + 'px'; b.style.top = y + 'px';
    };
    document.addEventListener('mouseover', (ev) => {
        const s = ev.target.closest && ev.target.closest('.ge-step');
        if (s) show(s); else if (!ev.target.closest('.ge-pop')) hide();
    });
    document.addEventListener('focusin', (ev) => { const s = ev.target.closest && ev.target.closest('.ge-step'); if (s) show(s); });
    document.addEventListener('focusout', hide);
    document.addEventListener('scroll', hide, true);
    document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') hide(); });

    const paint = () => document.querySelectorAll('.nav-tabs .nav-link, .nav-link[data-bs-toggle="tab"]').forEach((a) => {
        const t = a.textContent.trim().toLowerCase();
        const c = {evento: '#14b8a6', todos: '#e2e8f0'}[t];
        if (c && !a.dataset.gec) { a.dataset.gec = '1'; a.classList.add('ge-t'); a.style.setProperty('--kc', c); }
    });
    paint();
    new MutationObserver(paint).observe(document.body, {childList: true, subtree: true});
})();
