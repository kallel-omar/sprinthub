<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\WorkspaceMember;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class WorkspaceMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'placeholder' => 'Select a user',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a user.',
                    ]),
                ],
            ])
            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Member' => 'member',
                    'Admin' => 'admin',
                ],
                'data' => 'member',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a role.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkspaceMember::class,
        ]);
    }
}