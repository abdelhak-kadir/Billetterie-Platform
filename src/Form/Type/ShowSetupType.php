<?php
declare(strict_types=1);
namespace App\Form\Type;
use App\Form\Model\ShowSetupData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\{TextType,DateTimeType};
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ShowSetupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nom', TextType::class, ['label' => 'Nom du spectacle', 'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. : Concert principal · Session acoustique']])
            ->add('debut', DateTimeType::class, ['label' => 'Date et heure', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => 'Africa/Casablanca'])
            ->add('standardPrice', MoneyMadType::class, ['label' => 'Standard (MAD)'])
            ->add('vipPrice', MoneyMadType::class, ['label' => 'VIP (MAD)'])
            ->add('vvipPrice', MoneyMadType::class, ['label' => 'VVIP (MAD)']);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => ShowSetupData::class]); }
}
