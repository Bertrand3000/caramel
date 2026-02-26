<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/utilisateurs', name: 'admin_utilisateurs_')]
#[IsGranted('ROLE_ADMIN')]
class UtilisateurController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $utilisateurs = $this->entityManager
            ->getRepository(Utilisateur::class)
            ->findBy([], ['login' => 'ASC']);

        return $this->render('admin/utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurs,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $utilisateur = new Utilisateur();
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $utilisateur->setPassword($this->passwordHasher->hashPassword($utilisateur, $plain));
            $this->entityManager->persist($utilisateur);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Utilisateur « %s » créé.', $utilisateur->getLogin()));

            return $this->redirectToRoute('admin_utilisateurs_index');
        }

        return $this->render('admin/utilisateur/form.html.twig', [
            'form'        => $form->createView(),
            'isEdit'      => false,
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Utilisateur $utilisateur, Request $request): Response
    {
        $form = $this->createForm(UtilisateurType::class, $utilisateur, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            if ($plain !== null && $plain !== '') {
                $utilisateur->setPassword($this->passwordHasher->hashPassword($utilisateur, $plain));
            }
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Utilisateur « %s » mis à jour.', $utilisateur->getLogin()));

            return $this->redirectToRoute('admin_utilisateurs_index');
        }

        return $this->render('admin/utilisateur/form.html.twig', [
            'form'        => $form->createView(),
            'isEdit'      => true,
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Utilisateur $utilisateur, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete_utilisateur_' . $utilisateur->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_utilisateurs_edit', ['id' => $utilisateur->getId()]);
        }

        if ($utilisateur->getUserIdentifier() === $this->getUser()?->getUserIdentifier()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('admin_utilisateurs_index');
        }

        $login = $utilisateur->getLogin();
        $this->entityManager->remove($utilisateur);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Utilisateur « %s » supprimé.', $login));

        return $this->redirectToRoute('admin_utilisateurs_index');
    }
}
