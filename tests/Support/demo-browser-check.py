"""Recette Chromium sur un jeu de démonstration jetable, jamais sur une base à garder."""
import argparse
from pathlib import Path

from playwright.sync_api import expect, sync_playwright


parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--base-url', default='http://localhost:8080')
parser.add_argument('--output-dir', default='/tmp/horizon-browser-qa')
args = parser.parse_args()
base = args.base_url.rstrip('/')
output = Path(args.output_dir)
output.mkdir(parents=True, exist_ok=True)


def login(page, role):
    page.goto(base + '/connexion')
    page.locator('[name="_username"]').fill(role + '@example.com')
    page.locator('[name="_password"]').fill('HorizonDemo2026!')
    page.get_by_role('button', name='Se connecter', exact=True).click()
    expected = {'client': '/compte', 'guichet': '/guichet', 'admin': '/admin'}[role]
    expect(page).to_have_url(base + expected)


def capture(page, name):
    page.evaluate('document.fonts.ready')
    page.evaluate("window.scrollTo({top: 0, left: 0, behavior: 'instant'})")
    overflow = page.evaluate('document.documentElement.scrollWidth - window.innerWidth')
    assert overflow <= 1, f'{name}: débordement horizontal global de {overflow}px'
    for element in page.locator('.footer-inner > *').all():
        assert element.evaluate('(el) => el.getBoundingClientRect().right <= window.innerWidth'), name + ': pied de page tronqué'
    page.screenshot(path=str(output / (name + '.png')), full_page=True)


def seat(page, number):
    return page.locator(f'[data-label="Parterre, rangée A, place {number}"]')


def public_ui(page, device):
    page.goto(base + '/connexion')
    expect(page.locator('h1')).to_have_text('Connexion')
    page.locator('[data-password-toggle]').click()
    expect(page.locator('#password')).to_have_attribute('type', 'text')
    page.locator('[data-password-toggle]').click()
    expect(page.locator('#password')).to_have_attribute('type', 'password')
    capture(page, device + '-connexion')
    page.locator('#email').fill('client@example.com')
    page.locator('#password').fill('mot-de-passe-incorrect')
    page.get_by_role('button', name='Se connecter', exact=True).click()
    expect(page.locator('#login-error')).to_contain_text('Connexion refusée')
    capture(page, device + '-connexion-refusee')
    page.goto(base + '/inscription')
    expect(page.locator('h1')).to_have_text('Créer un compte')
    capture(page, device + '-inscription')
    email = 'nouveau-' + device + '@example.com'
    page.locator('#form_email').fill(email)
    page.locator('#form_password').fill('HorizonDemo2026!')
    page.get_by_role('button', name='Créer mon compte', exact=True).click()
    expect(page).to_have_url(base + '/connexion')
    page.locator('#email').fill(email)
    page.locator('#password').fill('HorizonDemo2026!')
    page.get_by_role('button', name='Se connecter', exact=True).click()
    expect(page).to_have_url(base + '/compte')
    page.get_by_role('button', name='Déconnexion', exact=True).click()
    response = page.goto(base + '/page-qui-nexiste-pas')
    assert response.status == 404
    expect(page.locator('h1')).to_have_text('Cette page est introuvable.')
    capture(page, device + '-404')
    page.get_by_role('link', name='Voir les événements').click()
    expect(page).to_have_url(base + '/')


def admin_ui(page, device):
    for route in ('representations', 'tarifs', 'inventaire', 'indicateurs'):
        page.goto(base + '/admin/' + route)
        with page.expect_navigation() as response:
            page.locator('form.filters button[type=submit]').click()
        assert response.value.status == 200, route + ': filtre vide refusé'
        assert page.locator('body.error-page').count() == 0
    page.goto(base + '/admin/evenements/new')
    title = 'Affiche test ' + device
    page.locator('#evenement_form_titre').fill(title)
    page.locator('#evenement_form_type').select_option('concert')
    page.locator('#evenement_form_image').set_input_files('public/admin/media/concert.webp')
    preview = page.locator('[data-event-image-preview] img')
    expect(preview).to_be_visible()
    page.wait_for_function('document.querySelector("[data-event-image-preview] img").naturalWidth > 0')
    capture(page, device + '-evenement-visuel')
    page.get_by_role('button', name='Enregistrer', exact=True).click()
    expect(page.locator('h1')).to_have_text(title)
    src = page.locator('.hero-image').get_attribute('src')
    assert src.startswith('/media/events/')
    response = page.request.get(base + src)
    assert response.status == 200 and response.headers['content-type'] == 'image/webp'
    capture(page, device + '-evenement-enregistre')
    page.goto(base + '/')
    expect(page.locator('.poster-link').filter(has_text=title).locator('img')).to_have_attribute('src', src)


