<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UtilisateurType extends AbstractType
{
    private const ROLES = [
        'Administrateur'        => 'ROLE_ADMIN',
        'DMAX'                  => 'ROLE_DMAX',
        'Agent'                 => 'ROLE_AGENT',
        'Télétravailleur'       => 'ROLE_TELETRAVAILLEUR',
        'Partenaire'            => 'ROLE_PARTENAIRE',
        'Agent de récupération' => 'ROLE_AGENT_RECUPERATION',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('login', TextType::class, [
                'label'       => 'Identifiant',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 2, 'max' => 180]),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label'    => 'Rôle(s)',
                'choices'  => self::ROLES,
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('actif', CheckboxType::class, [
                'label'    => 'Compte actif',
                'required' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label'       => $isEdit
                    ? 'Nouveau mot de passe (laisser vide pour ne pas changer)'
                    : 'Mot de passe',
                'mapped'      => false,
                'required'    => !$isEdit,
                'constraints' => $isEdit ? [] : [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 6]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
            'is_edit'    => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
