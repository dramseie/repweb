<?php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class EavUpsertRequest
{
    #[Assert\NotBlank] public string $tenant;
    #[Assert\NotBlank] public string $type;
    #[Assert\NotBlank] public string $ci;
    #[Assert\NotBlank] public string $name;
    #[Assert\Choice(['active','inactive','planned','retired'], message: 'Invalid status.', multiple: false)]
    public string $status = 'active';

    /** @var array<string,mixed> */
    #[Assert\Type('array')]
    public array $attributes = [];

    public ?string $updated_by = null;

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self {
        $o = new self();
        $o->tenant     = (string)($data['tenant'] ?? '');
        $o->type       = (string)($data['type'] ?? '');
        $o->ci         = (string)($data['ci'] ?? '');
        $o->name       = (string)($data['name'] ?? '');
        $o->status     = (string)($data['status'] ?? 'active');
        $o->attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
        $o->updated_by = isset($data['updated_by']) ? (string)$data['updated_by'] : null;
        return $o;
    }
}