def event_workspace(page, device, browser, options):
    page.goto(base + '/admin/evenements/creer')
    page.locator('#event_setup_evenement_titre').fill('Festival complet ' + device)
    page.locator('#event_setup_evenement_type').select_option('concert')
    page.locator('#event_setup_shows_0_nom').fill('Ouverture ' + device)
    page.locator('#event_setup_shows_0_debut').fill('2099-12-10T19:00')
    page.locator('[data-add-show]').click()
    page.locator('#event_setup_shows_1_nom').fill('Concert principal ' + device)
    page.locator('#event_setup_shows_1_debut').fill('2099-12-10T20:30')
    page.locator('#event_setup_shows_1_vipPrice').fill('250')
    expect(page.locator('[data-show-card]')).to_have_count(2)
    capture(page, device + '-creation-complete')
    page.get_by_role('button', name='Créer l’événement et ses spectacles', exact=True).click()
    expect(page.locator('h1')).to_have_text('Festival complet ' + device)
    expect(page.locator('.show-card')).to_have_count(2)
    capture(page, device + '-espace-evenement')
    first_admin = page.locator('.session').filter(has_text='Ouverture ' + device).get_attribute('href')
    second_admin = page.locator('.session').filter(has_text='Concert principal ' + device).get_attribute('href')
    event_id = page.url.split('/evenements/')[1].split('?')[0].split('#')[0]
    customer = browser.new_context(**options)
    shop = customer.new_page()
    login(shop, 'client')
    shop.goto(base + '/evenements/' + event_id)
    expect(shop.locator('.session')).to_have_count(2)
    first = shop.locator('.session').filter(has_text='Ouverture ' + device).get_attribute('href')
    second = shop.locator('.session').filter(has_text='Concert principal ' + device).get_attribute('href')
    shop.goto(base + first)
    chosen = shop.locator('[data-category-code=vip][data-status=disponible]').first
    label = chosen.get_attribute('data-label')
    expect(chosen).to_have_attribute('data-price-centimes', '20000')
    chosen.click()
    expect(shop.locator('.seat-category-legend')).to_contain_text('VVIP')
    capture(shop, device + '-plan-categories')
    shop.locator('[data-seatmap-continue]').click()
    expect(shop.locator('[data-line-tariff] option')).to_have_count(1)
    expect(shop.locator('[data-line-tariff]')).to_contain_text('VIP')
    shop.get_by_role('button', name='Réserver ces places', exact=True).click()
    shop.get_by_role('button', name='Simuler un paiement réussi', exact=True).click()
    expect(shop.locator('.store-status')).to_have_text('Payée')
    shop.get_by_role('link', name='Voir mon billet').click()
    expect(shop.locator('h1')).to_have_text('Ouverture ' + device)
    with shop.expect_download() as result:
        shop.get_by_role('link', name='Télécharger le billet PDF', exact=True).click()
    download = result.value
    assert Path(download.path()).read_bytes().startswith(b'%PDF-');download.delete()
    shop.goto(base + first)
    expect(shop.locator('[data-label="' + label + '"]')).to_have_attribute('data-status', 'vendue')
    shop.goto(base + second)
    expect(shop.locator('[data-label="' + label + '"]')).to_have_attribute('data-status', 'disponible')
    expect(shop.locator('[data-label="' + label + '"]')).to_have_attribute('data-price-centimes', '25000')
    page.goto(base + first_admin)
    expect(page.locator('h1')).to_have_text('Ouverture ' + device)
    capture(page, device + '-espace-spectacle')
    page.goto(base + first_admin + '/ventes')
    expect(page.locator('.tariff-card')).to_have_count(1)
    page.goto(base + second_admin + '/ventes')
    expect(page.locator('.tariff-card')).to_have_count(0)
    customer.close()
    print(f'{device}: création complète de deux spectacles, prix VIP/VVIP, achat VIP/PDF et inventaires indépendants : OK')


