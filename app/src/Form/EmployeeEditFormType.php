<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class EmployeeEditFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir le prénom.'
                    ),
                    new Length(max: 100),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir le nom.'
                    ),
                    new Length(max: 100),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir une adresse e-mail.'
                    ),
                    new Email(
                        message: 'Veuillez saisir une adresse e-mail valide.'
                    ),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Numéro de téléphone',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir le numéro de téléphone.'
                    ),
                    new Length(max: 20),
                ],
            ])
            ->add('employeeNumber', TextType::class, [
                'label' => 'Numéro d’employé',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir le numéro d’employé.'
                    ),
                    new Length(max: 50),
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