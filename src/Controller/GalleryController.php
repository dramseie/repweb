<?php

namespace App\Controller;

use App\Entity\Gallery;
use App\Repository\GalleryPhotoRepository;
use App\Repository\GalleryRepository;
use App\Service\GalleryManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gallery')]
class GalleryController extends AbstractController
{
    #[Route('', name: 'gallery_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request): Response
    {
        $initialSlug = $request->query->get('gallery');

        return $this->render('gallery/index.html.twig', [
            'initial_slug' => $initialSlug,
        ]);
    }

    #[Route('/share/{token}', name: 'gallery_share', methods: ['GET'])]
    public function share(string $token, GalleryRepository $galleries): Response
    {
        $gallery = $galleries->findOneByToken($token);
        if (!$gallery) {
            throw $this->createNotFoundException('Gallery not found');
        }

        return $this->render('gallery/share.html.twig', [
            'gallery' => $gallery,
            'share_token' => $token,
        ]);
    }

    #[Route('/media/{id}', name: 'gallery_media', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function media(
        int $id,
        Request $request,
        GalleryPhotoRepository $photos,
        GalleryManager $manager,
        Security $security
    ): Response
    {
        $photo = $photos->find($id);
        if (!$photo) {
            throw $this->createNotFoundException('Photo not found');
        }

        $gallery = $photo->getGallery();
        if (!$gallery instanceof Gallery) {
            throw $this->createNotFoundException('Gallery missing');
        }

        $token = $request->query->get('token');
        $isGranted = $security->isGranted('ROLE_USER');
        if (!$isGranted && !$gallery->matchesToken(is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Access denied.');
        }

        $absolute = $manager->getAbsolutePath($photo);
        if (!is_file($absolute)) {
            throw $this->createNotFoundException('File missing on storage');
        }

        $response = new BinaryFileResponse($absolute);
        $response->headers->set('Content-Type', $photo->getMimeType());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $photo->getOriginalFilename());

        return $response;
    }
}
