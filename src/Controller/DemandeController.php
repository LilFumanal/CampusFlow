<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Form\DemandeType;
use App\Repository\DemandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class DemandeController extends AbstractController
{
#[Route('/demande', name: 'app_demande', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Crée l’objet qui recevra les valeurs du formulaire.
        // Son constructeur initialise déjà la date et le statut.
        $demande = new Demande();

        // Associe le formulaire DemandeType à cette nouvelle demande.
        $form = $this->createForm(DemandeType::class, $demande);

        // Lit les données envoyées par le navigateur et les applique à l’objet.
        $form->handleRequest($request);

        // N’enregistre la demande que si le formulaire a été envoyé et est valide.
        if ($form->isSubmitted() && $form->isValid()) {
            // Indique à Doctrine qu’il doit enregistrer cette nouvelle entité.
            $entityManager->persist($demande);

            // Exécute l’écriture en base de données.
            $entityManager->flush();

            // Redirige après l’enregistrement pour éviter une nouvelle soumission
            // si l’usager actualise la page.
            return $this->redirectToRoute('app_demande_show', ['id' => $demande->getId()]);
        }

        // Affiche le formulaire, ou le réaffiche avec ses erreurs de validation.
        return $this->render('demande/index.html.twig', [
            'form' => $form,
        ]);
    }
    #[Route('/demande/{id}', name: 'app_demande_show', methods: ['GET'])]
    public function show(Demande $demande): Response
    {
        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
        ]);
    }

    #[Route('/traiter', name: 'app_demande_traiter', methods: ['GET'])]
    public function traiter(DemandeRepository $demandeRepository): Response
    {
        return $this->render('demande/traiter.html.twig', [
            'demandes' => $demandeRepository->findAll(),
        ]);
    }
}
