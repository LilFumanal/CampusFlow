<?php

namespace App\Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    public function testRegistrationCreatesUserWithHashedPassword(): void
    {
        $client = static::createClient();
        $email = sprintf('new-%s@example.com', bin2hex(random_bytes(6)));

        $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $client->submitForm('Créer mon compte', [
            'registration_form[email]' => $email,
            'registration_form[plainPassword][first]' => 'strong-password',
            'registration_form[plainPassword][second]' => 'strong-password',
        ]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Votre compte a été créé.');

        $user = static::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy([
            'email' => $email,
        ]);
        self::assertInstanceOf(User::class, $user);
        self::assertNotSame('strong-password', $user->getPassword());
    }

    public function testRegistrationRejectsMismatchedPasswords(): void
    {
        $client = static::createClient();
        $email = sprintf('mismatch-%s@example.com', bin2hex(random_bytes(6)));

        $client->request('GET', '/register');
        $client->submitForm('Créer mon compte', [
            'registration_form[email]' => $email,
            'registration_form[plainPassword][first]' => 'strong-password',
            'registration_form[plainPassword][second]' => 'different-password',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Les deux mots de passe doivent être identiques.');
        self::assertNull(
            static::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy([
                'email' => $email,
            ])
        );
    }

    public function testRegistrationRejectsAnEmailAlreadyInUse(): void
    {
        $client = static::createClient();
        $email = sprintf('duplicate-%s@example.com', bin2hex(random_bytes(6)));
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $existingUser = (new User())
            ->setEmail($email)
            ->setPassword('already-hashed');
        $entityManager->persist($existingUser);
        $entityManager->flush();

        $client->request('GET', '/register');
        $client->submitForm('Créer mon compte', [
            'registration_form[email]' => $email,
            'registration_form[plainPassword][first]' => 'strong-password',
            'registration_form[plainPassword][second]' => 'strong-password',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Cette adresse e-mail est déjà utilisée.');
        self::assertCount(1, $entityManager->getRepository(User::class)->findBy(['email' => $email]));
    }
}
