<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Mapbender\CoreBundle\Entity\Application;

class MapbenderMap
{
    /**
     * Constructor.
     *
     * @param Connection $conn Doctrine DBAL connection
     */
    public function __construct(private Connection $conn) {}
    /**
     * Build the map structure for a given application.
     *
     * @param Application $application
     * @return array
     */
    public function build(Application $application): array
    {
        return [
            'id'        => $application->getId(),
            'title'     => $application->getTitle(),
            'slug'      => $application->getSlug(),
            'layersets' => $this->getLayersets($application->getId()),
        ];
    }

    /**
     * Retrieve all layersets for a given application ID.
     *
     * @param int $applicationId
     * @return array
     */
    private function getLayersets(int $applicationId): array
    {
        $layersets = $this->conn->fetchAllAssociative(
            'SELECT id, title
             FROM mb_core_layerset
             WHERE application_id = ?
             ORDER BY id',
            [$applicationId]
        );

        foreach ($layersets as &$layerset) {
            $layerset['sources'] = $this->getSourceInstances($layerset['id']);
        }

        return $layersets;
    }

    /**
     * Retrieve all source instances for a given layerset ID.
     *
     * @param int $layersetId
     * @return array
     */
    private function getSourceInstances(int $layersetId): array
    {
        $instances = $this->conn->fetchAllAssociative(
            'SELECT id, discr, title, weight, enabled
             FROM mb_core_sourceinstance
             WHERE layerset = ?
             ORDER BY weight ASC',
            [$layersetId]
        );

        $sources = [];

        foreach ($instances as $instance) {
            $type = $this->normalizeDiscr($instance['discr']);

            $source = $this->getSourceForInstance((int)$instance['id'], $type);

            if (!$source) {
                error_log("Instance skipped: id={$instance['id']} type={$instance['discr']} (normalized type: $type)");
                continue;
            }

            $source['instance_id'] = (int)$instance['id'];
            $source['enabled']     = (bool)$instance['enabled'];
            $source['layers']      = $this->getLayersForInstance((int)$instance['id'], $type);

            $sources[] = $source;
        }

        return $sources;
    }
    /**
     * Normalize the discr field to a known type.
     *
     * @param string $discr
     * @return string
     */
    private function normalizeDiscr(string $discr): string
    {
        $discr = strtolower(trim($discr));

        return match ($discr) {
            'wms', 'wmsinstance' => 'WMS',
            'wmts', 'wmtsinstance' => 'WMTS',
            'vector', 'vectortiles', 'vectortilesinstance' => 'VECTOR',
            default => '',
        };
    }
    /**
     * Get the source data for a given instance ID and type.
     *
     * @param int $instanceId
     * @param string $type
     * @return array|null
     */
    private function getSourceForInstance(int $instanceId, string $type): ?array
    {
        return match ($type) {
            'WMS'   => $this->getWmsSource($instanceId),
            'WMTS'  => $this->getWmtsSource($instanceId),
            'VECTOR' => $this->getVectorSource($instanceId),
            default => null,
        };
    }
    /**
     * Get WMS source information for a given instance ID.
     *
     * @param int $instanceId
     * @return array|null
     */
    private function getWmsSource(int $instanceId): ?array
    {
        return $this->conn->fetchAssociative(
            'SELECT 
                wi.id AS instance_id,
                ws.id AS source_id,
                ws.originurl AS url
             FROM mb_wms_wmsinstance wi
             JOIN mb_wms_wmssource ws ON ws.id = wi.wmssource
             WHERE wi.id = ?',
            [$instanceId]
        ) ?: null;
    }
    /**
     * Get WMTS source information for a given instance ID.
     *
     * @param int $instanceId
     * @return array|null
     */
    private function getWmtsSource(int $instanceId): ?array
    {
        return $this->conn->fetchAssociative(
            'SELECT
                wi.id AS instance_id,
                ws.id AS source_id,
                ws.url AS url
             FROM mb_wmts_wmtsinstance wi
             JOIN mb_wmts_wmtslayersource ws ON ws.id = wi.wmtssource
             WHERE wi.id = ?',
            [$instanceId]
        ) ?: null;
    }
    /**
     * Get Vector source information for a given instance ID.
     *
     * @param int $instanceId
     * @return array|null
     */
    private function getVectorSource(int $instanceId): ?array
    {
        return $this->conn->fetchAssociative(
            'SELECT
                vi.id AS instance_id,
                vs.id AS source_id,
                vs.json_url AS url
             FROM mb_vectortiles_instance vi
             JOIN mb_vectortiles_source vs ON vs.id = vi.source
             WHERE vi.id = ?',
            [$instanceId]
        ) ?: null;
    }
    /**
     * Get all layers for a given instance ID and type.
     *
     * @param int $instanceId
     * @param string $type
     * @return array
     */
    private function getLayersForInstance(int $instanceId, string $type): array
    {
        return match ($type) {
            'WMS'   => $this->getWmsLayers($instanceId),
            'WMTS'  => $this->getWmtsLayers($instanceId),
            'VECTOR' => $this->getVectorLayers($instanceId),
            default => [],
        };
    }
    /**
     * Récupère les layers WMS pour une instance donnée
     * avec les informations réelles de la source et le statut "selected"
     * depuis l'instance.
     */
    private function getWmsLayers(int $instanceId): array
    {
        return $this->conn->fetchAllAssociative(
            'SELECT 
            l.id        AS layer_id,
            l.title     AS title,
            l.name      AS name,
            l.abstract  AS abstract,
            il.selected AS selected,
            l.styles    AS styles,
            il.minscale AS minscale,
            il.maxscale AS maxscale
         FROM mb_wms_wmsinstancelayer il
         JOIN mb_wms_wmslayersource l ON l.id = il.wmslayersource
         WHERE il.wmsinstance = ?
         ORDER BY il.priority ASC',
            [$instanceId]
        );
    }



    /**
     * Get WMTS layers for a given instance ID.
     *
     * @param int $instanceId
     * @return array
     */
    private function getWmtsLayers(int $instanceId): array
    {
        return $this->conn->fetchAllAssociative(
            'SELECT id, title, selected, style
             FROM mb_wmts_wmtsinstancelayer
             WHERE wmtsinstance = ?
             ORDER BY priority ASC',
            [$instanceId]
        );
    }
    /**
     * Get Vector layers for a given instance ID.
     *
     * @param int $instanceId
     * @return array
     */
    private function getVectorLayers(int $instanceId): array
    {
        return $this->conn->fetchAllAssociative(
            'SELECT id, feature_info_title AS title, selected, opacity
             FROM mb_vectortiles_instance
             WHERE id = ?',
            [$instanceId]
        );
    }
}
