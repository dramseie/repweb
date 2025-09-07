<?php
namespace App\Controller;

use App\Dto\EavUpsertRequest;
use App\Service\ExtEavService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EavController extends AbstractController
{
    #[Route('/api/eav/upsert', name: 'api_eav_upsert', methods: ['POST'])]
    public function upsert(Request $req, ValidatorInterface $validator, ExtEavService $svc): JsonResponse
    {
        $data = json_decode($req->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $dto  = EavUpsertRequest::fromArray($data);

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errs = [];
            foreach ($errors as $e) { $errs[] = ['field'=>$e->getPropertyPath(),'message'=>$e->getMessage()]; }
            return $this->json(['error'=>'validation_failed','details'=>$errs], 422);
        }

        try {
            $row = $svc->upsert(
                $dto->tenant, $dto->type, $dto->ci, $dto->name, $dto->status, $dto->attributes, $dto->updated_by
            );
            return $this->json(['ok'=>true,'result'=>$row], 200);
        } catch (\RuntimeException $e) {
            // Likely a SIGNAL 45000 from the procedure (unknown tenant/type, invalid JSON, etc.)
            return $this->json(['error'=>'upsert_failed','message'=>$e->getMessage()], 400);
        }
    }
}
