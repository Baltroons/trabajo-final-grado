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
        $contenido = $request->request->get('mensaje');
        $salaId = $request->request->get('salaId');
        $file = $request->files->get('archivo_adjunto');
        $user = $this->getUser();

        if (!$user || !$salaId) return new JsonResponse(['error' => 'No autorizado'], 403);

        $sala = $salaRepo->find($salaId);
        if (!$sala) return new JsonResponse(['error' => 'Sala no encontrada'], 404);

        $mensaje = new Mensaje();
        $mensaje->setContenido($contenido);
        $mensaje->setAutor($user);
        $mensaje->setSala($sala);

        // Gestión del Archivo
        if ($file) {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $newFilename = $slugger->slug($originalFilename).'-'.uniqid().'.'.$file->guessExtension();

            try {
                $file->move($this->getParameter('kernel.project_dir').'/public/uploads', $newFilename);
                $mensaje->setArchivoUrl('/uploads/' . $newFilename);
                $mensaje->setArchivoNombre($file->getClientOriginalName());
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Error al subir archivo'], 500);
            }
        }

        $em->persist($mensaje);
        $em->flush();

        // Notificar a Mercure
        $hub->publish(new Update(
            "https://brainhub.com/sala/{$salaId}",
            json_encode($mensaje->toArray())
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
                // Aquí deberás crear un método en tu MensajeRepository que busque
                // los mensajes donde el emisor seas tú y el receptor él, y viceversa.


                $mensajes = $mensajeRepo->findConversacionPrivada($user, $receptor);
                foreach ($mensajes as $m) {
                    $mensajesData[] = $m->toArray();
                }
            }
        }

        return $this->json($mensajesData);
    }
}
