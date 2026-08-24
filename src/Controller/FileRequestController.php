<?php

namespace App\Controller;

use App\Entity\FileRequests;
use App\Entity\Files;
use App\Entity\Firms;
use App\Form\SendFilesFormType;
use App\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;

// ag: controller for the secure file request url logic
final class FileRequestController extends AbstractController
{
    use Traits\EntityActions;
    use ResetPasswordControllerTrait;

    #[Route('/{account}/file-request/{token}', name: 'firm_file_request')]
    public function index(Request $request, string $account, ?string $token = null): Response
    {
        // 1. Fetch firm
        $firm = $this->em->getRepository(Firms::class)->findOneBy(['account' => $account, 'active' => true]);
        if (!$firm) {
            throw $this->createNotFoundException('Firm not found.');
        }

        // 2. Validate token
        $hashed = hash('sha256', $token);
        $qb = $this->em->getRepository(FileRequests::class)->createQueryBuilder('fr');

        // dd($hashed);
        $fileRequest = $qb
            ->where('fr.hashedToken = :hashed')
            ->andWhere('fr.status = :status')
            ->andWhere('fr.tokenExpiresAt >= :now')
            ->setParameter('hashed', $hashed)
            ->setParameter('status', FileRequests::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable('now'))
            ->getQuery()
            ->getOneOrNullResult();

        // dd($fileRequest);
        if (!$fileRequest || !$fileRequest->isValidRequest()) {
            return $this->render('file_request/expired.html.twig', [
                'error' => 'This link is no longer valid.',
            ]);
        }

        // 3. Passcode required?
        if ($fileRequest->hasPasscode()) {
            return $this->redirectToRoute('firm_file_request_passcode', [
                'account' => $account,
                'token'   => $token,
            ]);
        }

        $storagePlan = $firm->getStoragePlan();
        $fileForm = $this->createForm(SendFilesFormType::class, null, [
            'storage_plan' => $storagePlan->getName(),
        ]);

        // 4. No passcode → show upload form
        return $this->render('file_request/index.html.twig', [
            'fileForm'      => $fileForm->createView(),
            'storagePlan'   => $storagePlan,
            'fileRequest'   => $fileRequest,
            'firm'          => $firm,
            'account'       => $account,
            'token'         => $token,
            // 'error'         => $error,
        ]);
    }

    #[Route('/{account}/file-request/{token}/submit', name: 'firm_file_request_submit')]
    public function fileRequestSubmit(Request $request, string $account, string $token, FileUploader $fileUploader): Response
    {
        $firm = $this->em->getRepository(Firms::class)->findOneBy(['account' => $account, 'active' => true]);
        if (!$firm) {
            // don’t reveal which accounts exist if you want to be more private
            throw new CustomUserMessageAuthenticationException('Invalid link.');
        }

        if (!$firm->getStoragePlan()?->getIsExternalFileRequests()) {
            throw $this->createNotFoundException('Invalid link.');
        }

        $storagePlan = $firm->getStoragePlan();
        $fileForm = $this->createForm(SendFilesFormType::class, null, [
            'storage_plan' => $storagePlan->getName(),
        ]);
        $hashed = hash('sha256', $token);
        $qb = $this->em->getRepository(FileRequests::class)->createQueryBuilder('fr');

        $fileRequest = $qb
            ->where('fr.hashedToken = :hashed')
            ->andWhere('fr.status = :status')
            ->andWhere('fr.tokenExpiresAt >= :now')
            ->setParameter('hashed', $hashed)
            ->setParameter('status', FileRequests::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable('now'))
            ->getQuery()
            ->getOneOrNullResult();

        $fileForm->handleRequest($request);
        // $userProfile = $fileRequest->getFirmUserProfile();
        $user = $fileRequest->getFirmUserProfile()->getUser();
        if ($fileForm->isSubmitted() && $fileForm->isValid()) {
            try {
                $files = $fileForm->get('files')->getData();
                $fileIds = array();
                foreach ($files as $key => $file) {
                    $newFile = $fileUploader->upload($file, $firm, $user, 'Requested File', null, null, $fileRequest);
                    if ($newFile instanceof Files) {
                        $fileIds[] = $newFile->getId();
                    }
                }

                $this->notificationService->notifyFileRequestCompleted($fileRequest, $firm, $fileIds);

                $fileRequest->setStatus(FileRequests::STATUS_COMPLETED);
                $this->em->flush();

                return $this->redirectToRoute('firm_file_request_completed', [
                    'account' => $account,
                    'token'   => $token,
                    'firm' => $firm,
                ]);
            } catch (\Throwable $e) {
                dd($e->getMessage());
                $this->audit->log(
                    action: 'fileRequest.submission',
                    resourceType: 'FileRequests',
                    resourceId: (string)$fileRequest->getId(),
                    success: false,
                    metadata: ['error' => $e->getMessage()],
                    severity: 'error',
                );
                $this->addFlash('error', "Something went wrong while uploading your requested files. Please try again or contact support if the problem continues.");
                return $this->redirectToRoute('firm_file_request', [
                    'account' => $account,
                    'token'   => $token,
                ]);
            }
        } else {
            $errors = $this->_getFormErrors($fileForm);
            $this->audit->log(
                action: 'fileRequest.submission',
                resourceType: 'FileRequests',
                resourceId: (string)$fileRequest->getId(),
                success: false,
                metadata: $errors,
                severity: 'error'
            );
            $this->addFlash('error', "Something went wrong with the submission of your file(s). Please try again or contact support if the problem continues.");
            return $this->redirectToRoute('firm_file_request', [
                'account' => $account,
                'token'   => $token,
            ]);
        }
    }

