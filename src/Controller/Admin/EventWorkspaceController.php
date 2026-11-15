<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\{Evenement,Salle};
use App\Entity\{Representation,Commande};
use App\Admin\{Pagination,QueryFilters};
use App\Form\Model\{EventSetupData,EvenementFormData};
use App\Form\Type\EventSetupType;
use App\Service\EventSetupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;

final class EventWorkspaceController extends AbstractController
{
    #[Route('/admin/representations/{id}/ventes',name:'admin_show_sales',requirements:['id'=>'\d+'],methods:['GET'])]
    public function sales(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $show = $em->find(Representation::class,$id) ?? throw $this->createNotFoundException();
        $query = $em->getRepository(Commande::class)->createQueryBuilder('c')->leftJoin('c.reservationItems','ri')->leftJoin('ri.inventairePlace','ii')->leftJoin('c.billets','b')->leftJoin('b.inventairePlace','bi')->where('ii.representation=:show OR bi.representation=:show')->setParameter('show',$show);
        $total = (int)(clone $query)->select('COUNT(DISTINCT c.id)')->getQuery()->getSingleScalarResult();
        $pagination = new Pagination(QueryFilters::positiveInt($request->query,'page',1),20,$total);
        $orders = $query->select('DISTINCT c')->orderBy('c.creeA','DESC')->setFirstResult($pagination->offset())->setMaxResults($pagination->limit)->getQuery()->getResult();
        return $this->render('admin/workspace/sales.html.twig',['representation'=>$show,'orders'=>$orders,'pagination'=>$pagination]);
    }
    #[Route('/admin/evenements/creer',name:'admin_event_setup',methods:['GET','POST'])]
    #[Route('/admin/evenements/{id}/spectacles/ajouter',name:'admin_event_add_shows',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    public function setup(Request $request, EntityManagerInterface $em, EventSetupService $setup, ?int $id = null): Response
    {
        $existing = $id !== null ? ($em->find(Evenement::class,$id) ?? throw $this->createNotFoundException()) : null;
        $halls = $em->getRepository(Salle::class)->findBy([],['nom'=>'ASC']);
        $posted = $request->isMethod('POST') ? $request->request->all('event_setup') : [];
        $hallId = is_scalar($posted['hall'] ?? null) ? filter_var($posted['hall'],FILTER_VALIDATE_INT) : false;
        $hall = $hallId ? $em->find(Salle::class,$hallId) : ($halls[0] ?? null);
        $rows = $hall !== null ? $setup::rows($hall) : [];
        $data = new EventSetupData();$data->hall = $hall;
        if ($existing !== null) { $data->evenement = EvenementFormData::fromEntity($existing); }
        foreach (array_keys($rows) as $index => $key) { $data->rowCategories[$key] = $index < 2 ? 'vvip' : ($index < 5 ? 'vip' : 'standard'); }
        $loadHall = $request->request->has('load_hall');
        if ($loadHall) { $request->request->set('event_setup',array_replace($posted,['rowCategories'=>$data->rowCategories])); }
        $form = $this->createForm(EventSetupType::class,$data,['rows'=>$rows,'existing_event'=>$existing!==null,'validation_groups'=>$loadHall ? false : null]);
        $form->handleRequest($request);
        if (!$loadHall && $form->isSubmitted() && $form->isValid()) {
            try {
                $event = $setup->create($data,$existing);
                $this->addFlash('success','L’événement, ses spectacles, leurs places et leurs prix sont prêts.');
                return $this->redirectToRoute('admin_evenement_show',['id'=>$event->getId()]);
            } catch (\InvalidArgumentException|\DomainException $e) { $form->addError(new FormError($e->getMessage())); }
            catch (FileException) { $form->addError(new FormError('Le visuel n’a pas pu être enregistré. Réessayez.')); }
        }
        return $this->render('admin/workspace/setup.html.twig',['form'=>$form->createView(),'existing'=>$existing,'rows'=>$rows,'hall'=>$hall]);
    }
}
