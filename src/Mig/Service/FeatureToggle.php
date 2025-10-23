<?php
namespace App\Mig\Service;

final class FeatureToggle
{
    public function __construct(
        private readonly bool $migEnabled = false,
        private readonly bool $eliseEnabled = false,
        private readonly bool $piEnabled = false,
    ) {}

    // Optional helper for tests/CLI; not used by DI in prod.
    public static function fromEnv(): self
    {
        $get = fn(string $k, bool $def=false) => filter_var($_ENV[$k] ?? $_SERVER[$k] ?? ($def ? '1' : '0'), FILTER_VALIDATE_BOOL);
        return new self(
            migEnabled:   $get('MIGRATION_MANAGER_ENABLED'),
            eliseEnabled: $get('BLAUE_ELISE_ENABLED'),
            piEnabled:    $get('PROGRESS_INDICATORS_ENABLED'),
        );
    }

    public function mig(): bool   { return $this->migEnabled; }
    public function elise(): bool { return $this->eliseEnabled; }
    public function pi(): bool    { return $this->piEnabled; }
}
