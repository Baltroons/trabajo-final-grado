<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileController extends AbstractController
{
    #[Route('/perfil/editar', name: 'app_profile_edit')]
    public function editar(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        if ($request->isMethod('POST')) {
            // --- ACTUALIZAR DATOS DEL PERFIL ---
            $user->setUsername($request->request->get('username'));
            $user->setBiografia($request->request->get('biografia'));
            $user->setCiudad($request->request->get('ciudad'));

            // Manejo de la Foto de Perfil
            $fotoFile = $request->files->get('foto_perfil');
            if ($fotoFile) {
                $originalFilename = pathinfo($fotoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$fotoFile->guessExtension();

                try {
                    $fotoFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/profiles',
                        $newFilename
                    );
                    $user->setFotoPerfil($newFilename);
                } catch (\Exception $e) {
                    // Error silencioso o podrías usar flash message
                }
            }

            // --- CAMBIO DE CONTRASEÑA (Solo si se llena el campo actual) ---
            $passwordActual = $request->request->get('password_actual');
            $nuevaPassword = $request->request->get('nueva_password');
            $confirmarPassword = $request->request->get('confirmar_password');

            $passwordChanged = false;

            if (!empty($passwordActual)) {
                // Validar contraseña actual
                if (!$passwordHasher->isPasswordValid($user, $passwordActual)) {
                    $this->addFlash('error', '❌ La contraseña actual es incorrecta.');
                }
                elseif (empty($nuevaPassword)) {
                    $this->addFlash('error', '⚠️ La nueva contraseña no puede estar vacía.');
                }
                elseif ($nuevaPassword !== $confirmarPassword) {
                    $this->addFlash('error', '⚠️ Las nuevas contraseñas no coinciden.');
                }
                elseif (strlen($nuevaPassword) < 8) {
                    $this->addFlash('error', '⚠️ La contraseña debe tener al menos 8 caracteres.');
                }
                else {
                    // ✅ Todo correcto - Actualizar contraseña
                    $hashedPassword = $passwordHasher->hashPassword($user, $nuevaPassword);
                    $user->setPassword($hashedPassword);
                    $passwordChanged = true;
                }
            }

            $em->flush();

            // Mensaje de éxito apropiado
            if ($passwordChanged) {
                $this->addFlash('success', '🔒 ¡Contraseña actualizada correctamente!');
            } elseif (empty($passwordActual)) {
                $this->addFlash('success', '✅ ¡Perfil actualizado correctamente!');
            }

            return $this->redirectToRoute('app_profile_edit');
        }

        return $this->render('profile/edit.html.twig', ['user' => $user]);
    }
}
