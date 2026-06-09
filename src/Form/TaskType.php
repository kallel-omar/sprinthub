<?php

namespace App\Form;

use App\Entity\Label;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $projectMembers = $options['project_members'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Task Title',
                'attr' => [
                    'placeholder' => 'Enter task title',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Task title is required.',
                    ]),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Task title must be at least {{ limit }} characters.',
                        'max' => 255,
                        'maxMessage' => 'Task title cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Describe the task...',
                ],
                'constraints' => [
                    new Length([
                        'max' => 2000,
                        'maxMessage' => 'Description cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])

            ->add('priority', ChoiceType::class, [
                'label' => 'Priority',
                'choices' => [
                    'Low' => 'Low',
                    'Medium' => 'Medium',
                    'High' => 'High',
                ],
            ])

            ->add('dueDate', DateTimeType::class, [
                'label' => 'Due Date',
                'required' => false,
                'widget' => 'single_text',
            ])

            ->add('assignee', EntityType::class, [
                'class' => User::class,
                'choices' => $projectMembers,
                'choice_label' => 'fullName',
                'required' => false,
                'placeholder' => 'Select project member',
            ])

            ->add('labels', EntityType::class, [
                'class' => Label::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'project_members' => [],
        ]);
    }
}