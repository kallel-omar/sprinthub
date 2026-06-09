<?php

namespace App\Form;

use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Project Name',
                'attr' => [
                    'placeholder' => 'Enter project name',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Project name is required.',
                    ]),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Project name must be at least {{ limit }} characters.',
                        'max' => 100,
                        'maxMessage' => 'Project name cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Describe your project...',
                ],
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Description cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}