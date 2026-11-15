/** Comportement de dialogue partagé ; les liens sont fournis par chaque écran. */
export function mountRepresentationDates(screen, root) {
    const dialog = screen.querySelector('[data-dates-dialog]');
    const opener = screen.querySelector('[data-open-dates]');
    const warning = screen.querySelector('[data-change-warning]');
    let pending = null;
    opener.addEventListener('click', () => { pending = null; warning.hidden = true; dialog.showModal(); });
    screen.querySelector('[data-close-dates]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pending = null; warning.hidden = true; opener.focus(); });
    for (const link of dialog.querySelectorAll('[data-change-representation]')) {
        link.addEventListener('click', event => {
            if (link.dataset.active === 'true') { event.preventDefault(); return; }
            if (root.seatMap.selected.size > 0) {
                event.preventDefault(); pending = link; warning.hidden = false;
                dialog.querySelector('[data-pending-count]').textContent = root.seatMap.selected.size;
                dialog.querySelector('[data-cancel-change]').focus();
            }
        });
    }
    dialog.querySelector('[data-cancel-change]').addEventListener('click', () => { warning.hidden = true; pending?.focus(); pending = null; });
    dialog.querySelector('[data-confirm-change]').addEventListener('click', () => { if (pending) window.location.assign(pending.href); });
}
