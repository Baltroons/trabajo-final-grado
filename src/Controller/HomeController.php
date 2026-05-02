<?php

namespace App\Controller;

use App\Entity\Sala;
use App\Form\SalaType;
use App\Repository\SalaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request; // Importante para el buscador
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{

    #[Route('/', name: 'app_home')]
    public function index(SalaRepository $salaRepository, Request $request): Response
    {
        $user = $this->getUser();
        $query = $request->query->get('q');

        // --- LÓGICA DE BÚSQUEDA ---
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
        } else {
            $salasAMostrar = [];
        }

        // --- LÓGICA DEL FORMULARIO PARA EL MODAL ---
        // Creamos una instancia vacía y su formulario para pasarlo al Twig
        $sala = new Sala();
        $form = $this->createForm(SalaType::class, $sala);

        return $this->render('home/index.html.twig', [
            'salas' => $salasAMostrar,
            'form' => $form->createView(), // ESTA ES LA LÍNEA QUE FALTA
        ]);
    }
}
