<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Enum\TypeEvenement;
use App\Form\Model\EvenementFormData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['maxlength' => 255],
            ])
            ->add('type', EnumType::class, [
                'class' => TypeEvenement::class,
                'label' => 'Type',
                'choice_label' => static fn (TypeEvenement $type): string => $type->label(),
                'placeholder' => 'Choisir un type',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 5, 'maxlength' => 5000],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EvenementFormData::class,
            'csrf_message' => 'Le jeton de sécurité est invalide. Merci de réessayer.',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'evenement_form';
    }
}
