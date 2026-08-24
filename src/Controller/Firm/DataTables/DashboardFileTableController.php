<?php

namespace App\Controller\Firm\DataTables;

use App\Controller\Traits\EntityActions;
use App\Entity\Files;
use App\Repository\FilesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\QueryBuilder;


final class DashboardFileTableController extends AbstractController
{
    use EntityActions;

    #[Route('/firm/ajax/file-table/data/call', name: 'firm_ajax_file_table_data_call')]
    public function __invoke(Request $request, FilesRepository $filesRepo): JsonResponse
    {
        $start  = max(0, (int) $request->query->get('start', 0));
        $length = min((int) $request->query->get('length', 10), 500);
        $draw   = (int) $request->query->get('draw', 0);
        $search = (string) ($request->query->all('search')['value'] ?? '');
        $order  = $request->query->all('order')[0] ?? null;
        $filter = $request->query->get('filter'); // received, sent, all

        $userProfile = $this->getUser()->getUserProfile();

        /** @var QueryBuilder $qb */
        $qb = $filesRepo->qbVisibleToUser($userProfile, $filter);

        // Search
        if ($search !== '') {
            $qb->andWhere('f.name LIKE :q')
                ->setParameter('q', '%' . $search . '%');
        }

        // ↕ Sorting
        $cols = [
            'id',
            'originalName',
            'uploadedBy',
            'assignedTo',
            'createdAt',
            'lastAccessedAt',
            'edit',
        ];

        if ($order) {
            $idx = (int) ($order['column'] ?? 0);
            $dir = strtoupper($order['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $col = $cols[$idx] ?? 'createdAt';
            $qb->orderBy('f.' . $col, $dir);
        } else {
            $qb->orderBy('f.createdAt', 'DESC');
        }

        // Counts
        $countQb = clone $qb;
        $recordsFiltered = (int) $countQb
            ->select('COUNT(f.idFile)')
            ->getQuery()
            ->getSingleScalarResult();

        // Page
        $rows = $qb->setFirstResult($start)->setMaxResults($length)->getQuery()->getResult();

        $firm = $userProfile->getFirm();
        $tzId = (string) $firm->getTimezone();
        $tz   = new \DateTimeZone($tzId);

        // Transform
        $data = array_map(function (Files $f) use ($tz) {
            $dtCreatedAt            = $f->getCreatedAt();
            $localCreatedAt         = $dtCreatedAt?->setTimezone($tz);
            $dtLastAccessedAt             = $f->getLastAccessedAt();

            if (!is_null($dtLastAccessedAt)) {
                $localLastAccessedAt = $dtLastAccessedAt->setTimezone($tz);
                $localLastAccessedAt = $localLastAccessedAt?->format('y-m-d g:ia');
            } else {
                $localLastAccessedAt = 'n/a';
            }

            $assignedTo = 'Unassigned';

            $uploadedBy = $f->getUploadedByFirmUser()?->getFullName()
                ?? $f->getUploadedByClientUser()?->getFullName()
                ?? 'System';

            if (!empty($f->hasSignatureRequests())) {
                $assignedTo = 'Docu Sign';
            } elseif ($f->isFileRequest()) {
                $assignedTo = 'File Request';
                $uploadedBy = $f->getFileRequest()->getRecipientName();
            } else {
                $assignedTo = $f->getClient()?->getName() ?? 'Public';
            }

            return [
                'id'                => $f->getId(),
                'originalName'      => $f->getName() ? $f->getName() : $f->getOriginalName(),
                'uploadedBy'        => $uploadedBy,
                'assignedTo'        => $assignedTo,
                'createdAt'         => $localCreatedAt?->format('y-m-d g:ia'),
                'lastAccessedAt'    => $localLastAccessedAt,
                'uuid'              => $f->getUuid(),
                'fileIcon'          => $f->getFileIcon(),
            ];
        }, $rows);

        return $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsFiltered,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    function trimStringWithDots(string $text, int $maxLength = 20, string $ellipsis = '...'): string
    {
        if (mb_strlen($text) > $maxLength) {
            // Subtract the length of the ellipsis from the maximum length to ensure the total length (text + ellipsis) does not exceed $maxLength.
            $trimmedLength = $maxLength - mb_strlen($ellipsis);
            return mb_substr($text, 0, $trimmedLength) . $ellipsis;
        }
        return $text;
    }
}
