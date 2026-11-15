import {mountSeatMaps} from '../admin/seat-map.js';
import {mountRepresentationDates} from '../admin/seat-map-dates.js';
mountSeatMaps();
for (const screen of document.querySelectorAll('[data-store-seat-screen]')) {
    const root = screen.querySelector('[data-seatmap]');
    const proceed = (ids, authenticate) => {
        const url = new URL(screen.dataset.checkoutUrl, window.location.origin);
        for (const id of ids) url.searchParams.append('seats[]', id);
        if (authenticate) url.searchParams.set('authenticate', authenticate);
        window.location.assign(url);
    };
    root.addEventListener('seatmap:continue', event => proceed(event.detail.ids));
    for (const link of document.querySelectorAll('[data-auth-link]')) {
        link.addEventListener('click', event => {
            if (!root.seatMap.selected.size) return;
            event.preventDefault(); proceed([...root.seatMap.selected], link.dataset.authLink);
        });
    }
    const updateTotal = () => {
        const total = root.querySelector('[data-selected-total]');
        if (total) total.textContent = 'Total estimé au plein tarif : ' + new Intl.NumberFormat('fr-MA', {style:'currency', currency:'MAD'}).format(root.seatMap.selected.size * Number(total.dataset.unitPrice) / 100);
    };
    root.addEventListener('seatmap:selectionchange', updateTotal); updateTotal();
    mountRepresentationDates(screen, root);
}
