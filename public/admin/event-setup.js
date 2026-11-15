const form = document.querySelector('[data-event-setup]');
if (form) {
    const list = form.querySelector('[data-show-list]');
    const update = () => {
        const cards = [...list.querySelectorAll('[data-show-card]')];
        cards.forEach((card, index) => { card.querySelector('[data-show-number]').textContent = index + 1; card.querySelector('[data-remove-show]').disabled = cards.length === 1; });
        form.querySelector('[data-add-show]').disabled = cards.length >= 12;
    };
    form.querySelector('[data-add-show]').addEventListener('click', () => {
        const index = Number(list.dataset.nextIndex);list.dataset.nextIndex = index + 1;
        list.insertAdjacentHTML('beforeend', list.dataset.prototype.replaceAll('__name__', String(index)));
        update();list.lastElementChild.querySelector('input').focus();
    });
    list.addEventListener('click', event => { const button = event.target.closest('[data-remove-show]'); if (button && !button.disabled) { button.closest('[data-show-card]').remove();update(); } });
    const counts = () => {
        const totals = {standard:0,vip:0,vvip:0};
        form.querySelectorAll('[data-category-row]').forEach(select => { totals[select.value] += Number(select.dataset.rowCount); });
        const target = form.querySelector('[data-category-counts]');target.replaceChildren();
        for (const [code, total] of Object.entries(totals)) { const item = document.createElement('span');item.className = 'seat-category-pill tier-' + code;item.textContent = `${code === 'standard' ? 'Standard' : code.toUpperCase()} · ${total} places`;target.append(item); }
    };
    form.addEventListener('change', counts);
    form.querySelector('[data-apply-tier]').addEventListener('click', () => { form.querySelectorAll('[data-category-row]').forEach(select => { select.value = form.querySelector('[data-bulk-tier]').value; });counts(); });
    update();counts();
}
