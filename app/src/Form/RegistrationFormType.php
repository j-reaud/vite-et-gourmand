<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

            ->add('firstName', null, [
                'label' => 'Prénom',
            ])
            ->add('lastName', null, [
                'label' => 'Nom',
            ])

            ->add('phone', null, [
                'label' => 'Téléphone',
            ])

            ->add('address', null, [
                'label' => 'Adresse postale',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre adresse e-mail.'),
                    new Email(message: 'Veuillez saisir une adresse e-mail valide.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les mots de passe doivent être identiques.',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],

                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir un mot de passe.',
                    ),
                    new Length(
                        min: 10,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                    new Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d\s])\S+$/',
                        message: 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial.',
                    ),

                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
