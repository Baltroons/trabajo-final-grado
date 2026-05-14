<?php
// src/Service/DashboardAnalyticsService.php
namespace App\Service;

use App\Entity\Mensaje;
use App\Entity\User;
use App\Entity\UserSession;
use App\Entity\UserStreak;
use App\Repository\MensajeRepository;
use App\Repository\SalaRepository;
use App\Repository\UserSessionRepository;
use App\Repository\UserStreakRepository;
use Doctrine\ORM\EntityManagerInterface;
// ❌ ELIMINAR: use Symfony\Bundle\Security\Bundle\Security; (Esta clase no existe en tu Symfony)
// ✅ USAR ESTE EN SU LUGAR:
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DashboardAnalyticsService
{
    private EntityManagerInterface $em;
    // ✅ CAMBIAR: En vez de Security, usamos TokenStorageInterface
    private TokenStorageInterface $tokenStorage;

    private MensajeRepository $mensajeRepo;
    private SalaRepository $salaRepo;
    private UserSessionRepository $sessionRepo;
    private UserStreakRepository $streakRepo;

    public function __construct(
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,  // ← CAMBIO AQUÍ
        MensajeRepository $mensajeRepo,
        SalaRepository $salaRepo,
        UserSessionRepository $sessionRepo,
        UserStreakRepository $streakRepo
    ) {
        $this->em = $em;
        $this->tokenStorage = $tokenStorage;  // ← CAMBIO AQUÍ
        $this->mensajeRepo = $mensajeRepo;
        $this->salaRepo = $salaRepo;
        $this->sessionRepo = $sessionRepo;
        $this->streakRepo = $streakRepo;
    }

    /**
     * Helper para obtener el usuario actual (reemplaza a $this->security->getUser())
     */
    private function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) return null;

        $user = $token->getUser();
        return ($user instanceof User) ? $user : null;
    }

    /**
     * Obtiene todas las métricas para el dashboard del usuario actual
     */
    public function getUserDashboardMetrics(): array
    {
        // ✅ USAR NUEVO MÉTODO HELPER
        $user = $this->getUser();
        if (!$user) return [];

        $hoy = new \DateTimeImmutable('today');
        $manana = $hoy->modify('+1 day');

        return [
            'salas_activas_hoy' => $this->getSalasActivasHoy($user, $hoy, $manana),
            'salas_totales' => $this->getSalasTotales($user),
            'tiempo_estudio_hoy' => $this->getTiempoEstudioHoy($user, $hoy, $manana),
            'tiempo_estudio_meta' => 18000,
            'mensajes_hoy' => $this->getMensajesHoy($user, $hoy, $manana),
            'salas_participadas_hoy' => $this->getSalasParticipadasHoy($user, $hoy, $manana),
            'racha_dias' => $this->getRachaActual($user),
            'record_racha' => $this->getRecordRacha($user),
            'porcentaje_cambio_salas' => 12.5,
            'porcentaje_cambio_mensajes' => 28.0,
            'porcentaje_cambio_tiempo' => 15.0,
        ];
    }

    /**
     * 1. SALAS ACTIVAS HOY
     */
    private function getSalasActivasHoy(User $user, \DateTimeImmutable $hoy, \DateTimeImmutable $manana): int
    {
        try {
            return $this->mensajeRepo->countDistinctSalasByUserAndDateRange($user, $hoy, $manana);
        } catch (\Exception $e) {
            error_log('Error getSalasActivasHoy: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Total de salas del usuario
     */
    private function getSalasTotales(User $user): int
    {
        try {
            return count($user->getSalasCreadas()) + count($user->getSalasSuscritas());
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 2. TIEMPO DE ESTUDIO HOY
     */
    private function getTiempoEstudioHoy(User $user, \DateTimeImmutable $hoy, \DateTimeImmutable $manana): int
    {
        try {
            $sesiones = $this->sessionRepo->findBy([
                'user' => $user,
                'status' => 'completed'
            ], ['startTime' => 'DESC']);

            $totalSegundos = 0;
            foreach ($sesiones as $sesion) {
                if ($sesion->getStartTime() >= $hoy && $sesion->getStartTime() < $manana) {
                    $totalSegundos += $sesion->getDurationSeconds();
                }
            }

            return $totalSegundos;
        } catch (\Exception $e) {
            error_log('Error getTiempoEstudioHoy: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Formatea segundos a "3h 45m"
     */
    public function formatTime(int $seconds): string
    {
        if ($seconds <= 0) return '0m';

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes}m";
    }

    /**
     * 3. MENSAJES HOY
     */
    private function getMensajesHoy(User $user, \DateTimeImmutable $hoy, \DateTimeImmutable $manana): int
    {
        try {
            return $this->mensajeRepo->countByUserAndDateRange($user, $hoy, $manana);
        } catch (\Exception $e) {
            error_log('Error getMensajesHoy: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 4. SALAS PARTICIPADAS HOY
     */
    private function getSalasParticipadasHoy(User $user, \DateTimeImmutable $hoy, \DateTimeImmutable $manana): int
    {
        try {
            return $this->mensajeRepo->countDistinctSalasByUserAndDateRange($user, $hoy, $manana);
        } catch (\Exception $e) {
            error_log('Error getSalasParticipadasHoy: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 5. RACHA ACTUAL
     */
    private function getRachaActual(User $user): int
    {
        try {
            $streak = $this->streakRepo->findOneBy(['user' => $user]);

            if (!$streak) {
                $streak = new UserStreak();
                $streak->setUser($user);
                $streak->setCurrentStreak(1);
                $streak->setLongestStreak(1);
                $streak->updateStreakForToday();
                $this->em->persist($streak);
                $this->em->flush();
            } else {
                $streak->updateStreakForToday();
                $this->em->flush();
            }

            return $streak->getCurrentStreak();
        } catch (\Exception $e) {
            error_log('Error getRachaActual: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Récord personal de racha
     */
    private function getRecordRacha(User $user): int
    {
        try {
            $streak = $this->streakRepo->findOneBy(['user' => $user]);
            return $streak ? $streak->getLongestStreak() : 1;
        } catch (\Exception $e) {
            return 1;
        }
    }

    /**
     * Registrar inicio de sesión de estudio
     */
    public function startStudySession(?Sala $sala = null, string $activityType = 'study'): UserSession
    {
        $user = $this->getUser();  // ✅ Usar helper

        $session = new UserSession();
        $session->setUser($user);
        $session->setSala($sala);
        $session->setStatus('active');
        $session->setActivityType($activityType);

        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }

    /**
     * Finalizar sesión de estudio
     */
    public function endStudySession(UserSession $session): void
    {
        $session->setEndTime(new \DateTimeImmutable());
        $session->setStatus('completed');
        $this->em->flush();

        $this->actualizarStreak($session->getUser());
    }

    /**
     * Actualizar racha después de actividad
     */
    private function actualizarStreak(User $user): void
    {
        $streak = $this->streakRepo->findOneBy(['user' => $user]);

        if (!$streak) {
            $streak = new UserStreak();
            $streak->setUser($user);
        }

        $streak->updateStreakForToday();
        $this->em->persist($streak);
        $this->em->flush();
    }
}
