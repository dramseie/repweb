<?php

namespace App\Controller\Api;

use App\Entity\Gallery;
use App\Entity\GalleryPhoto;
use App\Repository\GalleryPhotoRepository;
use App\Repository\GalleryRepository;
use App\Service\GalleryManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/galleries')]
class GalleryApiController extends AbstractController
{
    public function __construct(
        private readonly GalleryRepository $galleries,
        private readonly GalleryPhotoRepository $photos,
        private readonly GalleryManager $manager,
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'api_galleries_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = array_map(
            fn (Gallery $gallery) => $this->serialiseGallery($gallery),
            $this->galleries->findBy([], ['name' => 'ASC'])
        );

        return $this->json([
            'data' => $items,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'api_galleries_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: 'null', true);
        $name = \is_array($payload) ? trim((string) ($payload['name'] ?? '')) : '';
        if ($name === '') {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $slug = $this->generateUniqueSlug($name);

        $gallery = new Gallery($name, $slug);
        $this->galleries->save($gallery);
        $this->em->flush();

        return $this->json([
            'data' => $this->serialiseGallery($gallery),
        ], Response::HTTP_CREATED);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/share', name: 'api_galleries_refresh_share', methods: ['POST'])]
    public function refreshShare(string $slug): JsonResponse
    {
        $gallery = $this->requireGallery($slug);
        $gallery->regenerateShareToken();
        $this->em->flush();

        return $this->json([
            'data' => $this->serialiseGallery($gallery),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/photos', name: 'api_gallery_photos_index', methods: ['GET'])]
    public function photos(string $slug): JsonResponse
    {
        $gallery = $this->requireGallery($slug);
        $items = array_map(
            fn (GalleryPhoto $photo) => $this->serialisePhoto($photo),
            $this->photos->findAllOrdered($gallery)
        );

        return $this->json([
            'gallery' => $this->serialiseGallery($gallery),
            'data' => $items,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/photos', name: 'api_gallery_photos_upload', methods: ['POST'])]
    public function upload(string $slug, Request $request): JsonResponse
    {
        $gallery = $this->requireGallery($slug);

        $files = $request->files->all('files');
        if (empty($files)) {
            $single = $request->files->get('file');
            if ($single !== null) {
                $files = [$single];
            }
        }

        if (empty($files)) {
            return $this->json(['error' => 'No files received'], Response::HTTP_BAD_REQUEST);
        }

        $results = [];
        foreach ($files as $uploaded) {
            if (\is_array($uploaded)) {
                foreach ($uploaded as $inner) {
                    if ($inner instanceof UploadedFile) {
                        $results[] = $this->handleUpload($gallery, $inner, $request);
                    }
                }
                continue;
            }

            if ($uploaded instanceof UploadedFile) {
                $results[] = $this->handleUpload($gallery, $uploaded, $request);
            }
        }

        return $this->json([
            'data' => $results,
        ], Response::HTTP_CREATED);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/photos/layout', name: 'api_gallery_photos_layout', methods: ['PATCH'])]
    public function updateLayout(string $slug, Request $request): JsonResponse
    {
        $gallery = $this->requireGallery($slug);

        $payload = json_decode($request->getContent() ?: 'null', true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $items = $payload['items'] ?? null;
        if (!\is_array($items)) {
            return $this->json(['error' => 'Missing layout items'], Response::HTTP_BAD_REQUEST);
        }

        $this->manager->updateLayout($gallery, $items);

        return $this->json(['status' => 'ok']);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/photos/{id}', name: 'api_gallery_photos_delete', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    public function delete(string $slug, int $id): JsonResponse
    {
        $gallery = $this->requireGallery($slug);
        $this->manager->deletePhoto($gallery, $id);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/photos/{id}', name: 'api_gallery_photos_update', requirements: ['id' => '\\d+'], methods: ['PATCH'])]
    public function update(string $slug, int $id, Request $request): JsonResponse
    {
        $gallery = $this->requireGallery($slug);

        $payload = json_decode($request->getContent() ?: 'null', true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        /** @var GalleryPhoto|null $photo */
        $photo = $this->photos->find($id);
        if (!$photo || $photo->getGallery()?->getId() !== $gallery->getId()) {
            return $this->json(['error' => 'Photo not found'], Response::HTTP_NOT_FOUND);
        }

        if (\array_key_exists('title', $payload)) {
            $photo->setTitle($payload['title'] !== null ? (string) $payload['title'] : null);
        }
        if (\array_key_exists('caption', $payload)) {
            $photo->setCaption($payload['caption'] !== null ? (string) $payload['caption'] : null);
        }

        if (\array_key_exists('creator', $payload)) {
            $photo->setCreator($payload['creator'] !== null ? (string) $payload['creator'] : null);
        }

        if (\array_key_exists('metadata', $payload) && \is_array($payload['metadata'])) {
            $photo->setMetadata($payload['metadata']);
        }

        $photo->touch();
        $this->photos->save($photo, true);

        return $this->json(['data' => $this->serialisePhoto($photo)]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{slug}/photos/{id}/watermark', name: 'api_gallery_photos_watermark', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function watermark(string $slug, int $id, Request $request): JsonResponse
    {
        $gallery = $this->requireGallery($slug);

        /** @var GalleryPhoto|null $photo */
        $photo = $this->photos->find($id);
        if (!$photo || $photo->getGallery()?->getId() !== $gallery->getId()) {
            return $this->json(['error' => 'Photo not found'], Response::HTTP_NOT_FOUND);
        }

        $logoUpload = $request->files->get('logo');
        $logoFile = null;
        if ($logoUpload instanceof UploadedFile) {
            $logoFile = $logoUpload;
        } elseif (\is_array($logoUpload) && !empty($logoUpload)) {
            $first = reset($logoUpload);
            if ($first instanceof UploadedFile) {
                $logoFile = $first;
            }
        }

        if (!$logoFile) {
            return $this->json(['error' => 'Watermark logo is required'], Response::HTTP_BAD_REQUEST);
        }

        $options = [
            'x' => $request->request->get('x'),
            'y' => $request->request->get('y'),
            'scale' => $request->request->get('scale'),
            'opacity' => $request->request->get('opacity'),
        ];

        $options = array_filter($options, static fn ($value) => $value !== null && $value !== '');
        $options = array_map(static fn ($value) => (float) $value, $options);

        $metadataPayload = [
            'creator' => $request->request->get('creator'),
            'metadata' => $request->request->all('metadata') ?: null,
        ];

        $updated = $this->manager->applyWatermark($gallery, $photo, $logoFile, $options, $metadataPayload);

        return $this->json(['data' => $this->serialisePhoto($updated)]);
    }

    #[Route('/share/{token}', name: 'api_gallery_share', methods: ['GET'])]
    public function share(string $token): JsonResponse
    {
        $gallery = $this->galleries->findOneByToken($token);
        if (!$gallery) {
            return $this->json(['error' => 'Gallery not found'], Response::HTTP_NOT_FOUND);
        }

        $items = array_map(
            fn (GalleryPhoto $photo) => $this->serialisePhoto($photo, $token),
            $this->photos->findAllOrdered($gallery)
        );

        return $this->json([
            'gallery' => $this->serialiseGallery($gallery),
            'data' => $items,
            'shareToken' => $token,
        ]);
    }

    private function handleUpload(Gallery $gallery, UploadedFile $file, Request $request): array
    {
        $title = $request->request->get('title');
        $caption = $request->request->get('caption');

        $photo = $this->manager->storeUploadedFile($gallery, $file, $title, $caption);

        return $this->serialisePhoto($photo);
    }

    private function serialisePhoto(GalleryPhoto $photo, ?string $shareToken = null): array
    {
        $params = ['id' => $photo->getId()];
        if ($shareToken !== null) {
            $params['token'] = $shareToken;
        }

        $url = $this->urlGenerator->generate('gallery_media', $params, UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            'id' => $photo->getId(),
            'title' => $photo->getTitle(),
            'caption' => $photo->getCaption(),
            'originalFilename' => $photo->getOriginalFilename(),
            'mimeType' => $photo->getMimeType(),
            'size' => $photo->getSize(),
            'width' => $photo->getWidth(),
            'height' => $photo->getHeight(),
            'displayOrder' => $photo->getDisplayOrder(),
            'layout' => $photo->getLayout(),
            'uploadedAt' => $photo->getUploadedAt()->format(DATE_ATOM),
            'updatedAt' => $photo->getUpdatedAt()->format(DATE_ATOM),
            'url' => $url,
            'previewUrl' => $url,
            'gallerySlug' => $photo->getGallery()?->getSlug(),
            'creator' => $photo->getCreator(),
            'metadata' => $photo->getMetadata(),
            'watermarkOptions' => $photo->getWatermarkOptions(),
        ];
    }

    private function serialiseGallery(Gallery $gallery): array
    {
        return [
            'id' => $gallery->getId(),
            'name' => $gallery->getName(),
            'slug' => $gallery->getSlug(),
            'shareToken' => $gallery->getShareToken(),
            'shareUrl' => $this->urlGenerator->generate(
                'gallery_share',
                ['token' => $gallery->getShareToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];
    }

    private function requireGallery(string $slug): Gallery
    {
        $gallery = $this->galleries->findOneBySlug($slug);
        if (!$gallery) {
            throw $this->createNotFoundException('Gallery not found');
        }

        return $gallery;
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = strtolower($this->slugger->slug($name)->toString());
        if ($base === '') {
            $base = 'gallery';
        }

        $slug = $base;
        $suffix = 2;
        while ($this->galleries->findOneBySlug($slug) !== null) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }
}
