<?php

namespace App\Controller;

use App\Entity\Mensaje;
use App\Repository\MensajeRepository;
use App\Repository\SalaRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ChatController extends AbstractController
{
    #[Route('/chat/enviar', name: 'app_chat_send', methods: ['POST'])]
    public function send(
        Request $request,
        HubInterface $hub,
        EntityManagerInterface $em,
        SalaRepository $salaRepo,
        SluggerInterface $slugger
    ): JsonResponse {
        try {
            $contenido = $request->request->get('mensaje');
            $salaId = $request->request->get('salaId');
            $file = $request->files->get('archivo');
            $user = $this->getUser();

            if (!$user || !$salaId) return new JsonResponse(['error' => 'No autorizado'], 403);

            $sala = $salaRepo->find($salaId);
            if (!$sala) return new JsonResponse(['error' => 'Sala no encontrada'], 404);

            $mensaje = new Mensaje();
            $mensaje->setContenido($contenido);
            $mensaje->setAutor($user);
            $mensaje->setSala($sala);

            // --- GESTIÓN DEL ARCHIVO ---
            if ($file) {
                $originalName = $file->getClientOriginalName();
                $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
                $newFilename = $slugger->slug(pathinfo($originalName, PATHINFO_FILENAME)).'-'.uniqid().'.'.$extension;

                $file->move($this->getParameter('kernel.project_dir').'/public/uploads', $newFilename);

                $mensaje->setArchivoUrl('/uploads/' . $newFilename);
                $mensaje->setArchivoNombre($originalName);

                $archivoEntidad = new \App\Entity\Archivo();
                $archivoEntidad->setNombreOriginal($originalName);
                $archivoEntidad->setNombreServidor($newFilename);
                $archivoEntidad->setTipo($extension);
                $archivoEntidad->setSala($sala);
                $archivoEntidad->setSubidoPor($user);

                $em->persist($archivoEntidad);
            }

            $em->persist($mensaje);
            $em->flush();

            // --- MERCURE Y RESPUESTA ---
            $mensajeData = json_encode($mensaje->toArray());

            // 1. Hacemos público el mensaje de la sala
            $hub->publish(new Update(
                "https://brainhub.com/sala/{$salaId}",
                $mensajeData,
                false // <-- ¡AÑADIDO! private: false
            ));

            $receptores = new \Doctrine\Common\Collections\ArrayCollection($sala->getMiembros()->toArray());
            if ($sala->getCreador() && !$receptores->contains($sala->getCreador())) {
                $receptores->add($sala->getCreador());
            }

            foreach ($receptores as $miembro) {
                if ($miembro->getId() !== $user->getId()) {
                    // 2. Hacemos pública la notificación personal
                    $hub->publish(new Update(
                        "https://brainhub.com/notifs/{$miembro->getId()}",
                        $mensajeData,
                        false // <-- ¡AÑADIDO! private: false
                    ));
                }
            }

            return new JsonResponse(['status' => 'OK']);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/chat/typing/{type}/{id}', name: 'app_chat_typing', methods: ['POST'])]
    public function typing(string $type, int $id, HubInterface $hub): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'No autorizado'], 403);
        }

        $topic = $type === 'sala' ? "https://brainhub.com/sala/{$id}" : "https://brainhub.com/user/{$id}";

        // 3. Hacemos público el evento "escribiendo..."
        $hub->publish(new Update(
            $topic,
            json_encode([
                'isTyping' => true,
                'autor'    => $user->getUsername()
            ]),
            false // <-- ¡AÑADIDO! private: false
        ));

        return new JsonResponse(['status' => 'OK']);
    }

    #[Route('/chat/typing/{type}/{id}', name: 'app_chat_typing', methods: ['POST'])]
    public function typing(string $type, int $id, HubInterface $hub): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'No autorizado'], 403);
        }

        // Definir a qué canal avisamos dependiendo de si es sala o privado
        $topic = $type === 'sala' ? "https://brainhub.com/sala/{$id}" : "https://brainhub.com/user/{$id}";

        // Avisar a Mercure de que alguien escribe
        $hub->publish(new Update(
            $topic,
            json_encode([
                'isTyping' => true,
                'autor'    => $user->getUsername() // <-- CORRECCIÓN: Ahora usa el nombre de usuario
            ])
        ));

        return new JsonResponse(['status' => 'OK']);
    }

    #[Route('/chat/history/{type}/{id}', name: 'app_chat_history', methods: ['GET'])]
    public function getHistory(
        string $type,
        int $id,
        MensajeRepository $mensajeRepo,
        SalaRepository $salaRepo,
        UserRepository $userRepo
    ): JsonResponse {

        $mensajesData = [];
        $user = $this->getUser();

        if ($type === 'sala') {
            $sala = $salaRepo->find($id);
            if ($sala) {
                // Obtenemos los últimos 50 mensajes de la sala
                $mensajes = $mensajeRepo->findBy(['sala' => $sala], ['fechaCreacion' => 'ASC'], 50);
                foreach ($mensajes as $m) {
                    $mensajesData[] = $m->toArray();
                }
            }
        } elseif ($type === 'user') {
            $receptor = $userRepo->find($id);
            if ($receptor && $user) {
                $mensajes = $mensajeRepo->findConversacionPrivada($user, $receptor);
                foreach ($mensajes as $m) {
                    $mensajesData[] = $m->toArray();
                }
            }
        }

        return $this->json($mensajesData);
    }
}
