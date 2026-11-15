from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin
import argparse
import urllib.request
import urllib.parse
import http.cookiejar
import json

class HttpResponse:
    def __init__(self, response):
        self.status_code=response.status; self.url=response.url; self.headers=response.headers
        self.content=response.read(); self.text=self.content.decode("utf-8",errors="replace")
    def raise_for_status(self):
        assert self.status_code<400,self.status_code

class Session:
    def __init__(self):
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def get(self,url,timeout=30):
        return HttpResponse(self.opener.open(url,timeout=timeout))
    def post(self,url,data,headers,timeout=30):
        req=urllib.request.Request(url,data=urllib.parse.urlencode(data).encode(),headers=headers)
        return HttpResponse(self.opener.open(req,timeout=timeout))


parser=argparse.ArgumentParser()
parser.add_argument('--base-url',default='http://localhost:8080')
parser.add_argument('--expect-existing',action='store_true')
args=parser.parse_args()
BASE=args.base_url.rstrip('/')
class Node:
    def __init__(self, tag='', attrs=()):
        self.tag, self.attrs, self.children = tag, dict(attrs), []
    def all(self, tag=None):
        result=[]
        for child in self.children:
            if isinstance(child, Node):
                if tag is None or child.tag == tag: result.append(child)
                result.extend(child.all(tag))
        return result
    def text(self):
        return ''.join(c.text() if isinstance(c, Node) else c for c in self.children)

class DOM(HTMLParser):
    def __init__(self, text):
        super().__init__(); self.root=Node(); self.stack=[self.root]; self.feed(text)
    def handle_starttag(self, tag, attrs):
        node=Node(tag, attrs); self.stack[-1].children.append(node)
        if tag not in ('area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'):
            self.stack.append(node)
    def handle_endtag(self, tag):
        for i in range(len(self.stack)-1, 0, -1):
            if self.stack[i].tag == tag:
                del self.stack[i:]; break
    def handle_data(self, data): self.stack[-1].children.append(data)

def get(session, path):
    response=session.get(urljoin(BASE,path),timeout=30)
    response.raise_for_status()
    return response, DOM(response.text).root

def submit(session, response, form, values=None):
    fields={x.attrs['name']:x.attrs.get('value','') for x in form.all('input') if 'name' in x.attrs}
    for select in form.all('select'):
        options=select.all('option')
        if options: fields[select.attrs['name']]=next((o for o in options if 'selected' in o.attrs),options[0]).attrs['value']
    fields.update(values or {})
    target=urljoin(response.url, form.attrs.get('action',response.url))
    result=session.post(target,data=fields,headers={'Origin':BASE,'Referer':response.url},timeout=30)
    result.raise_for_status()
    return result, DOM(result.text).root

def login(profile):
    session=Session()
    response, dom=get(session,'/connexion')
    form=next(f for f in dom.all('form') if any(i.attrs.get('name')=='_username' for i in f.all('input')))
    response,dom=submit(session,response,form,{'_username':profile+'@example.com','_password':'HorizonDemo2026!'})
    assert '/connexion' not in response.url, response.url
    expected={'client':'/compte','guichet':'/guichet','admin':'/admin'}[profile]
    assert response.url == BASE+expected, response.url
    print('Connexion '+profile+' : OK')
    return session

def seat(dom):
    return next(n for n in dom.all('button') if n.attrs.get('data-label')=='Parterre, rangée A, place 5')

client=login('client')
_,catalogue=get(client,'/')
event=next(a.attrs['href'] for a in catalogue.all('a') if 'Concert Horizon' in a.text())
_,event_dom=get(client,event)
reps=[a for a in event_dom.all('a') if a.attrs.get('href','').startswith('/representations/')]
r1=next(a.attrs['href'] for a in reps if '01/12/2026' in a.text())
r2=next(a.attrs['href'] for a in reps if '02/12/2026' in a.text())
_,plan=get(client,r1)
if args.expect_existing:
    assert seat(plan).attrs['data-status']=='vendue'
    seat_id=seat(plan).attrs['data-seat-id']
    _,account=get(client,'/compte')
    order_path=next(a.attrs['href'] for a in account.all('a') if a.attrs.get('href','').startswith('/commandes/'))
    response,order=get(client,order_path)
else:
    assert seat(plan).attrs['data-status']=='disponible'
    seat_id=seat(plan).attrs['data-seat-id']
    response,checkout=get(client,r1.replace('/representations/','/achat/').replace('/plan','')+'?seats[]='+seat_id)
    form=next(f for f in checkout.all('form') if any('Réserver ces places' in b.text() for b in f.all('button')))
    response,order=submit(client,response,form)
    assert '/commandes/' in response.url
    form=next(f for f in order.all('form') if any('Simuler un paiement réussi' in b.text() for b in f.all('button')))
    response,order=submit(client,response,form)
    assert 'Payée' in order.text(), order.text()[:500]
ticket_path=next(a.attrs['href'] for a in order.all('a') if 'Voir mon billet' in a.text())
_,ticket=get(client,ticket_path)
pdf_path=next(a.attrs['href'] for a in ticket.all('a') if 'Télécharger le billet PDF' in a.text())
pdf=client.get(urljoin(BASE,pdf_path),timeout=30)
pdf.raise_for_status()
assert pdf.headers['Content-Type'].startswith('application/pdf')
assert pdf.content.startswith(b'%PDF-')

# Le PDF privé est contrôlé en mémoire ; aucun billet dans le dépôt.
print('Achat Parterre/A5 sur R1, paiement simulé, PDF téléchargé : OK')

counter=login('guichet')
get(counter,'/guichet')
_,counter_r1=get(counter,'/guichet'+r1+'?seats[]='+seat_id)
assert seat(counter_r1).attrs['data-status']=='vendue'
assert seat(counter_r1).attrs['data-initial-selected']=='false'
_,counter_r2=get(counter,'/guichet'+r2)
assert seat(counter_r2).attrs['data-status']=='disponible'
print('Guichet : A5 vendue sur R1, libre sur R2 : OK')

admin=login('admin')
for path in ['/admin','/admin/indicateurs']:
    get(admin,path)
_,admin_r1=get(admin,'/admin'+r1)
assert seat(admin_r1).attrs['data-status']=='vendue'
print('Administration et indicateurs accessibles ; A5 vendue sur R1 : OK')
