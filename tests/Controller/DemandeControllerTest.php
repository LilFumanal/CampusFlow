<?php

namespace App\Tests\Controller;

use App\Entity\Demande;
use App\Entity\HistoriqueStatut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemandeControllerTest extends WebTestCase
{
    // Fonctions Index

    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/demande');

        self::assertResponseIsSuccessful();
    }
    public function testIndexAfficheLeFormulaireSansCreerDeDemande(): void
    {
        $client = static::createClient();

        // Récupère le gestionnaire Doctrine depuis le conteneur de test.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(Demande::class);

        // Mémorise l'état initial pour vérifier que le GET ne crée rien.
        $nombreAvant = $repository->count([]);

        // Charge la page du formulaire.
        $crawler = $client->request('GET', '/demande');

        // Vérifie que la page est accessible et contient un formulaire.
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('form')->count());

        // Vérifie que l'affichage du formulaire n'a rien enregistré.
        self::assertSame($nombreAvant, $repository->count([]));
    }

    public function testSoumissionValideCreeUneDemandeEtRedirige(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(Demande::class);
        $nombreAvant = $repository->count([]);

        // Ouvre la page pour récupérer le vrai formulaire, avec son éventuel jeton CSRF.
        $crawler = $client->request('GET', '/demande');

        // Remplace "Envoyer" par le libellé ou le sélecteur réel du bouton.
        $form = $crawler->selectButton('Envoyer')->form([
            // Adapte ces noms aux champs définis dans DemandeType.
            'demande[email]' => 'test@example.com',
            'demande[texte]' => 'Je souhaite faire une demande de test.',
        ]);

        // Soumet le formulaire.
        $client->submit($form);

        // Vérifie la redirection vers la page de détail.
        self::assertResponseRedirects();

        // Suit la redirection et vérifie que la page cible est accessible.
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        // Vide l'EntityManager pour relire l'état réellement enregistré en base.
        $entityManager->clear();

        // Une demande doit avoir été ajoutée.
        self::assertSame($nombreAvant + 1, $repository->count([]));

        // Vérifie les valeurs persistées sur la dernière demande créée.
        $demandeEnregistree = $repository->findOneBy([], ['id' => 'DESC']);

        self::assertNotNull($demandeEnregistree);
        self::assertSame('test@example.com', $demandeEnregistree->getEmail());
        self::assertSame(
            'Je souhaite faire une demande de test.',
            $demandeEnregistree->getTexte()
        );
    }

    public function testSoumissionInvalideNeCreePasDeDemande(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(Demande::class);
        $nombreAvant = $repository->count([]);

        $crawler = $client->request('GET', '/demande');

        $form = $crawler->selectButton('Envoyer')->form([
            'demande[email]' => 'pas-un-email',
            'demande[texte]' => 'Texte de test',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($nombreAvant, $repository->count([]));
    }

    // --- Fonctions Changement de statut ---  

    public function testChangementDeStatutAutoriseCreeUnHistorique(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $demande = new Demande();
        $demande->setEmail('test@example.com');
        $demande->setTexte("Ceci est un test de demande.");
        $entityManager->persist($demande);
        $entityManager->flush();

        $id = $demande->getId();

        $crawler = $client->request('GET', sprintf('/demande/%d/statut', $id));
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Mettre à jour le statut')->form([
            'changer_statut[statut]' => 'Acceptée',
            'changer_statut[commentaire]' => 'Dossier complet',
        ]);

        $client->submit($form);

        self::assertResponseRedirects(sprintf('/demande/%d', $id));

        $entityManager->clear();

        $demandeEnregistree = $entityManager->getRepository(Demande::class)->find($id);

        self::assertSame('Acceptée', $demandeEnregistree->getStatut());

        $historique = $entityManager
            ->getRepository(HistoriqueStatut::class)
            ->findOneBy(['demande' => $demandeEnregistree]);

        self::assertNotNull($historique);
    }

    public function testTransitionInterditeNeModifiePasLaDemande(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $demande = new Demande();
        $demande->setEmail('test@example.com');
        $demande->setTexte("Ceci est un test de demande.");
        $entityManager->persist($demande);
        $entityManager->flush();

        $id = $demande->getId();

        $crawler = $client->request('GET', sprintf('/demande/%d/statut', $id));
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Mettre à jour le statut')->form([
            'changer_statut[statut]' => 'Traitée',
            'changer_statut[commentaire]' => 'Test de transition interdite',
        ]);
        $repository = $entityManager->getRepository(HistoriqueStatut::class);
        $historiquesAvant = $repository->findBy(['demande' => $demande]);

        $client->submit($form);

        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains(
            'body',
            'La transition de « Soumise » vers « Traitée » n’est pas autorisée.'
        );

        $entityManager->clear();

        $demandeEnregistree = $entityManager->getRepository(Demande::class)->find($id);
        $historiquesApres = $repository->findBy(['demande' => $demandeEnregistree]);

        self::assertCount(count($historiquesAvant), $historiquesApres);
        self::assertSame('Soumise', $demandeEnregistree->getStatut());

    }
}