<?php

namespace App\Form;

use App\Entity\CustomerOrder;
use App\Entity\User;
use App\Service\CustomerOrderPriceCalculator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

final class OrderPlacementFormType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        /** @var User $customer */
        $customer = $options['customer'];
        $minimumPeople = $options['minimum_people'];

        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'mapped' => false,
                'disabled' => true,
                'data' => $customer->getFirstName(),
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'mapped' => false,
                'disabled' => true,
                'data' => $customer->getLastName(),
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'mapped' => false,
                'disabled' => true,
                'data' => $customer->getPhone(),
            ])
            ->add('deliveryAddress', TextType::class, [
                'label' => 'Adresse de livraison',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'street-address',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir une adresse de livraison.',
                    ),
                ],
            ])
            ->add('deliveryAt', DateTimeType::class, [
                'label' => 'Date et heure de livraison',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez choisir une date de livraison.',
                    ),
                    new GreaterThan(
                        value: 'now',
                        message: 'La date de livraison doit être dans le futur.',
                    ),
                ],
            ])
            ->add('numberOfPeople', IntegerType::class, [
                'label' => 'Nombre de personnes',
                'attr' => [
                    'min' => $minimumPeople,
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir le nombre de personnes.',
                    ),
                    new Range(
                        min: $minimumPeople,
                        minMessage: sprintf(
                            'Ce menu nécessite au minimum %d personnes.',
                            $minimumPeople,
                        ),
                    ),
                ],
            ])
            ->add('discountAmount', MoneyType::class, [
                'label' => 'Réduction',
                'currency' => 'EUR',
                'disabled' => true,
                'help' => sprintf(
                    '%d %% appliqués automatiquement à partir de %d personnes.',
                    CustomerOrderPriceCalculator::DISCOUNT_RATE_PERCENT,
                    $minimumPeople
                        + CustomerOrderPriceCalculator::DISCOUNT_EXTRA_PEOPLE,
                ),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerOrder::class,
            'csrf_token_id' => 'place_order',
        ]);

        $resolver->setRequired([
            'customer',
            'minimum_people',
        ]);

        $resolver->setAllowedTypes('customer', User::class);
        $resolver->setAllowedTypes('minimum_people', 'int');
    }
}
