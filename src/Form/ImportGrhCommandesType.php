<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

class ImportGrhCommandesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('xlsxFile', FileType::class, [
            'mapped' => false,
            'required' => true,
            'label' => 'Fichier GRH (.xlsx)',
            'constraints' => [
                new File([
                    'mimeTypes' => [
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'application/octet-stream',
                    ],
                ]),
            ],
        ]);
    }
}
