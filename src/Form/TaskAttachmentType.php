<?php

namespace App\Form;

use App\Entity\TaskAttachment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

class TaskAttachmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'mapped' => false,
            'label' => 'Upload File',
            'required' => true,
            'constraints' => [
                new NotNull([
                    'message' => 'Please select a file.',
                ]),
                new File([
                    'maxSize' => '10M',
                    'maxSizeMessage' => 'File size cannot exceed 10 MB.',
                    'mimeTypesMessage' => 'Invalid file format.',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskAttachment::class,
        ]);
    }
}