/* ASATS — app.js */
document.addEventListener('DOMContentLoaded', () => {

    /* ── Mobile nav toggle ─────────────────────────── */
    const toggle = document.getElementById('navToggle');
    const panel  = document.getElementById('navPanel');
    if (toggle && panel) {
        toggle.addEventListener('click', () => {
            const open = panel.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
            panel.setAttribute('aria-hidden', String(!open));
        });
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!toggle.contains(e.target) && !panel.contains(e.target)) {
                panel.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
                panel.setAttribute('aria-hidden', 'true');
            }
        });
    }

    /* ── Countdown timers ──────────────────────────── */
    document.querySelectorAll('[data-countdown]').forEach((el) => {
        const deadline = new Date(el.dataset.countdown);
        if (Number.isNaN(deadline.getTime())) return;

        const update = () => {
            const diff = deadline.getTime() - Date.now();
            el.classList.remove('due', 'overdue');

            if (diff <= 0) {
                el.textContent = 'Deadline passed';
                el.classList.add('overdue');
                return;
            }

            const days  = Math.floor(diff / 86_400_000);
            const hours = Math.floor((diff % 86_400_000) / 3_600_000);
            const mins  = Math.floor((diff % 3_600_000)  / 60_000);

            if (days > 0)       el.textContent = `${days}d ${hours}h remaining`;
            else if (hours > 0) el.textContent = `${hours}h ${mins}m remaining`;
            else                el.textContent = `${mins}m remaining`;

            if (diff < 86_400_000)          el.classList.add('overdue');
            else if (diff < 2 * 86_400_000) el.classList.add('due');
        };

        update();
        window.setInterval(update, 60_000);
    });

    /* ── Filter tabs ───────────────────────────────── */
    const filterBtns  = document.querySelectorAll('.ftab[data-filter]');
    const tableRows   = document.querySelectorAll('[data-status]');
    const searchInput = document.querySelector('#trackingSearch');

    if (filterBtns.length && tableRows.length) {
        const apply = () => {
            const active = document.querySelector('.ftab.active')?.dataset.filter ?? 'all';
            const term   = (searchInput?.value ?? '').trim().toLowerCase();
            tableRows.forEach((row) => {
                const matchStatus = active === 'all' || row.dataset.status === active;
                const matchSearch = !term || row.textContent.toLowerCase().includes(term);
                row.hidden = !(matchStatus && matchSearch);
            });
        };

        filterBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                filterBtns.forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                apply();
            });
        });

        searchInput?.addEventListener('input', apply);
    }

});
