<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Evenement;
use App\Entity\Representation;
use App\Entity\Salle;
use App\Form\Model\RepresentationFormData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RepresentationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('evenement', EntityType::class, [
                'class' => Evenement::class,
                'label' => 'Événement',
                'choice_label' => 'titre',
                'placeholder' => 'Choisir un événement',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('e')
                    ->orderBy('e.titre', 'ASC'),
            ])
            ->add('salle', EntityType::class, [
                'class' => Salle::class,
                'label' => 'Salle',
                'choice_label' => 'nom',
                'placeholder' => 'Choisir une salle',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('s')
                    ->orderBy('s.nom', 'ASC'),
                'disabled' => $options['salle_locked'],
                'help' => $options['salle_locked']
                    ? 'La salle ne peut plus être changée après la création de l’inventaire.'
                    : null,
            ])
            ->add('debut', DateTimeType::class, [
                'label' => 'Date et heure (Africa/Casablanca)',
                'widget' => 'single_text',
                'html5' => true,
                'with_seconds' => false,
                'input' => 'datetime_immutable',
                'model_timezone' => 'UTC',
                'view_timezone' => 'Africa/Casablanca',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RepresentationFormData::class,
            'salle_locked' => false,
            'csrf_message' => 'Le jeton de sécurité est invalide. Merci de réessayer.',
        ]);
        $resolver->setAllowedTypes('salle_locked', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'representation_form';
    }
}
