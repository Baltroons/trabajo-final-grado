<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProfileController extends AbstractController
{
    #[Route('/perfil/editar', name: 'app_profile_edit')]
    public function editar(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        if ($request->isMethod('POST')) {
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
                    $this->addFlash('error', 'Error al subir la imagen.');
                }
            }

            $em->flush();

            $this->addFlash('success', '¡Perfil actualizado correctamente!');
            return $this->redirectToRoute('app_profile_edit');
        }

        return $this->render('profile/edit.html.twig', ['user' => $user]);
    }
}
