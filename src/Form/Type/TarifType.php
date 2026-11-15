<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Representation;
use App\Entity\Tarif;
use App\Enum\CategorieTarif;
use App\Form\Model\TarifFormData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TarifType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('representation', EntityType::class, [
                'class' => Representation::class,
                'label' => 'Représentation',
                'choice_label' => static function (Representation $representation): string {
                    return sprintf(
                        '%s — %s (Africa/Casablanca)',
                        $representation->getEvenement()->getTitre(),
                        \DateTimeImmutable::createFromInterface($representation->getDebut())
                            ->setTimezone(new \DateTimeZone('Africa/Casablanca'))
                            ->format('d/m/Y H:i'),
                    );
                },
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('r')
                    ->join('r.evenement', 'e')
                    ->addSelect('e')
                    ->orderBy('r.debut', 'ASC'),
                'disabled' => $options['representation_locked'],
                'help' => $options['representation_locked']
                    ? 'Ce tarif a déjà été utilisé : sa représentation ne peut plus être changée.'
                    : null,
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['maxlength' => 100],
            ])
            ->add('categorie', EnumType::class, [
                'class' => CategorieTarif::class,
                'label' => 'Catégorie',
                'choice_label' => static fn (CategorieTarif $categorie): string => $categorie->label(),
                'placeholder' => 'Choisir une catégorie',
            ])
            ->add('prixCentimes', MoneyMadType::class, [
                'label' => 'Prix (MAD)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TarifFormData::class,
            'representation_locked' => false,
            'csrf_message' => 'Le jeton de sécurité est invalide. Merci de réessayer.',
        ]);
        $resolver->setAllowedTypes('representation_locked', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'tarif_form';
    }
}
