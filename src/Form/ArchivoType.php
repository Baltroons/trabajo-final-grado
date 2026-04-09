<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;

class ArchivoType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('documento', FileType::class, [
            'label' => 'Subir archivo (PDF o Imagen)',
            'mapped' => false, // No está en la entidad directamente
            'required' => true,
            // src/Form/ArchivoType.php

            'constraints' => [
                new File(
                    maxSize: '5M',
                    mimeTypes: ['application/pdf', 'image/jpeg', 'image/png'],
                    mimeTypesMessage: 'Por favor sube un PDF o imagen válida'
                )
            ],
            'attr' => ['class' => 'block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100']
        ]);
    }

}
