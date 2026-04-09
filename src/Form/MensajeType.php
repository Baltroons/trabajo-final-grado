<?php

namespace App\Form;

use App\Entity\Mensaje;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class MensajeType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('contenido', TextareaType::class, [
            'label' => false,
            'attr' => [
                'placeholder' => 'Escribe una duda o comparte algo...',
                'class' => 'w-full rounded-2xl border-gray-200 focus:ring-emerald-500 focus:border-emerald-500 p-4 text-sm',
                'rows' => 2
            ]
        ]);
    }

}