    #[Route('/{account}/file-request/{token}/completed', name: 'firm_file_request_completed')]
    public function completed(Request $request, ?string $account, ?string $token): response
    {
        $firm = $this->em->getRepository(Firms::class)->findOneBy(['account' => $account, 'active' => true]);

        return $this->render('file_request/request-completed-message.html.twig', [
            'firm' => $firm,
        ]);
    }

    #[Route('/{account}/file-request/{token}/passcode', name: 'firm_file_request_passcode')]
    public function passcode(Request $request, ?string $account, ?string $token): response
    {
        $firm = $this->em->getRepository(Firms::class)->findOneBy(['account' => $account, 'active' => 1]);

        if (!$firm) {
            // don’t reveal which accounts exist if you want to be more private
            throw new CustomUserMessageAuthenticationException('Invalid link.');
        }

        if (!$firm->getStoragePlan()?->getIsExternalFileRequests()) {
            throw $this->createNotFoundException('Invalid link.');
        }

        return $this->render('file_request/passcode.html.twig', [
            'account' => $account,
            'token' => $token,
            'firm' => $firm,
        ]);
    }

    #[Route('/{account}/file-request/{token}/passcode/submit', name: 'firm_file_request_passcode_submit', methods: ['POST'])]
    public function passcodeVerification(Request $request, string $account, string $token, PasswordHasherFactoryInterface $passwordHasherFactory): Response
    {
        $firm = $this->em->getRepository(Firms::class)->findOneBy(['account' => $account, 'active' => 1]);

        if (!$firm) {
            // don’t reveal which accounts exist if you want to be more private
            throw new CustomUserMessageAuthenticationException('Invalid link.');
        }

        if (!$firm->getStoragePlan()?->getIsExternalFileRequests()) {
            throw $this->createNotFoundException('Invalid link.');
        }

        $submittedPasscode = '';
        for ($i = 1; $i <= 5; $i++) {
            $submittedPasscode .= $request->request->get("digit_{$i}_input");
        }

        $hashedToken = hash('sha256', $token);
        $fileRequest = $this->em->getRepository(FileRequests::class)->findOneBy(['hashedToken' => $hashedToken]);


        $hasher = $passwordHasherFactory->getPasswordHasher(FileRequests::class);

        // dd($fileRequest->getHashedPasscode());
        $isValid = $hasher->verify($fileRequest->getHashedPasscode(), $submittedPasscode);

        if (!$fileRequest || !$fileRequest->isValidRequest()) {
            return $this->render('file_request/expired.html.twig', ['error' => 'Link is no longer valid.']);
        }

        if (!$isValid) {
            return $this->render('file_request/passcode.html.twig', [
                'error'   => 'Incorrect Passcode',
                'account' => $account,
                'token'   => $token,
                'firm'    => $firm,
            ]);
        }

        $storagePlan = $firm->getStoragePlan();
        $fileForm = $this->createForm(SendFilesFormType::class, null, [
            'storage_plan' => $storagePlan->getName(),
        ]);

        // Success → show upload form
        return $this->render('file_request/index.html.twig', [
            'fileForm' => $fileForm->createView(),
            'storagePlan' => $storagePlan,
            'fileRequest' => $fileRequest,
            'firm'        => $firm,
            'account'       => $account,
            'token'         => $token,
        ]);
    }
}
