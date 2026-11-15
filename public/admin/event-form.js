const input = document.querySelector('[data-event-image-input]');
const preview = document.querySelector('[data-event-image-preview]');
if (input && preview) {
    const image = preview.querySelector('img');
    const label = preview.querySelector('[data-event-image-name]');
    const original = image.getAttribute('src');
    let temporaryUrl;
    input.addEventListener('change', () => {
        if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
        const file = input.files[0];
        if (file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            temporaryUrl = URL.createObjectURL(file);
            image.src = temporaryUrl;
            label.textContent = file.name;
            preview.hidden = false;
        } else {
            if (original) image.src = original; else image.removeAttribute('src');
            preview.hidden = !original;
            label.textContent = 'Visuel actuel';
        }
    });
    window.addEventListener('pagehide', () => { if (temporaryUrl) URL.revokeObjectURL(temporaryUrl); });
}
