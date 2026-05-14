<?php

namespace App\Controller;

use App\Entity\Sala;
use App\Form\SalaType;
use App\Repository\SalaRepository;
use App\Service\DashboardAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        SalaRepository $salaRepository,
        Request $request,
        DashboardAnalyticsService $analyticsService
    ): Response {
        $user = $this->getUser();
        $query = $request->query->get('q');

        if ($user) {
            if ($query) {
                $salasAMostrar = $salaRepository->findBySearchQuery($query);
            } else {
                $salasAMostrar = $salaRepository->createQueryBuilder('s')
                    ->leftJoin('s.miembros', 'm')
                    ->where('s.creador = :user OR m = :user')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getResult();
            }

            // Obtener métricas pre-formateadas
            $metricasCrudas = $analyticsService->getUserDashboardMetrics();

            $metricas = [
                'salas_activas_hoy' => $metricasCrudas['salas_activas_hoy'],
                'salas_totales' => $metricasCrudas['salas_totales'],
                'tiempo_estudio_hoy' => $metricasCrudas['tiempo_estudio_hoy'],
                'tiempo_estudio_meta' => $metricasCrudas['tiempo_estudio_meta'],
                'mensajes_hoy' => $metricasCrudas['mensajes_hoy'],
                'salas_participadas_hoy' => $metricasCrudas['salas_participadas_hoy'],
                'racha_dias' => $metricasCrudas['racha_dias'],
                'record_racha' => $metricasCrudas['record_racha'],

                // Porcentajes
                'porcentaje_cambio_salas' => $metricasCrudas['porcentaje_cambio_salas'],
                'porcentaje_cambio_mensajes' => $metricasCrudas['porcentaje_cambio_mensajes'],
                'porcentaje_cambio_tiempo' => $metricasCrudas['porcentaje_cambio_tiempo'],

                // ✅ DATOS FORMATEADOS PARA TWIG
                'tiempo_formateado' => $analyticsService->formatTime($metricasCrudas['tiempo_estudio_hoy']),
                'meta_formateada' => $analyticsService->formatTime($metricasCrudas['tiempo_estudio_meta']),
                'porcentaje_barra_salas' => $metricasCrudas['salas_totales'] > 0
                    ? round(($metricasCrudas['salas_activas_hoy'] / $metricasCrudas['salas_totales']) * 100)
                    : 0,
                'porcentaje_barra_tiempo' => min(
                    round(($metricasCrudas['tiempo_estudio_hoy'] / $metricasCrudas['tiempo_estudio_meta']) * 100),
                    100
                ),
            ];
        } else {
            $salasAMostrar = [];
            $metricas = [];
        }

        $sala = new Sala();
        $form = $this->createForm(SalaType::class, $sala);

        return $this->render('home/index.html.twig', [
            'salas' => $salasAMostrar,
            'form' => $form->createView(),
            'metricas' => $metricas,
        ]);
    }

    /**
     * ✅✅✅ NUEVO: API Endpoint para obtener métricas en tiempo real (AJAX)
     */
    #[Route('/api/dashboard/metrics', name: 'api_dashboard_metrics', methods: ['GET'])]
    public function getDashboardMetrics(DashboardAnalyticsService $analyticsService): JsonResponse
    {
        try {
            $metricas = $analyticsService->getUserDashboardMetrics();

            return $this->json([
                'success' => true,
                'data' => $metricas,
                'timestamp' => (new \DateTime())->format('c'),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API Endpoint para iniciar sesión de estudio (AJAX)
     */
    #[Route('/api/session/start', name: 'api_session_start', methods: ['POST'])]
    public function startSession(Request $request, DashboardAnalyticsService $analyticsService): Response
    {
        try {
            $salaId = $request->request->get('salaId');
            $sala = $salaId ? $this->getDoctrine()->getRepository(Sala::class)->find($salaId) : null;

            $session = $analyticsService->startStudySession($sola);

            return $this->json([
                'success' => true,
                'sessionId' => $session->getId(),
                'startTime' => $session->getStartTime()->format('H:i:s')
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API Endpoint para finalizar sesión de estudio (AJAX)
     */
    #[Route('/api/session/{id}/end', name: 'api_session_end', methods: ['POST'])]
    public function endSession(int $id, DashboardAnalyticsService $analyticsService): Response
    {
        try {
            $session = $this->getDoctrine()->getRepository(\App\Entity\UserSession::class)->find($id);

            if (!$session || $session->getUser() !== $this->getUser()) {
                return $this->json(['error' => 'No autorizado'], 403);
            }

            $analyticsService->endStudySession($session);
            $metricas = $analyticsService->getUserDashboardMetrics();

            return $this->json([
                'success' => true,
                'duration' => $session->getFormattedDuration(),
                'updatedMetrics' => $metricas
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
