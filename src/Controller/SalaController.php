<?php

namespace App\Controller;

use App\Entity\Archivo;
use App\Entity\Mensaje;
use App\Entity\Sala;
use App\Form\ArchivoType;
use App\Form\MensajeType;
use App\Form\SalaType;
use App\Repository\SalaRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

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

    #[Route('/new', name: 'app_sala_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sala = new Sala();
        $form = $this->createForm(SalaType::class, $sala);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sala->setCreador($this->getUser());
            $entityManager->persist($sala);
            $entityManager->flush();

            // 1. Añadimos el mensaje de éxito
            $this->addFlash('success', '¡Sala creada con éxito! Ya puedes invitar a tus compañeros.');

            // 2. Redirigimos a la sala recién creada
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sala/new.html.twig', [
            'sala' => $sala,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sala_show', methods: ['GET', 'POST'])]
    public function show(
        Sala $sala,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger // <-- Añadido para nombres seguros
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // --- 1. GESTIÓN DE MENSAJES (FORO/CHAT) ---
        $mensaje = new Mensaje();
        $formMensaje = $this->createForm(MensajeType::class, $mensaje);
        $formMensaje->handleRequest($request);

        if ($formMensaje->isSubmitted() && $formMensaje->isValid()) {
            $mensaje->setAutor($user);
            $mensaje->setSala($sala);
            $mensaje->setFechaCreacion(new \DateTimeImmutable());

            // NUEVO: Capturamos el archivo que viene desde el input manual del chat
            /** @var UploadedFile $archivoAdjunto */
            $archivoAdjunto = $request->files->get('archivo_adjunto');

            if ($archivoAdjunto) {
                $originalFilename = pathinfo($archivoAdjunto->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename); // Limpiamos espacios y caracteres raros
                $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$archivoAdjunto->guessExtension();

                try {
                    $archivoAdjunto->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads',
                        $newFilename
                    );

                    // Creamos la entidad Archivo para que aparezca en la pestaña Multimedia
                    $archivoEntity = new Archivo();
                    $archivoEntity->setNombreOriginal($archivoAdjunto->getClientOriginalName());
                    $archivoEntity->setNombreServidor($newFilename);
                    $archivoEntity->setTipo($archivoAdjunto->guessExtension());
                    $archivoEntity->setSubidoPor($user);

                    $sala->addArchivo($archivoEntity);
                    $entityManager->persist($archivoEntity);

                    // Añadimos una nota visual al mensaje del chat
                    $textoActual = $mensaje->getContenido() ?? '';
                    $mensaje->setContenido($textoActual . "\n\n📎 *[Archivo adjunto: " . $archivoAdjunto->getClientOriginalName() . "]*");

                } catch (\Exception $e) {
                    $this->addFlash('error', 'No se pudo subir el archivo adjunto.');
                }
            }

            $entityManager->persist($mensaje);
            $entityManager->flush();

            $this->addFlash('success', 'Mensaje publicado.');
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        // --- 2. GESTIÓN DE ARCHIVOS (PESTAÑA MULTIMEDIA) ---
        $archivoEntity = new Archivo();
        $formArchivo = $this->createForm(ArchivoType::class, $archivoEntity);
        $formArchivo->handleRequest($request);

        if ($formArchivo->isSubmitted() && $formArchivo->isValid()) {
            /** @var UploadedFile $file */
            $file = $formArchivo->get('documento')->getData();

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename); // También aplicamos Slugger aquí
                $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads',
                        $newFilename
                    );

                    $archivoEntity->setNombreOriginal($file->getClientOriginalName());
                    $archivoEntity->setNombreServidor($newFilename);
                    $archivoEntity->setTipo($file->guessExtension());
                    $archivoEntity->setSubidoPor($user);

                    $sala->addArchivo($archivoEntity);

                    $entityManager->persist($archivoEntity);
                    $entityManager->flush();

                    $this->addFlash('success', '¡Archivo compartido con éxito!');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error técnico: No se pudo guardar el archivo.');
                }
            }

            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        // --- 3. RENDERIZADO FINAL ---
        return $this->render('sala/show.html.twig', [
            'sala' => $sala,
            'formMensaje' => $formMensaje->createView(),
            'formArchivo' => $formArchivo->createView(),
            'mensajes' => $sala->getMensajes()
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sala_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sala $sala, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SalaType::class, $sala);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // IMPORTANTE: Añadir el flash para que SweetAlert lo detecte
            $this->addFlash('success', '¡Cambios guardados correctamente!');

            return $this->redirectToRoute('app_sala_show', [
                'id' => $sala->getId()
            ], Response::HTTP_SEE_OTHER);
        }

        // Si el formulario no es válido, puedes añadir un flash de error para debug
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Hay errores en el formulario. Revisa los campos.');
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
        // Solo el creador puede invitar
        if ($this->getUser() !== $sala->getCreador()) {
            throw $this->createAccessDeniedException();
        }

        // Recoger el email/username del formulario
        $usuarioBuscado = $request->request->get('usuario_invitado');

        // Buscar al usuario en la base de datos (por email o username)
        $invitado = $userRepository->findOneBy(['email' => $usuarioBuscado]);
        // if (!$invitado) { $invitado = $userRepository->findOneBy(['username' => $usuarioBuscado]); }

        if ($invitado) {
            if (!$sala->getMiembros()->contains($invitado)) {
                $sala->addMiembro($invitado);
                $em->flush();
                $this->addFlash('success', '¡' . $invitado->getUsername() . ' ha sido añadido a la sala!');
            } else {
                $this->addFlash('error', 'Este usuario ya está en la sala.');
            }
        } else {
            $this->addFlash('error', 'No hemos encontrado a ningún usuario con ese dato.');
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
