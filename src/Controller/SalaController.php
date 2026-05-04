<?php

namespace App\Controller;

use App\Entity\Sala;
use App\Entity\Mensaje; // <-- IMPORTANTE: Añadir la entidad Mensaje
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
use Symfony\Component\Mercure\HubInterface; // <-- IMPORTANTE: Añadir Hub de Mercure
use Symfony\Component\Mercure\Update;       // <-- IMPORTANTE: Añadir Update de Mercure

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
            $sala->setCreador($this->getUser());

            if (!$sala->getToken()) {
                $sala->setToken(bin2hex(random_bytes(15)));
            }

            $entityManager->persist($sala);
            $entityManager->flush();

            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept'), 'application/json')) {
                return new JsonResponse([
                    'success' => true,
                    'sala_id' => $sala->getId(),
                    'sala_nombre' => $sala->getNombre(),
                    'redirect_url' => $this->generateUrl('app_sala_show', ['id' => $sala->getId()])
                ]);
            }
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Error en la validación del formulario.'
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{id}/edit-ajax', name: 'app_sala_edit_ajax', methods: ['POST'])]
    public function editAjax(Request $request, Sala $sala, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($this->getUser() !== $sala->getCreador()) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'No tienes permisos para editar esta sala.'
            ], 403);
        }

        $nuevoNombre = $request->request->get('nombre');
        $nuevaDescripcion = $request->request->get('descripcion');

        if (!empty($nuevoNombre)) {
            $sala->setNombre($nuevoNombre);
        }

        if ($nuevaDescripcion !== null) {
            $sala->setDescripcion($nuevaDescripcion);
        }

        $entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'data' => [
                'nombre' => $sala->getNombre(),
                'descripcion' => $sala->getDescripcion()
            ]
        ]);
    }

    #[Route('/{id}', name: 'app_sala_show', methods: ['GET'])]
    public function show(Sala $sala, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) return $this->redirectToRoute('app_login');

        $formMensaje = $this->createForm(MensajeType::class);
        $formEdit = $this->createForm(SalaType::class, $sala, [
            'action' => $this->generateUrl('app_sala_edit', ['id' => $sala->getId()]),
        ]);

        if (!$sala->getToken()) {
            $sala->setToken(bin2hex(random_bytes(15)));
            $em->flush();
        }

        return $this->render('sala/show.html.twig', [
            'sala' => $sala,
            'formMensaje' => $formMensaje->createView(),
            'formEdit' => $formEdit->createView(),
            'mensajes' => $sala->getMensajes()
        ]);
    }

    // =========================================================================
    // --- NUEVAS RUTAS PARA EL CHAT EN TIEMPO REAL (MERCURE) ---
    // =========================================================================

    #[Route('/{id}/post-mensaje', name: 'app_sala_post_mensaje', methods: ['POST'])]
    public function postMensaje(Sala $sala, Request $request, EntityManagerInterface $em, HubInterface $hub): JsonResponse
    {
        $contenido = $request->request->get('contenido');

        if (!$contenido) {
            return new JsonResponse(['status' => 'error', 'message' => 'El mensaje no puede estar vacío'], 400);
        }

        // 1. Guardar en base de datos
        $mensaje = new Mensaje();
        $mensaje->setContenido($contenido);
        $mensaje->setAutor($this->getUser());
        $mensaje->setSala($sala);

        $em->persist($mensaje);
        $em->flush();

        // 2. Renderizar el "partial" de Twig a un string de HTML
        $html = $this->renderView('sala/_mensaje_item.html.twig', [
            'msg' => $mensaje
        ]);

        // 3. Enviar a Mercure
        $topic = "https://brainhub.com/sala/" . $sala->getId();
        $update = new Update(
            $topic,
            json_encode([
                'type' => 'message',
                'html' => $html
            ])
        );

        $hub->publish($update);

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/{id}/typing', name: 'app_sala_typing', methods: ['POST'])]
    public function typing(Sala $sala, HubInterface $hub): JsonResponse
    {
        // Solo necesitamos avisar al hub que alguien está escribiendo
        $topic = "https://brainhub.com/sala/" . $sala->getId();
        $update = new Update(
            $topic,
            json_encode([
                'type' => 'typing',
                'user' => $this->getUser()->getUserIdentifier() // Enviamos el identificador para no auto-mostrarnos el aviso
            ])
        );

        $hub->publish($update);

        return new JsonResponse(['status' => 'ok']);
    }

    // =========================================================================

    #[Route('/{id}/edit', name: 'app_sala_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sala $sala, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SalaType::class, $sala);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

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
            $this->addFlash('success', 'La sala ha sido eliminada definitivamente.');
        }

        return $this->redirectToRoute('app_sala_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/join/{token}', name: 'app_sala_join', methods: ['GET'])]
    public function join(string $token, SalaRepository $salaRepo, EntityManagerInterface $em): Response
    {
        $sala = $salaRepo->findOneBy(['token' => $token]);

        if (!$sala) {
            $this->addFlash('error', 'El enlace de invitación no es válido o ha expirado.');
            return $this->redirectToRoute('app_home');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($sala->getCreador() !== $user && !$sala->getMiembros()->contains($user)) {
            $sala->addMiembro($user);
            $em->flush();
            $this->addFlash('success', '¡Te has unido con éxito a ' . $sala->getNombre() . '!');
        }

        return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
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
    public function expulsar(Sala $sala, int $user_id, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $currentUser = $this->getUser();

        if ($currentUser !== $sala->getCreador()) {
            $this->addFlash('error', '¡Alto ahí! Solo el administrador de la sala puede expulsar estudiantes.');
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        $estudiante = $userRepository->find($user_id);

        if (!$estudiante) {
            $this->addFlash('error', 'El usuario que intentas expulsar no existe.');
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        if ($currentUser === $estudiante) {
            $this->addFlash('warning', 'No puedes expulsarte a ti mismo de tu propia sala.');
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        if ($sala->getMiembros()->contains($estudiante)) {
            $sala->removeMiembro($estudiante);
            $entityManager->flush();
            $this->addFlash('success', 'El estudiante ' . $estudiante->getUsername() . ' ha sido expulsado de la sala.');
        } else {
            $this->addFlash('warning', 'Este estudiante ya no pertenece a la sala.');
        }

        return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
    }
}
