<?php

namespace App\Form;

use App\Entity\Workspace;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class WorkspaceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Workspace Name',
                'attr' => [
                    'placeholder' => 'Enter workspace name',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Workspace name is required.',
                    ]),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Workspace name must be at least {{ limit }} characters.',
                        'max' => 100,
                        'maxMessage' => 'Workspace name cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Workspace::class,
        ]);
    }
}