def buy(page, number, name, counter=False):
    chosen = seat(page, number)
    expect(chosen).to_have_attribute('data-status', 'disponible')
    chosen.click()
    expect(page.locator('[data-selected-count]')).to_have_text('1')
    # Le clavier doit déplacer le focus et permettre de retirer puis reprendre le siège.
    chosen.press('ArrowRight')
    adjacent = seat(page, number + 1)
    expect(adjacent).to_be_focused()
    adjacent.press('ArrowLeft')
    expect(chosen).to_be_focused()
    chosen.press('Enter')
    expect(page.locator('[data-selected-count]')).to_have_text('0')
    chosen.press('Enter')
    expect(page.locator('[data-selected-count]')).to_have_text('1')
    if page.viewport_size['width'] <= 700:
        assert not page.locator('[data-selection-details]').evaluate('(el) => el.open')
    capture(page, name + '-plan')
    page.locator('[data-seatmap-continue]').click()
    expect(page.get_by_role('heading', name='Tarifs et récapitulatif', exact=True)).to_be_visible()
    if counter:
        page.get_by_label('E-mail du destinataire').fill('destinataire@example.com')
        reduced = page.locator('[data-line-tariff] option').filter(has_text='Réduit').first
        page.locator('[data-line-tariff]').select_option(value=reduced.get_attribute('value'))
    capture(page, name + '-tarifs')
    page.get_by_role('button', name='Réserver ces places', exact=True).click()
    page.get_by_role('button', name='Simuler un paiement réussi', exact=True).click()
    expect(page.locator('.store-status')).to_have_text('Payée')
    page.get_by_role('link', name='Voir mon billet').click()
    capture(page, name + '-billet')
    with page.expect_download() as result:
        page.get_by_role('link', name='Télécharger le billet PDF', exact=True).click()
    download = result.value
    assert download.failure() is None
    assert Path(download.path()).read_bytes().startswith(b'%PDF-')
    download.delete()  # Les billets privés ne sont pas joints aux captures publiques.


with sync_playwright() as playwright:
    browser = playwright.chromium.launch()
    for device, viewport, client_seat, counter_seat in [
        ('desktop', {'width': 1440, 'height': 1000}, 6, 8),
        ('mobile', {'width': 390, 'height': 844}, 7, 9),
    ]:
        options = dict(viewport=viewport, locale='fr-FR', accept_downloads=True,
                       is_mobile=device == 'mobile', has_touch=device == 'mobile')
        client = browser.new_context(**options)
        client_page = client.new_page()
        public_ui(client_page, device)
        login(client_page, 'client')
        client_page.goto(base + '/')
        capture(client_page, device + '-catalogue')
        client_page.locator('.poster-link').filter(has_text='Concert Horizon').click()
        r1 = client_page.locator('.session').filter(has_text='01/12/2026').get_attribute('href')
        r2 = client_page.locator('.session').filter(has_text='02/12/2026').get_attribute('href')
        client_page.goto(base + r1)
        buy(client_page, client_seat, device + '-client')

        counter = browser.new_context(**options)
        counter_page = counter.new_page()
        login(counter_page, 'guichet')
        counter_page.goto(base + '/guichet' + r1)
        sold = seat(counter_page, client_seat)
        expect(sold).to_have_attribute('data-status', 'vendue')
        # Déclencher l'événement malgré aria-disabled vérifie aussi le garde-fou JS.
        sold.dispatch_event('click')
        expect(counter_page.locator('[data-selected-count]')).to_have_text('0')
        buy(counter_page, counter_seat, device + '-guichet', counter=True)

        client_page.goto(base + r1)
        expect(seat(client_page, counter_seat)).to_have_attribute('data-status', 'vendue')
        counter_page.goto(base + '/guichet' + r2)
        for number in (client_seat, counter_seat):
            expect(seat(counter_page, number)).to_have_attribute('data-status', 'disponible')

        admin = browser.new_context(**options)
        admin_page = admin.new_page()
        login(admin_page, 'admin')
        capture(admin_page, device + '-admin')
        admin_page.goto(base + '/admin/indicateurs')
        expect(admin_page.locator('[data-report-gross]')).to_be_visible()
        expect(admin_page.get_by_role('heading', name='Google Sheets', exact=True)).to_be_visible()
        capture(admin_page, device + '-indicateurs')
        admin_ui(admin_page, device)
        event_workspace(admin_page, device, browser, options)
        for context in (client, counter, admin):
            context.close()
        print(f'{device}: 3 connexions, achat client, vente guichet, PDF, clavier, stock R1/R2 et largeurs : OK')
        print(f'{device}: inscription, connexion refusée, mot de passe affichable, 404, filtres vides, visuel créé et publié : OK')
    browser.close()
