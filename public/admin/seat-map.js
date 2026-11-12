/** Plan partagé : état local uniquement. Les écrans adaptent les événements aux parcours autorisés. */
export class SeatMap {
    constructor(root) {
        this.root = root;
        this.seats = [...root.querySelectorAll('[data-seat-id]')];
        this.byId = new Map(this.seats.map(seat => [seat.dataset.seatId, seat]));
        this.selected = new Set(this.seats.filter(s => s.dataset.initialSelected === 'true' && s.dataset.status === 'disponible').map(s => s.dataset.seatId));
        this.mode = root.dataset.mode;
        root.addEventListener('click', event => this.click(event));
        root.addEventListener('keydown', event => this.keydown(event));
        root.querySelectorAll('.h-modes input').forEach(input => input.addEventListener('change', () => {
            this.mode = input.value;
            this.update();
            this.announce(this.mode === 'select' ? 'Mode Sélectionner : seules les places libres peuvent être ajoutées.' : 'Mode Inspecter : cliquez sur un siège pour consulter son état.');
        }));
        // Un seul arrêt Tab dans le plan ; les flèches parcourent ensuite tous les sièges.
        this.seats.forEach((seat, index) => seat.tabIndex = index === 0 ? 0 : -1);
        // Le récapitulatif collant ne doit pas recouvrir le dernier siège au clavier.
        this.selection = root.querySelector('.h-selection');
        if (typeof ResizeObserver !== 'undefined') {
            this.selectionObserver = new ResizeObserver(() => this.measureSelection());
            this.selectionObserver.observe(this.selection);
        }
        this.update();
    }

    announce(text) { this.root.querySelector('[data-inspection]').textContent = text; }

    measureSelection() {
        this.root.style.setProperty('--h-selection-height', `${Math.ceil(this.selection.getBoundingClientRect().height)}px`);
    }

    click(event) {
        const seat = event.target.closest('[data-seat-id]');
        if (seat && this.root.contains(seat)) {
            if (this.mode === 'inspect') {
                this.announce(`${seat.dataset.label}, ${seat.dataset.statusLabel}.`);
            } else if (seat.dataset.status === 'disponible' && this.root.dataset.selectable === 'true') {
                this.selected.has(seat.dataset.seatId) ? this.selected.delete(seat.dataset.seatId) : this.selected.add(seat.dataset.seatId);
                this.update();
                this.announce(`${seat.dataset.label}, ${this.selected.has(seat.dataset.seatId) ? 'sélectionné localement' : 'retiré de la sélection'}.`);
            } else {
                this.announce(`${seat.dataset.label}, ${seat.dataset.statusLabel}. Cette place ne peut pas être sélectionnée.`);
            }
            this.focus(seat);
        }
        const remove = event.target.closest('[data-remove-seat]');
        if (remove) {
            const id = remove.dataset.removeSeat;
            this.selected.delete(id);
            this.update();
            this.focus(this.byId.get(id));
            this.announce('Siège retiré de la sélection locale.');
        }
        if (event.target.closest('[data-clear-selection]')) {
            this.selected.clear(); this.update();
            if (this.seats[0]) this.focus(this.seats[0]);
            this.announce('Sélection locale vidée.');
        }
        if (event.target.closest('[data-seatmap-continue]') && this.selected.size) {
            this.root.dispatchEvent(new CustomEvent('seatmap:continue', {bubbles: true, detail: {ids: [...this.selected]}}));
        }
    }

    focus(seat) {
        if (!seat) return;
        this.seats.forEach(s => s.tabIndex = s === seat ? 0 : -1);
        seat.focus({preventScroll: true});
        seat.scrollIntoView({block: 'nearest', inline: 'nearest', behavior: 'instant'});
        // scrollIntoView ne connaît pas le récapitulatif collant dans la page.
        // Révéler aussi le siège dans la fenêtre, après le défilement du plan.
        if (this.selected.size) {
            let covered = seat.getBoundingClientRect().bottom - this.selection.getBoundingClientRect().top + 16;
            if (covered > 0) window.scrollBy({top: covered, behavior: 'instant'});
            covered = seat.getBoundingClientRect().bottom - this.selection.getBoundingClientRect().top + 16;
            if (covered > 0) this.root.querySelector('.h-map-viewport').scrollBy({top: covered, behavior: 'instant'});
        }
    }

    keydown(event) {
        const seat = event.target.closest('[data-seat-id]');
        if (!seat) return;
        const rows = [...this.root.querySelectorAll('[data-seat-row]')];
        const row = seat.closest('[data-seat-row]');
        const seats = [...row.querySelectorAll('[data-seat-id]')];
        const index = seats.indexOf(seat);
        let target;
        if (event.key === 'ArrowLeft') target = seats[index - 1];
        if (event.key === 'ArrowRight') target = seats[index + 1];
        if (event.key === 'Home') target = seats[0];
        if (event.key === 'End') target = seats.at(-1);
        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            const nextRow = rows[rows.indexOf(row) + (event.key === 'ArrowUp' ? -1 : 1)];
            if (nextRow) {
                const options = [...nextRow.querySelectorAll('[data-seat-id]')];
                target = options.find(s => s.dataset.number === seat.dataset.number) || options[Math.min(index, options.length - 1)];
            }
        }
        if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) {
            event.preventDefault(); this.focus(target);
        }
    }

    update() {
        this.root.dataset.mode = this.mode;
        this.root.dataset.hasSelection = String(this.selected.size > 0);
        for (const seat of this.seats) {
            const selected = this.selected.has(seat.dataset.seatId);
            seat.classList.toggle('h-is-selected', selected);
            seat.setAttribute('aria-pressed', String(selected));
            seat.setAttribute('aria-disabled', String(this.mode === 'select' && seat.dataset.status !== 'disponible'));
            seat.setAttribute('aria-label', `${seat.dataset.label}, ${selected ? 'sélectionné' : seat.dataset.statusLabel}`);
            seat.querySelector('.h-seat-symbol').textContent = selected ? '✓' : ({reservee: '◷', vendue: '×', bloquee: '−'}[seat.dataset.status] || '');
        }
        const list = this.root.querySelector('[data-selection-list]');
        list.replaceChildren();
        for (const id of this.selected) {
            const item = document.createElement('li');
            const text = document.createElement('span');
            text.textContent = this.byId.get(id).dataset.label;
            const remove = document.createElement('button');
            remove.type = 'button'; remove.dataset.removeSeat = id;
            remove.textContent = '×'; remove.setAttribute('aria-label', 'Retirer ' + text.textContent);
            item.append(text, remove); list.append(item);
        }
        this.root.querySelector('[data-selected-count]').textContent = this.selected.size;
        this.root.querySelector('[data-selection-empty]').hidden = this.selected.size > 0;
        this.root.querySelector('[data-clear-selection]').disabled = this.selected.size === 0;
        this.root.querySelector('[data-seatmap-continue]').hidden = this.selected.size === 0;
        this.measureSelection();
        this.root.dispatchEvent(new CustomEvent('seatmap:selectionchange', {bubbles: true, detail: {ids: [...this.selected], mode: this.mode}}));
    }
}

export function mountSeatMaps(scope = document) {
    return [...scope.querySelectorAll('[data-seatmap]')].map(root => {
        if (!root.seatMap) root.seatMap = new SeatMap(root);
        return root.seatMap;
    });
}
