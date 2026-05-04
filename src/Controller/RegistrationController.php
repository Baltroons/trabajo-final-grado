<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository; // <- NUEVO: Importamos el repositorio
use App\Security\UserAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse; // <- NUEVO: Para devolver JSON
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // do anything else you need here, like send an email

            return $security->login($user, UserAuthenticator::class, 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    // =========================================================================
    // NUEVO ENDPOINT: Verifica si el usuario o email ya existen en tiempo real
    // =========================================================================
    #[Route('/check-availability', name: 'app_check_availability', methods: ['GET'])]
    public function checkAvailability(Request $request, UserRepository $userRepository): JsonResponse
    {
        $username = $request->query->get('username');
        $email = $request->query->get('email');

        // Si están buscando por nombre de usuario
        if ($username) {
            $existingUser = $userRepository->findOneBy(['username' => $username]);
            return new JsonResponse(['available' => $existingUser === null]);
        }

        // Si están buscando por email
        if ($email) {
            $existingEmail = $userRepository->findOneBy(['email' => $email]);
            return new JsonResponse(['available' => $existingEmail === null]);
        }

        // Si no mandan nada, devolvemos error (no disponible)
        return new JsonResponse(['available' => false], 400);
    }
}
