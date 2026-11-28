<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class OrderConfirmationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function send(CustomerOrder $order): void
    {
        $customer = $order->getCustomer();

        if ($customer === null || $customer->getEmail() === null) {
            throw new \LogicException(
                'La commande doit appartenir à un client avec une adresse e-mail.',
            );
        }

        $email = (new TemplatedEmail())
            ->from('no-reply@vite-et-gourmand.fr')
            ->to($customer->getEmail())
            ->subject(sprintf(
                'Confirmation de votre commande %s',
                $order->getReference(),
            ))
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }
}
