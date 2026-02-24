<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\ProduitEtatEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class)
            ->add('etat', ChoiceType::class, ['choices' => ProduitEtatEnum::cases(), 'choice_label' => static fn (ProduitEtatEnum $e) => $e->value])
            ->add('etage', TextType::class)
            ->add('porte', TextType::class)
            ->add('tagTeletravailleur', CheckboxType::class, ['required' => false])
            ->add('largeur', NumberType::class, [
                'required' => true,
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('hauteur', NumberType::class, [
                'required' => true,
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('profondeur', NumberType::class, [
                'required' => true,
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('photoProduit', FileType::class, ['mapped' => false, 'required' => $options['photo_required']])
            ->add('photoNumeroInventaire', FileType::class, ['mapped' => false, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'photo_required' => true]);
        $resolver->setAllowedTypes('photo_required', 'bool');
    }
}
