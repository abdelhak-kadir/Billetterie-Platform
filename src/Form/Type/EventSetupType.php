<?php
declare(strict_types=1);
namespace App\Form\Type;
use App\Entity\Salle;
use App\Form\Model\EventSetupData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\{CollectionType,FormType,ChoiceType};
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EventSetupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['existing_event']) { $builder->add('evenement', EvenementType::class, ['label' => false]); }
        $builder->add('hall', EntityType::class, ['class' => Salle::class, 'choice_label' => 'nom', 'label' => 'Salle des spectacles', 'placeholder' => 'Choisir une salle'])
            ->add('shows', CollectionType::class, ['entry_type' => ShowSetupType::class, 'entry_options' => ['label' => false], 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false, 'label' => false, 'prototype' => true, 'prototype_data'=>new \App\Form\Model\ShowSetupData()]);
        $rows = $builder->create('rowCategories', FormType::class, ['label' => false]);
        foreach ($options['rows'] as $key => $row) {
            $rows->add($key, ChoiceType::class, ['label' => $row['label'], 'choices' => ['Standard' => 'standard', 'VIP' => 'vip', 'VVIP' => 'vvip'], 'attr' => ['data-category-row' => '', 'data-row-count' => $row['count'], 'data-row-zone' => $row['zone']]]);
        }
        $builder->add($rows);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => EventSetupData::class, 'rows' => [], 'existing_event' => false, 'csrf_message' => 'Votre session a expiré. Rechargez le formulaire.']);
        $resolver->setAllowedTypes('rows', 'array');$resolver->setAllowedTypes('existing_event', 'bool');
    }
    public function getBlockPrefix(): string { return 'event_setup'; }
}
