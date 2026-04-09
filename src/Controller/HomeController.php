<?php
namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface; // La herramienta para manejar la BD
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\SalaRepository;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(SalaRepository $salaRepository): Response
    {
        $user = $this->getUser();
        $misSalas = $user ? $salaRepository->createQueryBuilder('s')
            ->leftJoin('s.miembros', 'm')
            ->where('s.creador = :user OR m = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult() : [];

        return $this->render('home/index.html.twig', [
            'mensaje' => '¡Bienvenido a BrainHub!', // Para arreglar el error anterior
            'salas' => $misSalas,                    // Aquí enviamos "salas"
        ]);
    }
}
