<?php

namespace App\Controller;

use App\Entity\ColetteEntry;
use App\Form\ColetteEntryType;
use App\Repository\ColetteEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ColetteController extends AbstractController
{
    #[Route('/colette', name: 'app_colette', methods: ['GET'])]
    public function index(ColetteEntryRepository $coletteEntryRepository): Response
    {
        $entries = $coletteEntryRepository->findPublished();
        $entriesByCategory = [];

        foreach ($entries as $entry) {
            $entriesByCategory[$entry->getCategory()][] = $entry;
        }

        $categoryLabels = [
            ColetteEntry::CATEGORY_RECIPE => 'Carnet de recettes',
            ColetteEntry::CATEGORY_KNOWLEDGE => 'Savoirs des anciens',
        ];

        return $this->render('public/colette.html.twig', [
            'entriesByCategory' => $entriesByCategory,
            'categoryLabels' => $categoryLabels,
        ]);
    }

    #[Route('/colette/new', name: 'app_colette_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $entry = new ColetteEntry();
        $form = $this->createForm(ColetteEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($entry);
            $entityManager->flush();

            $this->addFlash('success', 'Nouvelle page ajoutée à l\'escapade de Colette.');

            return $this->redirectToRoute('app_colette');
        }

        return $this->render('public/colette_form.html.twig', [
            'form' => $form->createView(),
            'mode' => 'create',
        ]);
    }

    #[Route('/colette/{slug}/edit', name: 'app_colette_edit', methods: ['GET', 'POST'])]
    public function edit(
        string $slug,
        Request $request,
        ColetteEntryRepository $coletteEntryRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $entry = $coletteEntryRepository->findOneBy(['slug' => $slug]);

        if (!$entry instanceof ColetteEntry) {
            throw $this->createNotFoundException('Entrée introuvable.');
        }

        $form = $this->createForm(ColetteEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Page mise à jour avec succès.');

            return $this->redirectToRoute('app_colette');
        }

        return $this->render('public/colette_form.html.twig', [
            'form' => $form->createView(),
            'mode' => 'edit',
            'entry' => $entry,
        ]);
    }
}
