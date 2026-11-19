<?php

namespace App\Controller;

use App\Document\AccountRegistration;
use App\Entity\User;
use App\Form\EmployeeAccountFormType;
use App\Form\EmployeeEditFormType;
use App\Repository\CustomerOrderRepository;
use App\Repository\UserRepository;
use App\Service\AccountKpiService;
use App\Service\AccountRegistrationRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(
        AccountKpiService $accountKpiService,
        CustomerOrderRepository $customerOrderRepository,
    ): Response {
        return $this->render('admin/index.html.twig', [
            'accountKpis' => $accountKpiService->getDashboardData(),
            'orderKpis' => $customerOrderRepository->getAdminKpis(),
            'recentOrders' => $customerOrderRepository->findRecentForAdmin(),
            'today' => new \DateTimeImmutable(),
        ]);
    }

    #[Route(
        '/admin/employees',
        name: 'app_admin_employee_index',
        methods: ['GET', 'POST'],
    )]
    public function employees(
        Request $request,
        UserRepository $userRepository,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
        LoggerInterface $logger,
        AccountRegistrationRecorder $registrationRecorder,
    ): Response {
        $newEmployee = new User();
        $employeeForm = $this->createForm(
            EmployeeAccountFormType::class,
            $newEmployee,
            [
                'action' => $this->generateUrl(
                    'app_admin_employee_index',
                ),
                'method' => 'POST',
            ],
        );
        $employeeForm->handleRequest($request);

        if ($employeeForm->isSubmitted() && $employeeForm->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $employeeForm
                ->get('plainPassword')
                ->getData();

            $newEmployee
                ->setRoles(['ROLE_EMPLOYEE'])
                ->setIsActive(true)
                ->setPassword(
                    $passwordHasher->hashPassword(
                        $newEmployee,
                        $plainPassword,
                    ),
                );

            $entityManager->wrapInTransaction(
                function (
                    EntityManagerInterface $manager,
                ) use ($newEmployee): void {
                    $manager->persist($newEmployee);
                    $manager->flush();

                    $employeeId = $newEmployee->getId();

                    if ($employeeId === null) {
                        throw new \LogicException(
                            'The employee identifier could not be generated.',
                        );
                    }

                    $newEmployee->setEmployeeNumber(sprintf(
                        'VG-EMP-%06d',
                        $employeeId,
                    ));
                },
            );

            $registrationRecorder->record(
                $newEmployee,
                AccountRegistration::TYPE_EMPLOYEE,
            );

            $email = (new TemplatedEmail())
                ->from('no-reply@vite-et-gourmand.fr')
                ->to((string) $newEmployee->getEmail())
                ->subject('Votre compte employé Vite & Gourmand')
                ->htmlTemplate('emails/employee_account_created.html.twig')
                ->context([
                    'employee' => $newEmployee,
                ]);

            try {
                $mailer->send($email);
            } catch (TransportExceptionInterface $exception) {
                $logger->error(
                    'Employee account notification email could not be sent.',
                    [
                        'employee_email' => $newEmployee->getEmail(),
                        'exception' => $exception,
                    ],
                );

                $this->addFlash(
                    'warning',
                    'Le compte employé a été créé, mais l’e-mail '
                    .'de notification n’a pas pu être envoyé.',
                );
            }

            $this->addFlash(
                'success',
                'Le compte employé a été créé avec succès.',
            );

            return $this->redirectToRoute('app_admin_employee_index');
        }

        $employees = $userRepository->findEmployees();
        $employeeEditForms = [];

        foreach ($employees as $employee) {
            $employeeEditForms[$employee->getId()] = $formFactory->createNamed(
                'employee_edit_'.$employee->getId(),
                EmployeeEditFormType::class,
                $employee,
                [
                    'action' => $this->generateUrl(
                        'app_admin_employee_edit',
                        ['id' => $employee->getId()]
                    ),
                    'method' => 'POST',
                ]
            )->createView();
        }

        return $this->render('admin/employee_index.html.twig', [
            'employees' => $employees,
            'employeeEditForms' => $employeeEditForms,
            'employeeForm' => $employeeForm->createView(),
            'openEmployeeCreateModal' => $employeeForm->isSubmitted(),
        ], new Response(
            status: $employeeForm->isSubmitted()
                ? Response::HTTP_UNPROCESSABLE_ENTITY
                : Response::HTTP_OK,
        ));
    }

    #[Route(
        '/admin/employees/{id}/edit',
        name: 'app_admin_employee_edit',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function editEmployee(
        User $employee,
        Request $request,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
    ): Response {
        if (!in_array('ROLE_EMPLOYEE', $employee->getRoles(), true)) {
            throw $this->createNotFoundException(
                'Employee account not found.'
            );
        }

        $form = $formFactory->createNamed(
            'employee_edit_'.$employee->getId(),
            EmployeeEditFormType::class,
            $employee
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Les informations de l’employé ont été modifiées.'
            );
        } else {
            $hasErrors = false;

            foreach ($form->getErrors(true) as $error) {
                $hasErrors = true;
                $this->addFlash('warning', $error->getMessage());
            }

            if (!$hasErrors) {
                $this->addFlash(
                    'warning',
                    'Les modifications n’ont pas pu être enregistrées.'
                );
            }
        }

        return $this->redirectToRoute('app_admin_employee_index');
    }

    #[Route(
        '/admin/employees/{id}/deactivate',
        name: 'app_admin_employee_deactivate',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function deactivateEmployee(
        User $employee,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!in_array('ROLE_EMPLOYEE', $employee->getRoles(), true)) {
            throw $this->createNotFoundException(
                'Employee account not found.'
            );
        }

        if (!$this->isCsrfTokenValid(
            'deactivate_employee_'.$employee->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Invalid CSRF token.'
            );
        }

        if ($employee->isActive()) {
            $employee->setIsActive(false);
            $entityManager->flush();

            $this->addFlash(
                'success',
                sprintf(
                    'Le compte employé %s a été désactivé.',
                    $employee->getEmail()
                )
            );
        }

        return $this->redirectToRoute('app_admin_employee_index');
    }
}
