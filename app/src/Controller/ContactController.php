<?php

namespace App\Controller;

use App\Form\ContactFormType;
use App\Model\ContactRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MailerInterface $mailer,
        LoggerInterface $logger,
        #[Autowire('%env(CONTACT_EMAIL)%')]
        string $contactEmail,
    ): Response {
        $contactRequest = new ContactRequest();
        $form = $this->createForm(ContactFormType::class, $contactRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (new TemplatedEmail())
                ->from('no-reply@vite-et-gourmand.fr')
                ->to($contactEmail)
                ->replyTo($contactRequest->getEmail())
                ->subject(sprintf(
                    'Demande de contact de %s %s',
                    $contactRequest->getFirstName(),
                    $contactRequest->getLastName(),
                ))
                ->htmlTemplate('emails/contact_request.html.twig')
                ->context(['contact' => $contactRequest]);

            try {
                $mailer->send($email);

                $this->addFlash('contact_success', true);

                return $this->redirectToRoute('app_contact');
            } catch (\Throwable $exception) {
                $logger->error('The contact email could not be queued.', [
                    'visitor_email' => $contactRequest->getEmail(),
                    'exception' => $exception,
                ]);

                $form->addError(new FormError(
                    'Votre message n’a pas pu être envoyé. '
                    .'Veuillez réessayer dans quelques instants.',
                ));
            }
        }

        $response = $form->isSubmitted()
            ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY)
            : null;

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form,
        ], $response);
    }
}
