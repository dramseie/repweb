<?php

namespace App\Service;

use App\Entity\Gallery;
use App\Entity\GalleryPhoto;
use App\Repository\GalleryPhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GalleryManager
{
    private const JPEG_DEFAULT_QUALITY = 92;

    public function __construct(
        private readonly GalleryPhotoRepository $photos,
        private readonly EntityManagerInterface $em,
        private readonly Filesystem $filesystem,
        private readonly string $storageDir,
    ) {
    }

    public function getStorageDir(): string
    {
        return rtrim($this->storageDir, '/');
    }

    public function getAbsolutePath(GalleryPhoto $photo): string
    {
        $base = $this->getStorageDir();
        $relative = trim($photo->getRelativePath(), '/');

        return $base . '/' . ($relative !== '' ? $relative . '/' : '') . $photo->getStoredFilename();
    }

    public function storeUploadedFile(Gallery $gallery, UploadedFile $file, ?string $title = null, ?string $caption = null): GalleryPhoto
    {
        if (!$file->isValid()) {
            throw new BadRequestHttpException('Upload failed.');
        }

        $mime = (string) $file->getMimeType();
        if (!str_starts_with($mime, 'image/')) {
            throw new BadRequestHttpException('Only image uploads are allowed.');
        }

        $relativePath = $this->buildRelativePath($gallery);
        $targetDir = $this->getStorageDir() . '/' . $relativePath;

        $this->filesystem->mkdir($targetDir);

        $extension = $file->guessExtension() ?: 'bin';
        $storedFilename = $this->generateFilename($extension);

        $moved = $file->move($targetDir, $storedFilename);

        $size = $moved->getSize();
        if ($size === false || $size === null) {
            $size = $file->getSize();
        }

        $photo = new GalleryPhoto();
        $photo->setGallery($gallery)
            ->setStoredFilename($storedFilename)
            ->setOriginalFilename($file->getClientOriginalName() ?? $storedFilename)
            ->setMimeType($mime)
            ->setSize((int) ($size ?? 0))
            ->setRelativePath($relativePath)
            ->setDisplayOrder($this->photos->getNextDisplayOrder($gallery))
            ->setTitle($title)
            ->setCaption($caption);

        $dimensions = @getimagesize($moved->getRealPath());
        if (is_array($dimensions) && isset($dimensions[0], $dimensions[1])) {
            $photo->setWidth((int) $dimensions[0])
                ->setHeight((int) $dimensions[1]);
        }

        $this->photos->save($photo);
        $this->em->flush();

        return $photo;
    }

    /**
     * @param array<int, array<string, mixed>> $layoutPayload
     */
    public function updateLayout(Gallery $gallery, array $layoutPayload): void
    {
        $repo = $this->photos;
        foreach ($layoutPayload as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $photo = $repo->find($id);
            if (!$photo || $photo->getGallery()?->getId() !== $gallery->getId()) {
                continue;
            }

            $order = isset($item['order']) ? (int) $item['order'] : $photo->getDisplayOrder();
            $layout = isset($item['layout']) && is_array($item['layout']) ? $this->normaliseLayout($item['layout']) : $photo->getLayout();

            $photo->setDisplayOrder($order)
                ->setLayout($layout)
                ->touch();
        }

        $this->em->flush();
    }

    public function deletePhoto(Gallery $gallery, int $id): void
    {
        $photo = $this->photos->find($id);
        if (!$photo || $photo->getGallery()?->getId() !== $gallery->getId()) {
            throw new NotFoundHttpException('Photo not found.');
        }

        $absolute = $this->getAbsolutePath($photo);
        if ($this->filesystem->exists($absolute)) {
            $this->filesystem->remove($absolute);
        }

        $this->photos->remove($photo, true);
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $metadataPayload
     */
    public function applyWatermark(Gallery $gallery, GalleryPhoto $photo, ?UploadedFile $logo, array $options, array $metadataPayload): GalleryPhoto
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            throw new BadRequestHttpException('Watermarking is unavailable because the GD extension is not enabled on the server.');
        }

        if ($photo->getGallery()?->getId() !== $gallery->getId()) {
            throw new BadRequestHttpException('Photo does not belong to gallery.');
        }

        $absolute = $this->getAbsolutePath($photo);
        if (!is_file($absolute)) {
            throw new NotFoundHttpException('Photo file missing.');
        }

        $imageData = file_get_contents($absolute);
        if ($imageData === false) {
            throw new BadRequestHttpException('Unable to read photo.');
        }

        $base = @imagecreatefromstring($imageData);
        if (!$base) {
            throw new BadRequestHttpException('Unsupported image format.');
        }

        try {
            imagealphablending($base, true);
            imagesavealpha($base, true);

            $baseWidth = imagesx($base);
            $baseHeight = imagesy($base);

            $watermarkResource = null;
            if ($logo instanceof UploadedFile) {
                if (!$logo->isValid()) {
                    throw new BadRequestHttpException('Invalid watermark upload.');
                }

                $logoData = file_get_contents($logo->getPathname());
                if ($logoData === false) {
                    throw new BadRequestHttpException('Unable to read watermark file.');
                }

                $watermarkResource = @imagecreatefromstring($logoData);
                if (!$watermarkResource) {
                    throw new BadRequestHttpException('Unsupported watermark format.');
                }
            }

            if (!$watermarkResource) {
                throw new BadRequestHttpException('Watermark logo is required.');
            }

            imagealphablending($watermarkResource, true);
            imagesavealpha($watermarkResource, true);

            $scale = isset($options['scale']) ? (float) $options['scale'] : 0.25;
            $scale = max(0.05, min($scale, 1.0));

            $targetWidth = max(1, (int) round($baseWidth * $scale));
            $wmWidth = imagesx($watermarkResource);
            $wmHeight = imagesy($watermarkResource);
            $targetHeight = max(1, (int) round($wmHeight * ($targetWidth / max(1, $wmWidth))));

            $resampled = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($resampled, false);
            imagesavealpha($resampled, true);
            $transparent = imagecolorallocatealpha($resampled, 0, 0, 0, 127);
            imagefilledrectangle($resampled, 0, 0, $targetWidth, $targetHeight, $transparent);

            imagecopyresampled($resampled, $watermarkResource, 0, 0, 0, 0, $targetWidth, $targetHeight, $wmWidth, $wmHeight);

            $opacity = isset($options['opacity']) ? (float) $options['opacity'] : 1.0;
            $opacity = max(0.0, min($opacity, 1.0));
            if ($opacity < 1.0) {
                $this->applyOpacity($resampled, $opacity);
            }

            $relativeX = isset($options['x']) ? (float) $options['x'] : 0.0;
            $relativeY = isset($options['y']) ? (float) $options['y'] : 0.0;

            $relativeX = max(0.0, min($relativeX, 1.0));
            $relativeY = max(0.0, min($relativeY, 1.0));

            $destX = (int) round(($baseWidth - $targetWidth) * $relativeX);
            $destY = (int) round(($baseHeight - $targetHeight) * $relativeY);

            imagecopy($base, $resampled, $destX, $destY, 0, 0, $targetWidth, $targetHeight);

            $tmpFile = uniqid(sys_get_temp_dir() . '/gal_', true) . '.jpg';
            imagealphablending($base, false);
            imagesavealpha($base, true);
            if (!imagejpeg($base, $tmpFile, self::JPEG_DEFAULT_QUALITY)) {
                throw new BadRequestHttpException('Unable to save processed image.');
            }

            $newFilename = $this->generateFilename('jpg');
            $targetDir = $this->getStorageDir() . '/' . trim($photo->getRelativePath(), '/');
            $this->filesystem->mkdir($targetDir);
            $newAbsolute = $targetDir . '/' . $newFilename;

            $moved = false;
            try {
                $this->filesystem->rename($tmpFile, $newAbsolute, true);
                $moved = true;
            } catch (\Throwable $exception) {
                $moved = false;
            }

            if (!$moved) {
                if (@rename($tmpFile, $newAbsolute)) {
                    $moved = true;
                }
            }

            if (!$moved) {
                if (@copy($tmpFile, $newAbsolute)) {
                    @unlink($tmpFile);
                    $moved = true;
                }
            }

            if (!$moved) {
                @unlink($tmpFile);
                throw new BadRequestHttpException('Unable to move processed image.');
            }

            // Ensure temporary file is removed if it still exists.
            if (is_file($tmpFile)) {
                @unlink($tmpFile);
            }

            if ($this->filesystem->exists($absolute)) {
                $this->filesystem->remove($absolute);
            }

            $size = filesize($newAbsolute) ?: 0;
            $photo->setStoredFilename($newFilename)
                ->setMimeType('image/jpeg')
                ->setSize((int) $size)
                ->setWidth($baseWidth)
                ->setHeight($baseHeight)
                ->touch();

            $originalBase = pathinfo($photo->getOriginalFilename(), PATHINFO_FILENAME) ?: 'image';
            $photo->setOriginalFilename($originalBase . '.jpg');

            $creator = isset($metadataPayload['creator']) ? $this->cleanMetadataValue((string) $metadataPayload['creator']) : null;
            $metadata = isset($metadataPayload['metadata']) && \is_array($metadataPayload['metadata']) ? $this->normaliseMetadataArray($metadataPayload['metadata']) : null;

            $photo->setCreator($creator !== '' ? $creator : null)
                ->setMetadata($metadata && $metadata !== [] ? $metadata : null)
                ->setWatermarkOptions([
                    'x' => $relativeX,
                    'y' => $relativeY,
                    'scale' => $scale,
                    'opacity' => $opacity,
                ]);

            $this->writeJpegMetadata($newAbsolute, $photo);

            $this->photos->save($photo);
            $this->em->flush();

            return $photo;
        } finally {
            if (isset($base) && \is_resource($base)) {
                imagedestroy($base);
            }
            if (isset($watermarkResource) && \is_resource($watermarkResource)) {
                imagedestroy($watermarkResource);
            }
            if (isset($resampled) && \is_resource($resampled)) {
                imagedestroy($resampled);
            }
        }
    }

    private function applyOpacity($image, float $opacity): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        for ($x = 0; $x < $width; ++$x) {
            for ($y = 0; $y < $height; ++$y) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                $color = [$rgba >> 16 & 0xFF, $rgba >> 8 & 0xFF, $rgba & 0xFF];
                $newAlpha = (int) round($alpha + (127 - $alpha) * (1 - $opacity));
                $newAlpha = max(0, min(127, $newAlpha));
                $newColor = imagecolorallocatealpha($image, $color[0], $color[1], $color[2], $newAlpha);
                imagesetpixel($image, $x, $y, $newColor);
            }
        }
    }

    private function writeJpegMetadata(string $filePath, GalleryPhoto $photo): void
    {
        if (!function_exists('iptcembed')) {
            return;
        }

        $tags = [];

        $title = $photo->getTitle();
        if ($title) {
            $tag = $this->buildIptcTag(5, $title); // ObjectName / Title
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }

        $creator = $photo->getCreator();
        if ($creator) {
            $tag = $this->buildIptcTag(80, $creator); // By-line
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }

        $metadata = $photo->getMetadata() ?? [];
        if (!empty($metadata['description'])) {
            $tag = $this->buildIptcTag(120, (string) $metadata['description']); // Caption/Description
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }
        if (!empty($metadata['copyright'])) {
            $tag = $this->buildIptcTag(116, (string) $metadata['copyright']); // Copyright Notice
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }

        if ($tags === []) {
            return;
        }

        $iptc = implode('', $tags);
        $jpegWithMetadata = @iptcembed($iptc, $filePath, 0);
        if ($jpegWithMetadata === false) {
            return;
        }

        @file_put_contents($filePath, $jpegWithMetadata);
    }

    private function buildIptcTag(int $dataset, string $value): ?string
    {
        $clean = $this->cleanMetadataValue($value);
        if ($clean === '') {
            return null;
        }

        $length = strlen($clean);
        $tag = chr(0x1C) . chr(2) . chr($dataset);

        if ($length <= 0x7FFF) {
            $tag .= chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } else {
            $tag .= chr(0x80) . chr(0x04) . pack('N', $length);
        }

        return $tag . $clean;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function normaliseMetadataArray(array $metadata): array
    {
        $allowed = [];
        if (isset($metadata['description'])) {
            $value = $this->cleanMetadataValue((string) $metadata['description']);
            if ($value !== '') {
                $allowed['description'] = $value;
            }
        }
        if (isset($metadata['copyright'])) {
            $value = $this->cleanMetadataValue((string) $metadata['copyright']);
            if ($value !== '') {
                $allowed['copyright'] = $value;
            }
        }

        return $allowed;
    }

    private function cleanMetadataValue(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', $value) ?? $value;

        return mb_substr($value, 0, 512);
    }

    /**
     * @return array<string, int>
     */
    private function normaliseLayout(array $layout): array
    {
        $keys = ['x', 'y', 'w', 'h'];
        $clean = [];
        foreach ($keys as $key) {
            if (isset($layout[$key])) {
                $clean[$key] = max(0, (int) $layout[$key]);
            }
        }

        return $clean;
    }

    private function buildRelativePath(Gallery $gallery): string
    {
        $slug = $gallery->getSlug();

        return sprintf('gallery/%s/%s/%s', $slug, date('Y'), date('m'));
    }

    private function generateFilename(string $extension): string
    {
        return bin2hex(random_bytes(12)) . '.' . $extension;
    }
}
