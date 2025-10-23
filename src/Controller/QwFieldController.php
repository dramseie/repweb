<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\QwField;            // ← adjust to your real entity namespace
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/qw')]
final class QwFieldController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * PATCH /api/qw/fields/{id}
     * Accepts partial updates for a field. We only touch the whitelisted keys.
     */
    #[Route('/fields/{id}', name: 'qw_field_patch', methods: ['PATCH'])]
    public function patch(int $id, Request $req): JsonResponse
    {
        /** @var QwField|null $field */
        $field = $this->em->getRepository(QwField::class)->find($id);
        if (!$field) {
            return new JsonResponse(['error' => 'field not found'], 404);
        }

        $data = json_decode($req->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);

        // Whitelisted simple scalars (nullable)
        $simple = [
            'placeholder'    => 'setPlaceholder',
            'default_value'  => 'setDefaultValue',
            'min_value'      => 'setMinValue',
            'max_value'      => 'setMaxValue',
            'step_value'     => 'setStepValue',
            'accept_mime'    => 'setAcceptMime',
            'max_size_mb'    => 'setMaxSizeMb',
            'validation_regex' => 'setValidationRegex',
        ];

        foreach ($simple as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $field->$setter($data[$key]); // allow null
            }
        }

        // options_json (merge, do not clobber)
        if (array_key_exists('options_json', $data)) {
            $current = $field->getOptionsJson() ?? [];
            $incoming = is_array($data['options_json']) ? $data['options_json'] : [];
            $merged = array_replace_recursive($current, $incoming);
            $field->setOptionsJson($merged);
        }

        $this->em->flush();

        return new JsonResponse($this->serializeField($field), 200);
    }

    /** Small field normalizer for JSON responses */
    private function serializeField(QwField $f): array
    {
        return [
            'id'             => $f->getId(),
            'item_id'        => $f->getItem()?->getId(),
            'ui_type'        => $f->getUiType(),
            'placeholder'    => $f->getPlaceholder(),
            'default_value'  => $f->getDefaultValue(),
            'min_value'      => $f->getMinValue(),
            'max_value'      => $f->getMaxValue(),
            'step_value'     => $f->getStepValue(),
            'accept_mime'    => $f->getAcceptMime(),
            'max_size_mb'    => $f->getMaxSizeMb(),
            'validation_regex' => $f->getValidationRegex(),
            'options_json'   => $f->getOptionsJson(),
            'created_at'     => $f->getCreatedAt()?->format(DATE_ATOM),
            'updated_at'     => $f->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
