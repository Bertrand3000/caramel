<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\ProduitEtatEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroInventaire', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^[0-9.-]+$/',
                        'message' => 'Le numero inventaire doit contenir uniquement des chiffres, des tirets et des points.',
                    ]),
                ],
                'attr' => [
                    'pattern' => '[0-9.-]*',
                    'inputmode' => 'numeric',
                    'placeholder' => '85.2000.100.2000',
                ],
            ])
            ->add('libelle', TextType::class)
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr'     => ['rows' => 3, 'placeholder' => 'Description facultative du produit…'],
            ])
            ->add('etat', ChoiceType::class, [
                'choices' => ProduitEtatEnum::cases(),
                'choice_label' => static function (ProduitEtatEnum $etat): string {
                    return match ($etat) {
                        ProduitEtatEnum::TRES_BON_ETAT => 'Très Bon Etat',
                        ProduitEtatEnum::BON => 'Bon Etat',
                        ProduitEtatEnum::ABIME => 'Abîmé',
                    };
                },
            ])
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
