<?php

namespace App\Form;

use App\Entity\OrderModification;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class EmployeeOrderEditFormType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('contactConfirmed', CheckboxType::class, [
                'label' => 'Je confirme avoir contacté le client',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(
                        message: 'Vous devez confirmer le contact client.',
                    ),
                ],
            ])
            ->add('contactMethod', ChoiceType::class, [
                'label' => 'Moyen de contact',
                'mapped' => false,
                'placeholder' => 'Choisir un moyen de contact',
                'choices' => [
                    'Appel téléphonique' => OrderModification::CONTACT_PHONE,
                    'E-mail' => OrderModification::CONTACT_EMAIL,
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez choisir un moyen de contact.',
                    ),
                ],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif de la modification',
                'mapped' => false,
                'attr' => [
                    'rows' => 5,
                    'maxlength' => 2000,
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir le motif de la modification.',
                    ),
                    new Length(
                        min: 10,
                        max: 2000,
                        minMessage: 'Le motif doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'edit_employee_order',
        ]);
    }

    public function getParent(): string
    {
        return CustomerOrderEditFormType::class;
    }
}
