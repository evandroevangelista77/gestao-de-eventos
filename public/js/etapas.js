/**
 * Gestão de Eventos — interface viva: pop-up de status ao passar o mouse (etapas e abas do evento),
 * cronômetro ao vivo de cada etapa (tempo na etapa e tempo restante pelo SLO) e cores dos títulos.
 */
(() => {
    const COLORS = {
        evento: '#2ee6c8', documentos: '#4d9bff', 'histórico': '#b0bec5', historico: '#b0bec5', todos: '#ffd400',
    };
    const MENU = {
        'evento.php': '#2ee6c8', 'calendario': '#ff9a3c', 'painel': '#4d9bff', 'aprovacoes': '#b388ff', 'relatorios': '#ffd400', 'documentos': '#29b6f6', 'etapa': '#ff5fa2', 'admin': '#ff5fa2', 'alertas': '#ffb300', 'alcada': '#a4ff3f',
    };
    const PALETTE = ['#29b6f6', '#2ee6a6', '#ff5fa2', '#ff9a3c', '#ffd400', '#b388ff', '#19e3ff', '#a4ff3f'];

    // ---------------------------------------------------------------- pop-up de status
    let box = null;
    const ensure = () => box || (box = Object.assign(document.body.appendChild(document.createElement('div')), {className: 'ge-pop', hidden: true}));
    const hide = () => { if (box) box.hidden = true; };
    const show = (el) => {
        const src = el.querySelector('.ge-pop-src');
        if (!src) return;
        const b = ensure();
        b.innerHTML = src.innerHTML;
        b.hidden = false;
        const r = el.getBoundingClientRect();
        const w = b.offsetWidth; const h = b.offsetHeight;
        let x; let y;
        if (el.matches('a.nav-link') && r.width < 300 && r.height < 60 && el.closest('.flex-column, .nav-pills, .nav-vertical, [class*=vertical]')) {
            x = r.right + 8; y = Math.min(Math.max(8, r.top - 8), window.innerHeight - h - 8);
            if (x + w > window.innerWidth - 8) x = Math.max(8, r.left - w - 8);
        } else {
            x = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
            y = r.bottom + 8;
            if (y + h > window.innerHeight - 8) y = Math.max(8, r.top - h - 8);
        }
        b.style.left = x + 'px'; b.style.top = y + 'px';
    };
    const host = (t) => t.closest && t.closest('.ge-step, a.nav-link');
    document.addEventListener('mouseover', (ev) => {
        const s = host(ev.target);
        if (s && s.querySelector('.ge-pop-src')) show(s); else hide();
    });
    document.addEventListener('focusin', (ev) => { const s = host(ev.target); if (s && s.querySelector('.ge-pop-src')) show(s); });
    document.addEventListener('focusout', hide);
    document.addEventListener('scroll', hide, true);
    document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') hide(); });

    // ---------------------------------------------------------------- cronômetro
    const t0 = Date.now();
    const p2 = (n) => String(n).padStart(2, '0');
    const fmt = (sec) => {
        sec = Math.max(0, Math.floor(sec));
        const d = Math.floor(sec / 86400); const h = Math.floor(sec % 86400 / 3600); const m = Math.floor(sec % 3600 / 60); const s = sec % 60;
        return (d ? d + 'd ' : '') + p2(h) + 'h ' + p2(m) + 'm ' + p2(s) + 's';
    };
    const mini = (sec) => {
        sec = Math.max(0, Math.floor(sec));
        const d = Math.floor(sec / 86400); const h = Math.floor(sec % 86400 / 3600); const m = Math.floor(sec % 3600 / 60);
        return d ? d + 'd ' + h + 'h' : h ? h + 'h ' + p2(m) + 'm' : m + 'm ' + p2(sec % 60) + 's';
    };
    const tick = () => {
        const dt = (Date.now() - t0) / 1000;
        document.querySelectorAll('.ge-timer').forEach((t) => {
            const spent = Number(t.dataset.spent) + dt; const slo = Number(t.dataset.slo);
            const left = slo - spent; const late = slo > 0 && left < 0; const warn = slo > 0 && !late && spent / slo >= 0.8;
            t.classList.toggle('late', late); t.classList.toggle('warn', warn);
            const set = (k, v) => { const n = t.querySelector(`[data-k=${k}]`); if (n) n.textContent = v; };
            set('spent', fmt(spent));
            set('left', fmt(Math.abs(left)));
            set('lefttxt', late ? 'Atrasada há' : 'Ainda restam');
            set('mini', !slo ? mini(spent) : (late ? 'atraso ' : 'resta ') + mini(Math.abs(left)));
            if (slo) {
                const pct = Math.min(100, 100 * spent / slo);
                const bar = t.querySelector('[data-k=bar]'); if (bar) { bar.style.width = pct + '%'; bar.style.backgroundSize = (100 / Math.max(pct, 1) * 100) + '% 100%'; }
                set('pct', late ? 'Prazo estourado: ' + Math.round(100 * spent / slo) + '% do SLO consumido' : Math.round(pct) + '% do SLO consumido');
            }
        });
    };
    tick(); setInterval(tick, 1000);

    // ---------------------------------------------------------------- cores
    const paint = () => {
        document.querySelectorAll('.nav-tabs .nav-link, .nav-link[data-bs-toggle="tab"]').forEach((a) => {
            const c = COLORS[a.textContent.trim().toLowerCase().replace(/\s*\d+$/, '')];
            if (c && !a.dataset.gec && !a.querySelector('.ge-t')) { a.dataset.gec = '1'; a.classList.add('ge-t'); a.style.setProperty('--kc', c); }
        });
        // entradas do plugin no menu lateral do GLPI
        document.querySelectorAll('a[href*="/plugins/gestaoeventos/front/"]').forEach((a) => {
            if (a.dataset.gem || a.closest('main, .page-body, .card')) return;
            a.dataset.gem = '1'; a.classList.add('ge-menu-item');
            const k = Object.keys(MENU).find((x) => a.getAttribute('href').includes(x));
            if (k) a.style.setProperty('--kc', MENU[k]);
        });
        // cabeçalhos de cartão das telas do plugin (painel, aprovações, relatórios…) sem cor própria
        let i = 0;
        document.querySelectorAll('.ge .card-header').forEach((h) => {
            if (h.closest('.ge-k') || h.classList.contains('ge-hd') || h.dataset.gec) return;
            h.dataset.gec = '1'; h.classList.add('ge-hd'); h.style.setProperty('--kc', PALETTE[i++ % PALETTE.length]);
        });
    };
    paint();
    let pending = false;
    new MutationObserver(() => { if (!pending) { pending = true; setTimeout(() => { pending = false; paint(); }, 150); } }).observe(document.body, {childList: true, subtree: true});
})();
