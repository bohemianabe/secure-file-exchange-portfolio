<?php

namespace App\Controller\Admin;

use App\Controller\Traits\EntityActions;
use App\Entity\Countries;
use App\Entity\Firms;
use App\Entity\FirmUserProfiles;
use App\Entity\States;
use App\Entity\StoragePlans;
use App\Entity\Tickets;
use App\Entity\User;
use App\Form\FirmFormType;
use App\Form\FirmUserProfileFormType;
use App\Form\UserFormType;
use App\Import\FirmCsvImporter;
use App\Message\SendWelcomeEmail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted("ROLE_ADMIN")]
class DashboardController extends AbstractController
{
    use EntityActions;

    #[Route('/admin/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function adminDashboard(Request $request): Response
    {
        // ag: logic to show 2fa prompt modal to user
        $showTwoFactorPrompt = false;
        // Adjust this check to match your app's actual logic
        $hasTwoFactorEnabled = $this->getUser()->isTotpAuthenticationEnabled() || $this->getUser()->isEmailAuthEnabled();

        if (!$hasTwoFactorEnabled) {
            $session = $request->getSession();
            // $session->remove('admin_show_2fa_prompt');

            if (!$session->has('admin_show_2fa_prompt')) {
                // 40% chance
                $shouldShow = random_int(1, 100) <= 40;
                $session->set('admin_show_2fa_prompt', $shouldShow);
            }

            $showTwoFactorPrompt = (bool) $session->get('admin_show_2fa_prompt', false);
        }

        $isOpenStatusTicket = $this->em->getRepository(Tickets::class)->isOpenStatusTicket();
        $validTzs = array_flip(\DateTimeZone::listIdentifiers());
        $filters = $this->em->getFilters();
        if ($filters->has('soft_delete')) {
            $filters->disable('soft_delete');
        }

        $softDeletedFirms = $this->em->getRepository(Firms::class)->findSoftDeleted();
        if ($filters->has('soft_delete')) {
            $filters->enable('soft_delete');
        }
        return $this->render('admin_user_access/dashboard.html.twig', [
            'firms' => $this->em->getRepository(Firms::class)->findAll(),
            'softDeletedFirms' => $softDeletedFirms,
            'storage_plans' => $this->em->getRepository(StoragePlans::class)->findBy(['isActive' => true]),
            'states' => $this->em->getRepository(States::class)->findAll(),
            'countries' => $this->em->getRepository(Countries::class)->findAll(),
            'firm_user_types' => FirmUserProfiles::getUserTypes(),
            'chart_data' => ['activeFirmMetrics' => $this->_getActiveFirmMetrics(), 'storagePlanMetrics' => $this->_getStoragePlanMetrics()],
            'validTzs' => $validTzs,
            'isOpenStatusTicket' => $isOpenStatusTicket,
            'showTwoFactorPrompt' => $showTwoFactorPrompt,
            'modals_to_include' => ['import-firm.html.twig',],
        ]);
    }

    #[Route('/admin/ajax/restore-soft-delete-firm/{firmId}/modal', name: 'admin_ajax_restore_soft_delete_firm_modal')]
    #[Route('/admin/ajax/restore-soft-delete-firm/{firmId}/modal/submit', name: 'admin_ajax_restore_soft_delete_firm_modal_submit')]
    public function adminRestoreSoftDeleteFirm(Request $request, int $firmId)
    {
        // ag: $firmId int needs to be passed since firm is softdeleted symfony won't find it if pass through as an object
        if ($request->attributes->get('_route') == 'admin_ajax_restore_soft_delete_firm_modal') {
            return $this->render('/layouts/modals/admin/restore-soft-deleted-firm.html.twig', [
                'firmId' => $firmId,
            ]);
        } else {
            // ag: disable the soft_delete filter so I can find
            $filters = $this->em->getFilters();
            if ($filters->isEnabled('soft_delete')) {
                $filters->disable('soft_delete');
            }
            $firm = $this->em->getRepository(Firms::class)->find($firmId);

            $this->em->wrapInTransaction(function (EntityManagerInterface $em) use ($firm) {
                $firm->restore();

                foreach ($firm->getClients() as $client) {
                    $client->restore();
                    foreach ($client->getClientUserProfiles() as $cup) {
                        $cup->delete();
                        // product choice: also soft-delete the User?
                        $cup->getUser()?->restore();
                    }
                }

                foreach ($firm->getFirmUserProfiles() as $fup) {
                    $fup->restore();
                    $fup->getUser()?->restore();
                }

                // ag: TODO RESTORE FILES ONCE FILES ARE INCORPORATED
            });

            if (!$filters->isEnabled('soft_delete')) {
                $filters->enable('soft_delete');
            }

            // ag: log event
            $this->audit->log(
                action: 'firm.restored',
                resourceType: 'Firm',
                resourceId: (string)$firm->getId(),
                success: true,
                metadata: ['firm_name' => $firm->getName()],
            );

            return $this->json([
                'success' => true,
                'message' => "Firm and subsequent entities restored."
            ]);
        }
    }

