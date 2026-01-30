<?php

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ColetteOcrService
{
    private const MAX_IMAGE_BYTES = 5_000_000; // 5 MB safety limit

    public function extractText(string $base64Data, ?string $mimeType = null): string
    {
        if ($base64Data === '') {
            return '';
        }

        $binaryData = base64_decode($base64Data, true);
        if ($binaryData === false) {
            throw new \InvalidArgumentException('Payload image is not valid base64.');
        }

        if (strlen($binaryData) > self::MAX_IMAGE_BYTES) {
            throw new \RuntimeException('Image trop volumineuse pour OCR (5 MB max).');
        }

        $inputPath = tempnam(sys_get_temp_dir(), 'colette_img_') ?: null;
        $outputPath = tempnam(sys_get_temp_dir(), 'colette_out_') ?: null;

        if ($inputPath === null || $outputPath === null) {
            throw new \RuntimeException('Impossible de préparer un fichier temporaire pour l\'OCR.');
        }

        file_put_contents($inputPath, $binaryData);

        $process = new Process([
            'tesseract',
            $inputPath,
            $outputPath,
            '-l',
            'fra+eng',
        ]);
        $process->setTimeout(15);

        try {
            $process->mustRun();
        } catch (ProcessFailedException) {
            $this->cleanup([$inputPath, $outputPath, $outputPath . '.txt']);
            throw new \RuntimeException("L'outil tesseract semble indisponible ou a échoué.");
        }

        $textFile = $outputPath . '.txt';
        $text = is_file($textFile) ? trim((string) file_get_contents($textFile)) : '';

        $this->cleanup([$inputPath, $outputPath, $textFile]);

        return $text;
    }

    private function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
