<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Full Name',
                'attr' => [
                    'placeholder' => 'Enter your full name',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Full name is required.',
                    ]),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Full name must be at least {{ limit }} characters.',
                        'max' => 100,
                        'maxMessage' => 'Full name cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])

            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'disabled' => $options['email_locked'],
                'attr' => [
                    'placeholder' => 'Enter your email',
                    'readonly' => $options['email_locked'],
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Email is required.',
                    ]),
                    new Email([
                        'message' => 'Please enter a valid email address.',
                    ]),
                ],
            ])

            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'I agree to the terms and conditions',
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You must agree to the terms.',
                    ]),
                ],
            ])

            ->add('plainPassword', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'Enter your password',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Password is required.',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters.',
                        'max' => 4096,
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'email_locked' => false,
        ]);
    }
}