<?php

namespace App\Form;

use App\Entity\Sala;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SalaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'attr' => ['placeholder' => 'Ej: Matemáticas Avanzadas', 'class' => 'w-full rounded-lg border-gray-300 focus:ring-emerald-500 focus:border-emerald-500']
            ])
            ->add('descripcion', TextareaType::class, [
                'attr' => ['rows' => 4, 'placeholder' => '¿De qué trata esta sala?', 'class' => 'w-full rounded-lg border-gray-300 focus:ring-emerald-500 focus:border-emerald-500']
            ])
            ->add('categoria', ChoiceType::class, [
                'choices'  => [
                    'Programación' => 'Programación',
                    'Matemáticas' => 'Matemáticas',
                    'Diseño' => 'Diseño',
                    'Idiomas' => 'Idiomas',
                    'Otros' => 'Otros',
                ],
                'attr' => ['class' => 'w-full rounded-lg border-gray-300 focus:ring-emerald-500 focus:border-emerald-500']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sala::class,
        ]);
    }
}
