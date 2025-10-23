<?php
namespace App\Twig;

use App\Mig\Service\FeatureToggle;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class FlagsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly FeatureToggle $ft) {}

    public function getGlobals(): array
    {
        return [
            'flags' => [
                'MIGRATION_MANAGER_ENABLED'   => $this->ft->mig(),
                'BLAUE_ELISE_ENABLED'         => $this->ft->elise(),
                'PROGRESS_INDICATORS_ENABLED' => $this->ft->pi(),
            ],
        ];
    }
}
