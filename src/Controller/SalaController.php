<?php

namespace App\Controller;

use App\Entity\Archivo;
use App\Entity\Mensaje;
use App\Entity\Sala;
use App\Form\ArchivoType;
use App\Form\MensajeType;
use App\Form\SalaType;
use App\Repository\SalaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sala')]
final class SalaController extends AbstractController
{
    #[Route(name: 'app_sala_index', methods: ['GET'])]
    public function index(SalaRepository $salaRepository): Response
    {
        return $this->render('sala/index.html.twig', [
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
            // ASIGNACIÓN AUTOMÁTICA DEL CREADOR
            $sala->setCreador($this->getUser());

            $entityManager->persist($sala);
            $entityManager->flush();

            $this->addFlash('success', '¡Sala creada con éxito!');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('sala/new.html.twig', [
            'sala' => $sala,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sala_show', methods: ['GET', 'POST'])]
    public function show(Sala $sala, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // --- 1. GESTIÓN DE MENSAJES ---
        $mensaje = new Mensaje();
        $formMensaje = $this->createForm(MensajeType::class, $mensaje);
        $formMensaje->handleRequest($request);

        // Verificamos si se envió el formulario de mensajes
        if ($formMensaje->isSubmitted() && $formMensaje->isValid()) {
            $mensaje->setAutor($user);
            $mensaje->setSala($sala);
            $mensaje->setFechaCreacion(new \DateTimeImmutable());

            $entityManager->persist($mensaje);
            $entityManager->flush();

            $this->addFlash('success', 'Mensaje publicado en el muro.');
            return $this->redirectToRoute('app_sala_show', ['id' => $sala->getId()]);
        }

        // --- 2. GESTIÓN DE ARCHIVOS ---
        $archivoEntity = new Archivo();
        $formArchivo = $this->createForm(ArchivoType::class, $archivoEntity);
        $formArchivo->handleRequest($request);

        // Verificamos si se envió el formulario de archivos
        if ($formArchivo->isSubmitted() && $formArchivo->isValid()) {
            /** @var UploadedFile $file */
            $file = $formArchivo->get('documento')->getData();

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = $originalFilename.'-'.uniqid('', true).'.'.$file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads',
                        $newFilename
                    );

                    $archivoEntity->setNombreOriginal($file->getClientOriginalName());
                    $archivoEntity->setNombreServidor($newFilename);
                    $archivoEntity->setTipo($file->guessExtension());
                    $archivoEntity->setSubidoPor($user);

                    // IMPORTANTE: Gracias a addArchivo() en la entidad Sala,
                    // esto vincula ambos lados de la relación
                    $sala->addArchivo($archivoEntity);
                    $archivoEntity->setSala($sala);

                    $entityManager->persist($archivoEntity);
                    $entityManager->flush();

                    $entityManager->refresh($sala);

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
            // Ordenamos mensajes: los más recientes arriba
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

            return $this->redirectToRoute('app_sala_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sala/edit.html.twig', [
            'sala' => $sala,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sala_delete', methods: ['POST'])]
    public function delete(Request $request, Sala $sala, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$sala->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($sala);
            $entityManager->flush();
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
}
