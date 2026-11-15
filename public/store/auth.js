document.querySelectorAll('[data-password-toggle]').forEach(button => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    button.hidden = false;
    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Masquer' : 'Afficher';
        button.setAttribute('aria-label', (visible ? 'Masquer' : 'Afficher') + ' le mot de passe');
        button.setAttribute('aria-pressed', String(visible));
    });
});
