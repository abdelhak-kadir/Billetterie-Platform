const form = document.querySelector('[data-checkout]');
if (form) {
    if (matchMedia('(max-width:700px)').matches) document.querySelector('[data-checkout-seats]').open = false;
    const update = () => {
        const total = [...form.querySelectorAll('[data-line-tariff]')].reduce((sum, select) => sum + Number(select.selectedOptions[0]?.dataset.price || 0), 0);
        document.querySelector('[data-checkout-total]').textContent = new Intl.NumberFormat('fr-MA', {style: 'currency', currency: 'MAD'}).format(total / 100);
    };
    form.addEventListener('change', update); update();
}
