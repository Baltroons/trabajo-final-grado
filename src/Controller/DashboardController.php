<?php

namespace App\Controller\API;

use App\Repository\SalaRepository;
use App\Repository\MensajeRepository;
use App\Repository\UserSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/api/dashboard/metrics', name: 'app_api_dashboard_metrics', methods: ['GET'])]
    public function getLiveMetrics(
        SalaRepository $salaRepo,
        MensajeRepository $mensajeRepo,
        UserSessionRepository $sessionRepo
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['success' => false], 401);
        }

        $hoy = new \DateTime('today');
        $mañana = clone $hoy;
        $mañana->modify('+1 day');

        // 1. Salas activas hoy (donde el usuario participó)
        $salasActivas = $salaRepo->countSalasActivasUsuario($user, $hoy, $mañana);
        $salasTotales = $salaRepo->count([]);

        // 2. Mensajes enviados hoy
        $mensajesHoy = $mensajeRepo->countByUsuarioAndFecha($user, $hoy, $mañana);
        $salasParticipadas = $mensajeRepo->countDistinctSalasByUsuario($user, $hoy, $mañana);

        // 3. Tiempo de estudio hoy (en segundos)
        $tiempoSegundos = $sessionRepo->getTiempoTotalHoy($user, $hoy);

        // 4. Racha actual
        $racha = $sessionRepo->getRachaActual($user);
        $recordRacha = $sessionRepo->getRecordRacha($user);

        // 5. Porcentajes de cambio (comparar con ayer)
        $ayer = clone $hoy;
        $ayer->modify('-1 day');
        $mensajesAyer = $mensajeRepo->countByUsuarioAndFecha($user, $ayer, $hoy);
        $cambioMensajes = $mensajesAyer > 0 ? round(($mensajesHoy - $mensajesAyer) / $mensajesAyer * 100) : 0;

        return $this->json([
            'success' => true,
            'metrics' => [
                'salas_activas_hoy' => $salasActivas,
                'salas_totales' => $salasTotales,
                'mensajes_hoy' => $mensajesHoy,
                'salas_participadas_hoy' => $salasParticipadas,
                'tiempo_segundos' => $tiempoSegundos,
                'tiempo_formateado' => $this->formatTiempo($tiempoSegundos),
                'racha_dias' => $racha,
                'record_racha' => $recordRacha,
                'porcentaje_cambio_mensajes' => $cambioMensajes,
                'timestamp' => time() // Para caché
            ]
        ]);
    }

    private function formatTiempo(int $segundos): string
    {
        $horas = floor($segundos / 3600);
        $minutos = floor(($segundos % 3600) / 60);

        if ($horas > 0) {
            return "{$horas}h {$minutos}m";
        }
        return "{$minutos}m";
    }
}
