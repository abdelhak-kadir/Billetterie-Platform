import {mountSeatMaps} from './seat-map.js';
import {mountRepresentationDates} from './seat-map-dates.js';
mountSeatMaps();
for (const screen of document.querySelectorAll('[data-admin-seat-screen]')) {
    const root = screen.querySelector('[data-seatmap]');
    root.addEventListener('seatmap:continue', event => {
        const url = new URL(screen.dataset.reservationUrl, window.location.origin);
        url.searchParams.set('from_plan', '1');
        for (const id of event.detail.ids) url.searchParams.append('seats[]', id);
        window.location.assign(url);
    });
    mountRepresentationDates(screen, root);
}
