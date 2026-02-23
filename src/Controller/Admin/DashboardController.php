<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\ImportTeletravailleursType;
use App\Form\ParametreType;
use App\Interface\GrhImportServiceInterface;
use App\Repository\ParametreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin/', name: 'admin_dashboard', methods: ['GET', 'POST'])]
    public function index(Request $request, ParametreRepository $parametreRepository, EntityManagerInterface $entityManager): Response
    {
        $values = [];
        foreach ($parametreRepository->findAll() as $parametre) {
            $values[$parametre->getCle()] = $parametre->getValeur();
        }

        $form = $this->createForm(ParametreType::class, ['boutique_ouverte_agents' => ($values['boutique_ouverte_agents'] ?? '0') === '1', 'boutique_ouverte_teletravailleurs' => ($values['boutique_ouverte_teletravailleurs'] ?? '0') === '1', 'boutique_ouverte_partenaires' => ($values['boutique_ouverte_partenaires'] ?? '0') === '1', 'max_produits_par_commande' => (int) ($values['max_produits_par_commande'] ?? 3)]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->getData() as $cle => $valeur) {
                $param = $parametreRepository->findOneBy(['cle' => $cle]) ?? (new \App\Entity\Parametre())->setCle((string) $cle);
                $param->setValeur(is_bool($valeur) ? ($valeur ? '1' : '0') : (string) $valeur);
                $entityManager->persist($param);
            }
            $entityManager->flush();
            $this->addFlash('success', 'Paramètres mis à jour.');

            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/dashboard/index.html.twig', ['form' => $form->createView(), 'importForm' => $this->createForm(ImportTeletravailleursType::class)->createView()]);
    }

    #[Route('/admin/import-teletravailleurs', name: 'admin_import_teletravailleurs', methods: ['POST'])]
    public function importTeletravailleurs(Request $request, GrhImportServiceInterface $grhImportService): Response
    {
        $form = $this->createForm(ImportTeletravailleursType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('csvFile')->getData();
            $count = $grhImportService->replaceAll($file->getPathname());
            $this->addFlash('success', sprintf('%d télétravailleurs importés.', $count));
        }

        return $this->redirectToRoute('admin_dashboard');
    }
}
