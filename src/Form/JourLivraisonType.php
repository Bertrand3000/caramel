<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\JourLivraison;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class JourLivraisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date',
            ])
            ->add('actif', CheckboxType::class, [
                'required' => false,
                'label' => 'Journée active',
            ])
            ->add('heureOuverture', TimeType::class, [
                'widget' => 'single_text',
                'with_seconds' => false,
                'label' => 'Heure ouverture',
            ])
            ->add('heureFermeture', TimeType::class, [
                'widget' => 'single_text',
                'with_seconds' => false,
                'label' => 'Heure fermeture',
            ])
            ->add('coupureMeridienne', CheckboxType::class, [
                'required' => false,
                'label' => 'Activer la coupure méridienne',
            ])
            ->add('heureCoupureDebut', TimeType::class, [
                'widget' => 'single_text',
                'with_seconds' => false,
                'required' => false,
                'label' => 'Début coupure',
            ])
            ->add('heureCoupureFin', TimeType::class, [
                'widget' => 'single_text',
                'with_seconds' => false,
                'required' => false,
                'label' => 'Fin coupure',
            ])
            ->add('exigerJourneePleine', CheckboxType::class, [
                'required' => false,
                'label' => 'Exiger que cette journée soit pleine avant de proposer la suivante',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => JourLivraison::class,
            'constraints' => [
                new Assert\Callback([$this, 'validateTemporalCoherence']),
            ],
        ]);
    }

    public function validateTemporalCoherence(mixed $data, ExecutionContextInterface $context): void
    {
        if (!$data instanceof JourLivraison) {
            return;
        }

        $ouverture = $data->getHeureOuverture();
        $fermeture = $data->getHeureFermeture();
        if ($ouverture >= $fermeture) {
            $context->buildViolation('L\'heure d\'ouverture doit être antérieure à l\'heure de fermeture.')
                ->atPath('heureFermeture')
                ->addViolation();
        }

        if (!$data->isCoupureMeridienne()) {
            return;
        }

        $debut = $data->getHeureCoupureDebut();
        $fin = $data->getHeureCoupureFin();
        if ($debut === null || $fin === null) {
            $context->buildViolation('Les heures de coupure sont obligatoires si la coupure méridienne est active.')
                ->atPath('heureCoupureDebut')
                ->addViolation();

            return;
        }

        if ($debut <= $ouverture) {
            $context->buildViolation('Le début de coupure doit être après l\'ouverture.')
                ->atPath('heureCoupureDebut')
                ->addViolation();
        }

        if ($fin >= $fermeture) {
            $context->buildViolation('La fin de coupure doit être avant la fermeture.')
                ->atPath('heureCoupureFin')
                ->addViolation();
        }

        if ($debut >= $fin) {
            $context->buildViolation('Le début de coupure doit être avant la fin de coupure.')
                ->atPath('heureCoupureDebut')
                ->addViolation();
        }
    }
}
