<?php

namespace App\Form;

use App\Entity\TaskChecklistItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TaskChecklistItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Add checklist item...',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Checklist item cannot be empty.',
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Checklist item must be at least {{ limit }} characters.',
                        'max' => 255,
                        'maxMessage' => 'Checklist item cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskChecklistItem::class,
        ]);
    }
}