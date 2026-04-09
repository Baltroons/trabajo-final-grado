<?php
namespace App\Controller;

use App\Entity\Tarea; // Importamos tu entidad
use Doctrine\ORM\EntityManagerInterface; // La herramienta para manejar la BD
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    // Inyectamos el EntityManagerInterface directamente en la función
    public function index(EntityManagerInterface $entityManager): Response
    {
        // --- 1. INSERTAR DATOS ---
        // Creamos una nueva tarea en memoria
        $nuevaTarea = new Tarea();
        $nuevaTarea->setNombre('Dominar Symfony 8, Docker y Twig');

        // Le decimos a Doctrine que "prepare" esta tarea y la guarde en Postgres
        $entityManager->persist($nuevaTarea);
        $entityManager->flush();

        // --- 2. LEER DATOS ---
        // Le pedimos a Doctrine que nos traiga TODAS las tareas de la tabla
        $tareas = $entityManager->getRepository(Tarea::class)->findAll();

        // --- 3. ENVIAR A TWIG ---
        return $this->render('home/index.html.twig', [
            'mensaje' => '¡Base de datos conectada con éxito!',
            'tareas' => $tareas, // Pasamos la lista de tareas a la vista
        ]);
    }
}
