<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

class ParametreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('boutique_ouverte_agents', CheckboxType::class, ['required' => false])
            ->add('boutique_ouverte_teletravailleurs', CheckboxType::class, ['required' => false])
            ->add('boutique_ouverte_partenaires', CheckboxType::class, ['required' => false])
            ->add('max_produits_par_commande', IntegerType::class);
    }
}
