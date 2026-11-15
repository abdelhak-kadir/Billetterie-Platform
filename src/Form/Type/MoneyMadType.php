<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Form\DataTransformer\CentimesTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MoneyMadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CentimesTransformer());
    }

    public function getParent(): string
    {
        return NumberType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'scale' => 2,
            'html5' => true,
            'grouping' => false,
            'attr' => [
                'step' => '0.01',
                'min' => '0',
                'inputmode' => 'decimal',
            ],
        ]);
    }
}
