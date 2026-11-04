<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\EmployeeAccountFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route(
        '/admin/employees/new',
        name: 'app_admin_employee_new'
    )]
    public function createEmployee(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
    ): Response {
        $employee = new User();
        $form = $this->createForm(
            EmployeeAccountFormType::class,
            $employee
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $employee
                ->setRoles(['ROLE_EMPLOYEE'])
                ->setIsActive(true)
                ->setPassword(
                    $passwordHasher->hashPassword(
                        $employee,
                        $plainPassword
                    )
                );

            $entityManager->persist($employee);
            $entityManager->flush();

            $email = (new TemplatedEmail())
                ->from('no-reply@vite-et-gourmand.fr')
                ->to((string) $employee->getEmail())
                ->subject('Votre compte employé Vite & Gourmand')
                ->htmlTemplate('emails/employee_account_created.html.twig')
                ->context([
                    'employee' => $employee,
                ]);

            $mailer->send($email);

            $this->addFlash(
                'success',
                'Le compte employé a été créé avec succès.'
            );

            return $this->redirectToRoute('app_admin_employee_new');
        }

        return $this->render('admin/employee_new.html.twig', [
            'employeeForm' => $form,
        ]);
    }
}