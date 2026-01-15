<?php

namespace App\Controller\Api;

use App\Service\MapbenderMap;
use Doctrine\ORM\EntityManagerInterface;
use Mapbender\CoreBundle\Entity\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MapController extends AbstractController
{
    #[Route('/api/maps/{name}', name: 'api_map_show', methods: ['GET'])]
    public function show(
        string $name,
        EntityManagerInterface $em,
        MapbenderMap $builder
    ): JsonResponse {
        // On cherche la map par slug ou titre
        $application = $em->getRepository(Application::class)
            ->findOneBy(['slug' => $name])
            ?? $em->getRepository(Application::class)
            ->findOneBy(['title' => $name]);

        if (!$application) {
            return $this->json(['error' => 'Map not found'], 404);
        }

        // Retour JSON avec toutes les infos de la map et layers
        return $this->json($builder->build($application));
    }
}
