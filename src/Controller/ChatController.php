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
        UserRepository $userRepo, // Añade este repositorio
        SluggerInterface $slugger
    ): JsonResponse {
        try {
            $contenido = $request->request->get('mensaje');
            $salaId = $request->request->get('salaId');
            $receptorId = $request->request->get('receptorId'); // NUEVO
            $file = $request->files->get('archivo');
            $user = $this->getUser();

            if (!$user || (!$salaId && !$receptorId)) return new JsonResponse(['error' => 'No autorizado'], 403);

            $mensaje = new Mensaje();
            $mensaje->setContenido($contenido);
            $mensaje->setAutor($user);

            $sala = null;
            $receptor = null;

            if ($salaId) {
                $sala = $salaRepo->find($salaId);
                if (!$sala) return new JsonResponse(['error' => 'Sala no encontrada'], 404);
                $mensaje->setSala($sala);
            } elseif ($receptorId) {
                $receptor = $userRepo->find($receptorId);
                if (!$receptor) return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
                $mensaje->setReceptor($receptor);
            }

            // --- GESTIÓN DEL ARCHIVO (igual que antes) ---
            $archivoTamano = null;
            if ($file) {
                $originalName = $file->getClientOriginalName();
                $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
                $newFilename = $slugger->slug(pathinfo($originalName, PATHINFO_FILENAME)).'-'.uniqid().'.'.$extension;
                $archivoTamano = $file->getSize();
                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $file->move($uploadDir, $newFilename);
                $mensaje->setArchivoUrl('/uploads/' . $newFilename);
                $mensaje->setArchivoNombre($originalName);

                if ($sala) { // Solo guardamos el archivo en la galería si es de una sala
                    $archivoEntidad = new \App\Entity\Archivo();
                    $archivoEntidad->setNombreOriginal($originalName);
                    $archivoEntidad->setNombreServidor($newFilename);
                    $archivoEntidad->setTipo($extension);
                    $archivoEntidad->setSala($sala);
                    $archivoEntidad->setSubidoPor($user);
                    if(method_exists($archivoEntidad, 'setTamano')) $archivoEntidad->setTamano($archivoTamano);
                    $em->persist($archivoEntidad);
                }
            }

            $em->persist($mensaje);
            $em->flush();

            // --- MERCURE Y RESPUESTA ---
            $timezone = new \DateTimeZone('Europe/Madrid');
            $fechaCreacion = $mensaje->getFechaCreacion() ? $mensaje->getFechaCreacion()->format('d/m/Y H:i') : (new \DateTime('now', $timezone))->format('d/m/Y H:i');

            $mensajeData = json_encode([
                'id'             => $mensaje->getId(),
                'autor'          => $user->getUsername(),
                'autorId'        => $user->getId(),
                'autorFoto'      => $user->getFotoPerfil(),
                'contenido'      => $contenido,
                'archivoUrl'     => $mensaje->getArchivoUrl(),
                'archivoNombre'  => $mensaje->getArchivoNombre(),
                'archivoTamano'  => $archivoTamano,
                'fechaCreacion'  => $fechaCreacion,
                'salaId'         => $salaId,
                'salaNombre'     => $sala ? $sala->getNombre() : null,
                'remitenteId'    => $receptorId ? $user->getId() : null // Para saber quién lo envía en el privado
            ]);

            if ($sala) {
                $hub->publish(new Update("https://brainhub.com/sala/{$salaId}", $mensajeData, false));
                $receptores = new \Doctrine\Common\Collections\ArrayCollection($sala->getMiembros()->toArray());
                if ($sala->getCreador() && !$receptores->contains($sala->getCreador())) $receptores->add($sala->getCreador());
                foreach ($receptores as $miembro) {
                    if ($miembro->getId() !== $user->getId()) {
                        $hub->publish(new Update("https://brainhub.com/notifs/{$miembro->getId()}", $mensajeData, false));
                    }
                }
            } elseif ($receptor) {
                // Mensaje privado: notificamos al receptor y a nosotros mismos
                $hub->publish(new Update("https://brainhub.com/user/{$receptor->getId()}", $mensajeData, false));
                $hub->publish(new Update("https://brainhub.com/user/{$user->getId()}", $mensajeData, false));
                $hub->publish(new Update("https://brainhub.com/notifs/{$receptor->getId()}", $mensajeData, false));
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

        $hub->publish(new Update(
            $topic,
            json_encode([
                'isTyping' => true,
                'autor'    => $user->getUsername()
            ]),
            false
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

    #[Route('/api/user/{id}/profile', name: 'api_user_profile', methods: ['GET'])]
    public function getUserProfile(int $id, UserRepository $userRepo): JsonResponse
    {
        $user = $userRepo->find($id);
        if (!$user) return new JsonResponse(['error' => 'Usuario no encontrado'], 404);

        return new JsonResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'fotoPerfil' => $user->getFotoPerfil(),
            'biografia' => $user->getBiografia(),
            'ciudad' => $user->getCiudad()
        ]);
    }

    #[Route('/chat/conversations', name: 'app_chat_conversations', methods: ['GET'])]
    public function getConversations(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return new JsonResponse([]);

        // Buscamos todos los usuarios con los que el usuario actual ha intercambiado mensajes
        $qb = $em->createQueryBuilder();
        $qb->select('u.id, u.username, u.fotoPerfil')
            ->from('App\Entity\User', 'u')
            ->innerJoin('App\Entity\Mensaje', 'm', 'WITH', '(m.autor = u AND m.receptor = :me) OR (m.receptor = u AND m.autor = :me)')
            ->where('u.id != :me')
            ->setParameter('me', $user->getId())
            ->groupBy('u.id')
            ->orderBy('MAX(m.fechaCreacion)', 'DESC'); // Ordenados por el último mensaje

        $users = $qb->getQuery()->getResult();

        return new JsonResponse($users);
    }
}