    #[Route('/admin/download-firm-import-csv', name: 'admin_download_firm_import_template_csv')]
    #[Route('/admin/firm-import-csv/submit', name: 'admin_firm_import_submit', methods: ['POST'])]
    public function adminImportFirmDownload(Request $request, FirmCsvImporter $importer)
    {
        // ag: arrays for the csv template
        $csvHeader = ['firm_name', 'firm_account', 'firm_storage_plan', 'firm_timezone', 'firm_addr1', 'firm_addr2', 'firm_city', 'firm_state', 'firm_zip', 'firm_country', 'firm_phone', 'firm_active', 'user_email', 'profile_first_name', 'profile_last_name', 'profile_title', 'profile_phone', 'profile_contact_user'];

        // Example row of demo data
        $exampleRow = ['Test Firm', 'testfirm123', 'starter', 'America/New_York', '1800 Pennsylvania Ave', '', 'Alexandria', 'VA', '22202', 'United States', '333-999-6666', 'true', 'johndoe@example.com', 'John', 'Doe', 'CEO', '123-456-7890', 'true'];

        if ($request->attributes->get('_route') == 'admin_firm_import_submit') {
            // ag: handled in separate file along with the audit log logic.
            $report = $importer->import($request->files->get('file'), $csvHeader);

            if (empty($report->errors)) {
                return $this->json(['success' => true, 'message' => 'CSV successfully added.']);
            }

            $first = $report->errors[0];
            return $this->json([
                'success' => false,
                'message' => $first['message'] . ' Please review your file and resubmit.',
                'errors'  => $report->errors,
            ]);
        }

        // Convert arrays into CSV lines
        $headerLine = implode(',', $csvHeader);
        $dataLine   = implode(',', array_map(function ($value) {
            // Wrap in quotes if value contains a comma or space
            return (strpos($value, ',') !== false || strpos($value, ' ') !== false)
                ? '"' . $value . '"'
                : $value;
        }, $exampleRow));

        $csvContent = $headerLine . "\n" . $dataLine;

        return new Response(
            $csvContent,
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="firm-template.csv"',
            ]
        );
    }

    #[Route('/admin/ajax/soft-delete-firm/modal/submit', name: 'admin_ajax_soft_delete_firm_modal_submit')]
    public function adminSoftDeleteFirm(Request $request)
    {
        $firmId = $request->request->get('firmId');
        $firm = $this->em->getRepository(Firms::class)->find($firmId);

        $this->em->wrapInTransaction(function (EntityManagerInterface $em) use ($firm) {
            $firm->delete();

            foreach ($firm->getClients() as $client) {
                $client->delete();
                foreach ($client->getClientUserProfiles() as $cup) {
                    $cup->delete();
                    // product choice: also soft-delete the User?
                    $cup->getUser()?->delete();
                }
            }

            foreach ($firm->getFirmUserProfiles() as $fup) {
                $fup->delete();
                $fup->getUser()?->delete();
            }

            // ag: TODO ADD DELETE FOR FILES ONCE FILES ARE INCORPORATED
        });

        // ag: log event
        $this->audit->log(
            action: 'firm.softDeleted',
            resourceType: 'Firm',
            resourceId: (string)$firm->getId(),
            success: true,
            metadata: ['firm_name' => $firm->getName()],
        );

        return $this->json([
            'success' => true,
            'message' => "Firm and subsequent entities deleted."
        ]);
    }

    #[Route('/admin/add/new-firm/modal', name: 'admin_add_new_firm_modal', methods: ['GET'])]
    #[Route('/admin/add/new-firm-submit', name: 'admin_add_new_firm_submit', methods: ['POST'])]
    public function adminNewFirm(Request $request, SluggerInterface $slugger): Response
    {
        $newFirm = new Firms();
        $firmForm = $this->createForm(FirmFormType::class, $newFirm);

        $newUser = new User();
        $newUserForm = $this->createForm(UserFormType::class, $newUser);

        $newFirmUserProfile = new FirmUserProfiles();
        $firmUserProfileForm = $this->createForm(FirmUserProfileFormType::class, $newFirmUserProfile);
        if ($request->attributes->get('_route') == 'admin_add_new_firm_modal') {
            return $this->render('layouts/modals/admin/add-new-firm.html.twig', [
                'firmForm' => $firmForm->createView(),
                'userForm' => $newUserForm->createView(),
                'firmUserProfileForm' => $firmUserProfileForm->createView(),
            ]);
        }

        $firmForm->handleRequest($request);

        $newUserForm->handleRequest($request);

        $newFirmUserProfile->setUser($newUser);
        $newFirmUserProfile->setFirm($newFirm);

        $firmUserProfileForm->handleRequest($request);

        // ag: validate all forms
        if (($firmForm->isSubmitted() && $firmForm->isValid()) && ($newUserForm->isSubmitted() && $newUserForm->isValid()) && ($firmUserProfileForm->isSubmitted() && $firmUserProfileForm->isValid())) {
            try {
                $logoFile = $firmForm->get('logo')->getData();

                if ($logoFile) {
                    $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();

                    $logoFile->move(
                        $this->getParameter('firm_logo_dir'),
                        $newFilename
                    );

                    $newFirm->setLogo($newFilename);
                }


                $this->em->persist($newFirm);
                $this->em->flush();


                $newUser->setRoles(['ROLE_FIRM']);

                $this->em->persist($newUser);
                $this->em->flush();

                // ag: create firmUserProfile
                $newFirmUserProfile->setFirm($newFirm);
                $this->em->persist($newFirmUserProfile);
                $this->em->flush();

                // ag: log event
                $this->audit->log(
                    action: 'user.created, firm.created, firmUserProfile.created',
                    resourceType: 'User, Firm, FirmUserProfiles',
                    resourceId: (string)$newUser->getId(),
                    success: true,
                    metadata: ['new_firmuserprofile_created' => $newFirmUserProfile->getFullName(), 'new_firm_created' => $newFirmUserProfile->getFirm()->getName()],
                );


                // ag: send out welcome email
                $this->messageBus->dispatch(new SendWelcomeEmail(
                    userId: $newUser->getId(),
                    email: $newUser->getEmail(),
                ));

                $this->em->flush();

                $this->addFlash('success', 'New firm, user, and welcome email created.');
                return $this->redirect($request->headers->get('referer'));
            } catch (\Throwable $e) {
                $this->audit->log(
                    action: 'firm.created, user.created, FirmUserProfile.created',
                    resourceType: 'Firms, User, FirmUserProfiles',
                    // resourceId: (string)$user->getId(),
                    success: false,
                    metadata: ['error' => $e->getMessage()],
                    severity: 'error',
                );
                $this->addFlash('error', 'Unable to create new firm. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        } else {
            $errors = $this->_getFormErrors($firmForm, $newUserForm);

            $this->audit->log(
                action: 'firm.created, user.created, FirmUserProfile.created',
                resourceType: 'Firms, User, FirmUserProfiles',
                // resourceId: (string)$user->getId(),
                success: false,
                metadata: $errors,
                severity: 'error',
            );
            $this->addFlash('error', 'An unexpected error occurred while processing the form. Please contact an administrator.');
            return $this->redirect($request->headers->get('referer'));
        }
    }
}
