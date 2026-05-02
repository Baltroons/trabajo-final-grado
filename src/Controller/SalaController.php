<?php

namespace App\Controller;

use App\Entity\Sala;
use App\Form\MensajeType;
use App\Form\SalaType;
use App\Repository\SalaRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sala')]
final class SalaController extends AbstractController
{
    #[Route(name: 'app_sala_index', methods: ['GET'])]
    public function index(SalaRepository $salaRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'salas' => $salaRepository->findAll(),
        ]);
    }

    #[Route('/new-ajax', name: 'app_sala_new_ajax', methods: ['POST'])]
    public function newAjax(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sala = new Sala();
        $form = $this->createForm(SalaType::class, $sala);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Asignamos el creador (el usuario actual)
            $sala->setCreador($this->getUser());

            $entityManager->persist($sala);
            $entityManager->flush();

            // Si la petición es AJAX (vía fetch) devolvemos JSON
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept'), 'application/json')) {
                return new JsonResponse([
                    'success' => true,
                    'sala_id' => $sala->getId(),
                    'sala_nombre' => $sala->getNombre(),
                    'redirect_url' => $this->generateUrl('app_sala_show', ['id' => $sala->getId()])
                ]);
            }
        }

        // Si el formulario no es válido, devolvemos los errores en JSON
        return new JsonResponse([
            'success' => false,
            'message' => 'Error en la validación del formulario.'
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{id}', name: 'app_sala_show', methods: ['GET'])]
    public function show(Sala $sala): Response
    {
        if (!$this->getUser()) return $this->redirectToRoute('app_login');

        // Formulario vacío para el chat (el JS lo enviará al ChatController)
        $formMensaje = $this->createForm(MensajeType::class);

        // Formulario de edición para el modal
        $formEdit = $this->createForm(SalaType::class, $sala, [
            'action' => $this->generateUrl('app_sala_edit', ['id' => $sala->getId()]),
        ]);

        return $this->render('sala/show.html.twig', [
            'sala' => $sala,
            'formMensaje' => $formMensaje->createView(),
            'formEdit' => $formEdit->createView(),
            'mensajes' => $sala->getMensajes() // El Twig usará esto para el historial
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sala_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sala $sala, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SalaType::class, $sala);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Si la petición viene por AJAX (Fetch), respondemos con JSON
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'status' => 'success',
                    'message' => '¡Sala actualizada correctamente!',
                    'nuevoNombre' => $sala->getNombre()
                ]);
            }

            $this->addFlash('success', '¡Cambios guardados correctamente!');
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'error', 'message' => 'Revisa los campos del formulario.'], 400);
            }
        }

        return $this->render('sala/edit.html.twig', [
            'sala' => $sala,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_sala_delete', methods: ['POST'])]
    public function delete(Request $request, Sala $sala, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$sala->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($sala);
            $entityManager->flush();

            // 3. AÑADIDO: El mensaje flash de éxito tras eliminar
            $this->addFlash('success', 'La sala ha sido eliminada definitivamente.');
        }

        return $this->redirectToRoute('app_sala_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/unirse', name: 'app_sala_join', methods: ['POST'])]
    public function join(Sala $sala, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        // Si no está logueado, lo mandamos a iniciar sesión
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Comprobamos que no sea el creador y que no esté ya dentro
        if ($sala->getCreador() !== $user && !$sala->getMiembros()->contains($user)) {
            $sala->addMiembro($user);
            $entityManager->flush();

            $this->addFlash('success', '¡Te has unido a la sala con éxito!');
        }

        // Redirigimos al Home para que vea la sala en su lista
        return $this->redirectToRoute('app_home');
    }

    #[Route('/sala/{id}/invitar', name: 'app_sala_invitar', methods: ['POST'])]
    public function invitar(Request $request, Sala $sala, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        if ($this->getUser() !== $sala->getCreador()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'error', 'message' => 'No tienes permisos.'], 403);
            }
            throw $this->createAccessDeniedException();
        }

        $usuarioBuscado = $request->request->get('usuario_invitado');
        $invitado = $userRepository->findOneBy(['email' => $usuarioBuscado]);

        // Descomenta si también permites buscar por username
        // if (!$invitado) { $invitado = $userRepository->findOneBy(['username' => $usuarioBuscado]); }

        if ($invitado) {
            if (!$sala->getMiembros()->contains($invitado)) {
                $sala->addMiembro($invitado);
                $em->flush();

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['status' => 'success', 'message' => '¡' . $invitado->getUsername() . ' se unió a la sala!']);
                }
                $this->addFlash('success', '¡Añadido a la sala!');
            } else {
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['status' => 'warning', 'message' => 'Este usuario ya está en la sala.']);
                }
                $this->addFlash('warning', 'Este usuario ya está en la sala.');
            }
        } else {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'error', 'message' => 'Usuario no encontrado.']);
            }
            $this->addFlash('error', 'Usuario no encontrado.');
        }

        return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
    }

    #[Route('/{id}/expulsar/{user_id}', name: 'app_sala_expulsar', methods: ['POST'])]
    public function expulsar(
        Sala $sala,
        int $user_id,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {

        $currentUser = $this->getUser();

        // 1. Seguridad básica: Solo el creador de la sala puede expulsar a otros
        if ($currentUser !== $sala->getCreador()) {
            $this->addFlash('error', '¡Alto ahí! Solo el administrador de la sala puede expulsar estudiantes.');
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        // 2. Buscamos al estudiante en la base de datos mediante su ID
        $estudiante = $userRepository->find($user_id);

        if (!$estudiante) {
            $this->addFlash('error', 'El usuario que intentas expulsar no existe.');
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        // 3. Evitar que el creador se expulse a sí mismo por error
        if ($currentUser === $estudiante) {
            $this->addFlash('warning', 'No puedes expulsarte a ti mismo de tu propia sala.');
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        // 4. Verificamos que el estudiante realmente pertenezca a la sala y lo eliminamos
        if ($sala->getMiembros()->contains($estudiante)) {
            $sala->removeMiembro($estudiante);

            // Guardamos los cambios en la base de datos
            $entityManager->flush();

            $this->addFlash('success', 'El estudiante ' . $estudiante->getUsername() . ' ha sido expulsado de la sala.');
        } else {
            $this->addFlash('warning', 'Este estudiante ya no pertenece a la sala.');
        }

        // 5. Redirigimos de vuelta a la vista de la sala
        return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
    }
}
