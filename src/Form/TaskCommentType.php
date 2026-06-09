<?php

namespace App\Form;

use App\Entity\TaskComment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TaskCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Write a comment...',
                    'rows' => 3,
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Comment cannot be empty.',
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Comment must be at least {{ limit }} characters.',
                        'max' => 1000,
                        'maxMessage' => 'Comment cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskComment::class,
        ]);
    }
}