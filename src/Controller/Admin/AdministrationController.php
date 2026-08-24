<?php

namespace App\Controller\Admin;

use App\Controller\Traits\EntityActions;
use App\Entity\AdminUserProfiles;
use App\Entity\Announcements;
use App\Entity\BlockedIps;
use App\Entity\Tickets;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;
use App\Form\AdminUserProfileFormType;
use App\Form\AnnouncementFormType;
use App\Form\BlockedIpFormType;
use App\Form\UserFormType;
use App\Message\SendWelcomeEmail;
use App\Repository\UserRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[IsGranted("ROLE_ADMIN")]
class AdministrationController extends AbstractController
{
    use EntityActions;

    #[Route('/admin/administrator-view', name: 'admin_administrator_view')]
    #[Route('/admin/add/administrator/modal', name: 'admin_add_new_admin_modal')]
    #[Route('/admin/add/administrator/submit', name: 'admin_add_new_admin_submit', methods: ['POST'])]
    public function adminAdministratorView(Request $request, UserRepository $userRepo): Response
    {
        $isOpenStatusTicket = $this->em->getRepository(Tickets::class)->isOpenStatusTicket();
        $newAdminUserProfile = new AdminUserProfiles();
        $newUser = new User();

        $userForm = $this->createForm(UserFormType::class, $newUser);
        $adminUserProfileForm = $this->createForm(AdminUserProfileFormType::class, $newAdminUserProfile);

        if ($request->attributes->get('_route') == 'admin_add_new_admin_modal') {
            return $this->render('/layouts/modals/admin/add-new-administrator.html.twig', [
                'userForm' => $userForm->createView(),
                'adminUserProfileForm' => $adminUserProfileForm->createView(),
            ]);
        } elseif ($request->attributes->get('_route') == 'admin_add_new_admin_submit') {
            $userForm->handleRequest($request);
            $adminUserProfileForm->handleRequest($request);

            if (($userForm->isSubmitted() && $userForm->isValid()) && ($adminUserProfileForm->isSubmitted() && $adminUserProfileForm->isValid())) {
                try {
                    $newUser->setRoles(['ROLE_ADMIN']);

                    $this->em->persist($newUser);

                    $newAdminUserProfile->setUser($newUser);
                    $this->em->persist($newAdminUserProfile);

                    // ag: log event
                    $this->audit->log(
                        action: 'user.created, adminUserProfile.created',
                        resourceType: 'User, AdminUserProfiles',
                        resourceId: (string)$newUser->getId(),
                        success: true,
                        metadata: ['admin_email' => $newAdminUserProfile->getUser()->getEmail()],
                    );

                    $this->em->flush();

                    // ag: send out welcome email
                    $this->messageBus->dispatch(new SendWelcomeEmail(
                        userId: $newUser->getId(),
                        email: $newUser->getEmail(),
                    ));
                    $this->addFlash('success', 'New Admin User Created.');
                    return $this->redirect($request->headers->get('referer'));
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Unable to create new admin user. Please contact an administrator.');
                    return $this->redirect($request->headers->get('referer'));
                }
            } else {
                $errors = $this->_getFormErrors($userForm, $adminUserProfileForm);

                $this->audit->log(
                    action: 'user.created, adminUserProfile.created',
                    resourceType: 'User, AdminUserProfiles',
                    resourceId: null,
                    success: false,
                    metadata: $errors,
                    severity: 'error',
                );

                $this->addFlash('error', 'There was an issue with the new admin user form submission. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        }

        $announcements = $this->em->getRepository(Announcements::class)->findAll() ?? null;
        return $this->render('admin_user_access/administrator-view.html.twig', [
            'users' => $userRepo->findByRoles(['ROLE_ADMIN']),
            'isOpenStatusTicket' => $isOpenStatusTicket,
            'announcements' => $announcements,
            // 'modals_to_include' => [''],
        ]);
    }

    #[Route('/admin/add/announcement/modal', name: 'admin_add_new_announcement_modal')]
    #[Route('/admin/add/announcement/submit', name: 'admin_add_new_announcement_submit', methods: ['POST'])]
    public function adminAnnouncementModal(Request $request): Response
    {
        $newAnnouncement = new Announcements();
        $announcementForm = $this->createForm(AnnouncementFormType::class, $newAnnouncement);

        if ($request->attributes->get('_route') == 'admin_add_new_announcement_submit') {
            $announcementForm->handleRequest($request);
            if ($announcementForm->isSubmitted() && $announcementForm->isValid()) {
                try {

                    $this->em->persist($newAnnouncement);

                    // ag: log event
                    $this->audit->log(
                        action: 'announcement.created',
                        resourceType: 'Announcement',
                        resourceId: (string)$newAnnouncement->getId(),
                        success: true,
                        metadata: ['announcement_title' => $newAnnouncement->getTitle()],
                    );
                    $this->em->flush();

                    $this->addFlash('success', 'New announcement created.');
                    return $this->redirect($request->headers->get('referer'));
                } catch (\Throwable $e) {
                    // ag: log event
                    $this->audit->log(
                        action: 'announcement.created',
                        resourceType: 'Announcement',
                        resourceId: (string)$newAnnouncement->getId(),
                        success: false,
                        metadata: ['error' => $e->getMessage()],
                        severity: 'error',
                    );

                    $this->addFlash('error', 'Unable to create new announcement. Please contact an administrator.');
                    return $this->redirect($request->headers->get('referer'));
                }
            } else {
                $errors = $this->_getFormErrors($announcementForm);

                $this->audit->log(
                    action: 'announcement.created',
                    resourceType: 'Announcement',
                    resourceId: null,
                    success: false,
                    metadata: $errors,
                    severity: 'error',
                );

                $this->addFlash('error', 'There was an issue with the new announcement form submission. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        }

        return $this->render('/layouts/modals/admin/add-new-announcement.html.twig', [
            'announcementForm' => $announcementForm->createView(),
        ]);
    }

    #[Route('/admin/administrator/edit/{userId}/modal', name: 'admin_edit_administrator_modal')]
    #[Route('/admin/administrator/edit/{userId}/modal/submit', name: 'admin_edit_administrator_modal_submit')]
    public function adminEditAdministratorModal(Request $request, $userId)
    {
        // ag: if submission comes from the profile page include the passwordfieldchecks
        if ($request->headers->get('referer') == $this->generateUrl('admin_profile_view', [], UrlGeneratorInterface::ABSOLUTE_URL)) {
            $passwordFieldsCheck = true;
        } else {
            $passwordFieldsCheck = false;
        }

        $user = $this->em->getRepository(User::class)->find($userId);
        $userForm = $this->createForm(UserFormType::class, $user, ['include_password_fields' => $passwordFieldsCheck]);

        $adminUserProfile = $user->getUserProfile();
        $adminProfileForm = $this->createForm(AdminUserProfileFormType::class, $adminUserProfile);
        if ($request->attributes->get('_route') == 'admin_edit_administrator_modal') {
            return $this->render('/layouts/modals/admin/edit-administrator.html.twig', [
                'adminUserProfile' => $adminUserProfile,
                'adminUserProfileForm' => $adminProfileForm,
                'userForm' => $userForm,
            ]);
        } else {
            $adminProfileForm->handleRequest($request);
            $userForm->handleRequest($request);

            if ($adminProfileForm->isSubmitted() && $adminProfileForm->isValid() && $userForm->isSubmitted() && $userForm->isValid()) {
                $uow = $this->em->getUnitOfWork();
                $uow->computeChangeSets();
                // ag: grab changes from both entities affected
                $userChanges = $uow->getEntityChangeSet($user);
                $adminUserProfileChanges = $uow->getEntityChangeSet($adminUserProfile);
                // ag: merge into one array
                $changes = array_merge($userChanges, $adminUserProfileChanges);

                $auditMetadata = [];
                foreach ($changes as $field => [$old, $new]) {
                    $oldNormalized = $this->_normalizeAuditValue($old);
                    $newNormalized = $this->_normalizeAuditValue($new);

                    if ($oldNormalized === $newNormalized) {
                        continue;
                    }

                    $auditMetadata[$field] = [
                        'from' => $oldNormalized,
                        'to' => $newNormalized,
                    ];
                }


                // ag: add a safety check to make sure that a changed email isn't being changed to an email that already exist in the user table
                if (isset($auditMetadata['email']['to']) && !empty($auditMetadata['email']['to'])) {
                    $userEmailSearchSubject = $auditMetadata['email']['to'];
                    $userEmailSearch = $this->em->getRepository(User::class)->findOneBy(['email' => $userEmailSearchSubject]);

                    if ($userEmailSearch instanceof User && !empty($userEmailSearch)) {
                        // ag: log event
                        $this->audit->log(
                            action: 'user.edited',
                            resourceType: 'User',
                            resourceId: (string)$user->getId(),
                            success: false,
                            metadata: ['message' => 'Cannot switch email to one already in existence within the system.', 'email' => $auditMetadata['email']['to']],
                            severity: 'warning',
                        );

                        $this->addFlash('error', 'Cannot switch email to one already in existence within the system. Must be unique.');
                        // ag: if the attempt to change email fails revert session back to original email. Changing email will log out user to log back in.
                        $user->setEmail($auditMetadata['email']['from']);
                        $this->em->refresh($user);

                        return $this->redirectToRoute('admin_administrator_view', ['id' => $user->getId()]);
                    }
                }

                if (!empty($auditMetadata)) {
                    // ag: log event
                    $this->audit->log(
                        action: 'user.edited, adminUserProfile.edited',
                        resourceType: 'User, AdminUserProfiles',
                        resourceId: (string)$user->getId(),
                        success: true,
                        metadata: $auditMetadata,
                    );
                }

                $this->em->flush();

                $this->addFlash('success', 'Admin Form updated.');
                return $this->redirect($request->headers->get('referer'));
            } else {
                $errors = $this->_getFormErrors($userForm);

                $this->audit->log(
                    action: 'user.edited, adminUserProfile.edited',
                    resourceType: 'User, AdminUserProfiles',
                    resourceId: (string)$user->getId(),
                    success: false,
                    metadata: $errors,
                    severity: 'error'
                );

                $this->addFlash('error', 'There was an issue with the admin form submission. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        }
    }

    #[Route('/admin/administrator/delete/{user}/modal/submit', name: 'admin_delete_admin_modal_submit')]
    public function adminDeleteAdministrator(Request $request, ?User $user)
    {
        // ag: add a check so admin can't delete themself when logged in
        if ($this->getUser() == $user) {
            $this->addFlash('error', 'Cannot delete administrator while logged in.');
            return $this->redirect($request->headers->get('referer'));
        } else {
            // ag: log event
            $this->audit->log(
                action: 'user.deleted, adminUserProfile.deleted',
                resourceType: 'User, AdminUserProfiles',
                resourceId: (string)$user->getId(),
                success: true,
                metadata: [],
            );

            $this->em->remove($user);
            $this->em->flush();

            $this->addFlash('success', 'Administrator deleted.');
            return $this->redirect($request->headers->get('referer'));
        }
    }

    #[Route('/admin/announcement/edit/{announcement}/modal', name: 'admin_edit_announcement_modal')]
    #[Route('/admin/announcement/edit/{announcement}/modal/submit', name: 'admin_edit_announcement_modal_submit')]
    #[Route('/admin/announcement/delete/{announcement}/modal/submit', name: 'admin_delete_announcement_modal_submit')]
    public function adminEditAnnouncementModal(Request $request, Announcements $announcement)
    {
        $announcementForm = $this->createForm(AnnouncementFormType::class, $announcement);
        if ($request->attributes->get('_route') == 'admin_edit_announcement_modal') {
            return $this->render('/layouts/modals/admin/edit-announcement.html.twig', [
                'announcementForm' => $announcementForm->createView(),
                'announcement' => $announcement,
            ]);
        } elseif ($request->attributes->get('_route') == 'admin_edit_announcement_modal_submit') {
            $announcementForm->handleRequest($request);
            if ($announcementForm->isSubmitted() && $announcementForm->isValid()) {
                try {
                    $uow = $this->em->getUnitOfWork();
                    $uow->computeChangeSets();
                    // ag: grab changes from both entities affected
                    $announcementChanges = $uow->getEntityChangeSet($announcement);

                    $auditMetadata = [];
                    foreach ($announcementChanges as $field => [$old, $new]) {
                        $auditMetadata[$field] = [
                            'from' => $this->_normalizeAuditValue($old),
                            'to'   => $this->_normalizeAuditValue($new),
                        ];
                    }

                    if (!empty($auditMetadata)) {
                        // ag: log event
                        $this->audit->log(
                            action: 'announcement.edited',
                            resourceType: 'Announcement',
                            resourceId: (string)$announcement->getId(),
                            success: true,
                            metadata: $auditMetadata,
                        );
                        $this->em->refresh($announcement);
                        $this->em->flush();

                        $this->addFlash('success', 'Announcement updated.');
                        return $this->redirect($request->headers->get('referer'));
                    }

                    return $this->redirect($request->headers->get('referer'));
                } catch (\Throwable $e) {
                    $this->audit->log(
                        action: 'announcement.edited',
                        resourceType: 'Announcement',
                        resourceId: (string)$announcement->getId(),
                        success: false,
                        metadata: ['error' => $e->getMessage()],
                        severity: 'error',
                    );
                    $this->addFlash('error', 'Unable to edit announcement. Please contact an administrator.');
                    return $this->redirect($request->headers->get('referer'));
                }
            } else {
                $errors = $this->_getFormErrors($announcementForm);

                $this->audit->log(
                    action: 'announcement.edited',
                    resourceType: 'Announcement',
                    resourceId: (string)$announcement->getId(),
                    success: false,
                    metadata: $errors,
                    severity: 'error'
                );

                $this->addFlash('error', 'There was an issue with the announcement form submission. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        } elseif ($request->attributes->get('_route') == 'admin_delete_announcement_modal_submit') {
            try {
                // ag: log event
                $this->audit->log(
                    action: 'announcement.deleted',
                    resourceType: 'Announcements',
                    resourceId: (string)$announcement->getId(),
                    success: true,
                    metadata: [],
                    severity: 'info',
                );

                $this->em->remove($announcement);
                $this->em->flush();

                $this->addFlash('success', 'Announcement deleted.');
                return $this->redirect($request->headers->get('referer'));
            } catch (\Throwable $e) {
                // ag: log event
                $this->audit->log(
                    action: 'announcement.deleted',
                    resourceType: 'Announcements',
                    resourceId: (string)$announcement->getId(),
                    success: false,
                    metadata: [],
                    severity: 'error',
                );

                $this->addFlash('error', 'Unable to delete announcement. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        }
    }

    #[Route('/admin/blocked-ip/modal/{blockedIp}', name: 'admin_blocked_ip_modal', methods: ['GET'])]
    #[Route('/admin/blocked-ip/modal/{blockedIp}/unblock', name: 'admin_unblock_blocked_ip_modal_submit', methods: ['POST'])]
    public function adminBlockedIpModal(Request $request, BlockedIps $blockedIp)
    {
        $timezone = $this->params->get('app_timezone');
        $blockedIpForm = $this->createForm(BlockedIpFormType::class, $blockedIp, ['user_timezone' => $timezone]);
        if ($request->attributes->get('_route') == 'admin_blocked_ip_modal') {
            return $this->render('/layouts/modals/admin/edit-blocked-ip.html.twig', [
                'blockedIpForm' => $blockedIpForm->createView(),
                'blockedIp' => $blockedIp,
            ]);
        } elseif ($request->attributes->get('_route') == 'admin_unblock_blocked_ip_modal_submit') {
            if ($blockedIp->getBlockedUntil() >= date('yyyy-MM-dd')) {
                try {
                    $unblockDate = new \DatetimeImmutable('yesterday');
                    $blockedIp->setBlockedUntil($unblockDate);

                    $this->em->persist($blockedIp);
                    $this->audit->log(
                        action: 'blockedIp.blockRemoved',
                        resourceType: 'BlockedIps',
                        resourceId: (string)$blockedIp->getId(),
                        success: true,
                        metadata: ['user' => $this->getUser()->getEmail()],
                        severity: 'info'
                    );
                    $this->em->flush();
                    $this->addFlash('success', 'Block removed.');
                    return $this->redirect($request->headers->get('referer'));
                } catch (\Throwable $e) {
                    $this->audit->log(
                        action: 'blockedIp.blockRemoved',
                        resourceType: 'BlockedIps',
                        resourceId: (string)$blockedIp->getId(),
                        success: false,
                        metadata: ['user' => $this->getUser()->getEmail(), 'error_msg' => $e->getMessage()],
                        severity: 'error'
                    );
                    $this->addFlash('Error', 'Unable to remove block. Please contact an administrator.');
                    return $this->redirect($request->headers->get('referer'));
                }
            }
        }
    }

    #[Route('/admin/delete/{user}/user', name: 'admin_delete_user')]
    public function adminDeleteUser(Request $request, ?User $user)
    {
        if ($user instanceof User) {
            try {
                // ag: log event
                $this->audit->log(
                    action: 'user.deleted',
                    resourceType: 'User',
                    resourceId: (string)$user->getId(),
                    success: true,
                    metadata: ['user_deleted' => $user->getEmail(), 'user_full_name' => $user->getUserProfile()->getFullName()],
                );

                $this->em->remove($user);

                $this->em->flush();
                $this->em->clear();

                $this->addFlash('success', 'User deleted.');
                return $this->redirect($request->headers->get('referer'));
            } catch (\Throwable $e) {
                $this->audit->log(
                    action: 'user.deleted',
                    resourceType: 'User',
                    resourceId: (string)$user->getId(),
                    success: false,
                    metadata: ['error' => $e->getMessage()],
                    severity: 'error'
                );
                $this->addFlash('error', 'Unable to delete user. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        } else {
            $this->addFlash('error', 'Unable to delete user. Contact an administrator.');
            return $this->redirect($request->headers->get('referer'));
        }
    }
}
