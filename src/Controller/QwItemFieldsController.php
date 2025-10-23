<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\QwField;      // adjust
use App\Entity\QwItem;       // adjust
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/qw')]
final class QwItemFieldsController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * GET /api/qw/items/{itemId}/fields
     */
    #[Route('/items/{itemId}/fields', name: 'qw_item_fields_list', methods: ['GET'])]
    public function list(int $itemId): JsonResponse
    {
        /** @var QwItem|null $item */
        $item = $this->em->getRepository(QwItem::class)->find($itemId);
        if (!$item) {
            return new JsonResponse(['error' => 'item not found'], 404);
        }

        $fields = $this->em->getRepository(QwField::class)->createQueryBuilder('f')
            ->andWhere('f.item = :item')->setParameter('item', $item)
            ->orderBy('f.id', 'ASC')
            ->getQuery()->getResult();

        return new JsonResponse(array_map(fn(QwField $f) => [
            'id'             => $f->getId(),
            'ui_type'        => $f->getUiType(),
            'placeholder'    => $f->getPlaceholder(),
            'default_value'  => $f->getDefaultValue(),
            'min_value'      => $f->getMinValue(),
            'max_value'      => $f->getMaxValue(),
            'step_value'     => $f->getStepValue(),
            'options_json'   => $f->getOptionsJson(),
        ], $fields));
    }
}
