<?php

namespace App\Tests\Controller;

use App\Entity\Demande;
use App\Entity\HistoriqueStatut;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemandeControllerTest extends WebTestCase
{
    public function testDepositPageRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');

        $client->loginUser($this->createUser());
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testUserCanSubmitAndSeeTheirOwnRequest(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(Demande::class);
        $countBefore = $repository->count([]);

        $crawler = $client->request('GET', '/');
        $form = $crawler->selectButton('Envoyer la demande')->form([
            'demande[texte]' => 'Je souhaite faire une demande de test.',
        ]);
        $client->submit($form);

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $entityManager->clear();
        self::assertSame($countBefore + 1, $repository->count([]));
        $demande = $repository->findOneBy(['user' => $user]);
        self::assertInstanceOf(Demande::class, $demande);
        self::assertSame($user->getUserIdentifier(), $demande->getEmail());
        self::assertSame('Je souhaite faire une demande de test.', $demande->getTexte());

        $client->request('GET', $location);
        self::assertResponseIsSuccessful();
    }

    public function testUserCannotSeeAnotherUsersRequest(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $otherUser = $this->createUser();
        $ownRequest = $this->createDemande($user);
        $otherRequest = $this->createDemande($otherUser);

        $client->request('GET', '/mes-demandes');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('a[href="/demande/%d"]', $ownRequest->getId()));
        self::assertSelectorNotExists(sprintf('a[href="/demande/%d"]', $otherRequest->getId()));

        $client->request('GET', sprintf('/demande/%d', $otherRequest->getId()));
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', sprintf('/demande/%d', $ownRequest->getId()));
        self::assertResponseIsSuccessful();
    }

    public function testOnlyAdminsCanListAndChangeRequests(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $demande = $this->createDemande($user);

        $client->request('GET', '/traiter');
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', sprintf('/demande/%d/statut', $demande->getId()), [
            'changer_statut' => [
                'statut' => 'Acceptée',
                'commentaire' => 'Test',
            ],
        ]);
        self::assertResponseStatusCodeSame(403);

        $admin = $this->createUser(true);
        $client->loginUser($admin);
        $client->request('GET', '/traiter');
        self::assertResponseIsSuccessful();

        $crawler = $client->request('GET', sprintf('/demande/%d/statut', $demande->getId()));
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Mettre à jour le statut')->form([
            'changer_statut[statut]' => 'Acceptée',
            'changer_statut[commentaire]' => 'Dossier complet',
        ]);
        $client->submit($form);

        self::assertResponseRedirects(sprintf('/demande/%d', $demande->getId()));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedRequest = $entityManager->getRepository(Demande::class)->find($demande->getId());
        self::assertSame('Acceptée', $savedRequest->getStatut());
        self::assertNotNull($entityManager->getRepository(HistoriqueStatut::class)->findOneBy([
            'demande' => $savedRequest,
            'statut' => 'Acceptée',
        ]));
    }

    public function testAdminCannotMakeAnInvalidStatusTransition(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $demande = $this->createDemande($user);
        $admin = $this->createUser(true);
        $client->loginUser($admin);

        $crawler = $client->request('GET', sprintf('/demande/%d/statut', $demande->getId()));
        $form = $crawler->selectButton('Mettre à jour le statut')->form([
            'changer_statut[statut]' => 'Traitée',
            'changer_statut[commentaire]' => 'Transition interdite',
        ]);
        $client->submit($form);

        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains(
            'body',
            'La transition de « Soumise » vers « Traitée » n’est pas autorisée.'
        );
        self::assertSame('Soumise', $demande->getStatut());
    }

    private function createUser(bool $admin = false): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())
            ->setEmail(sprintf('%s-%s@example.com', $admin ? 'admin' : 'user', bin2hex(random_bytes(6))))
            ->setPassword('test-password-hash');

        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createDemande(User $user): Demande
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $demande = (new Demande())
            ->setEmail($user->getUserIdentifier())
            ->setUser($user)
            ->setTexte('Demande de test');

        $entityManager->persist($demande);
        $entityManager->flush();

        return $demande;
    }
}
