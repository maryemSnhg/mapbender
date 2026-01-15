<?php

namespace App\Controller\Api;

use App\Service\MapbenderMap;
use Doctrine\ORM\EntityManagerInterface;
use Mapbender\CoreBundle\Entity\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MapLayersController extends AbstractController
{
    #[Route('/api/maps/{slug}/layers', name: 'api_map_layers', methods: ['GET'])]
    public function layers(
        string $slug,
        EntityManagerInterface $em,
        MapbenderMap $mapbenderMap
    ): JsonResponse {
        $application = $em->getRepository(Application::class)
            ->findOneBy(['slug' => $slug]);

        if (!$application) {
            return $this->json(['error' => 'Map not found'], 404);
        }

        return $this->json(
            $mapbenderMap->build($application)['layers']
        );
    }
}
