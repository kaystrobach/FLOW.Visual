<?php
/**
 * Created by PhpStorm.
 * User: kay
 * Date: 24.04.15
 * Time: 09:22.
 */

namespace KayStrobach\VisualSearch\Utility;

use Neos\Flow\Annotations as Flow;

class MapperUtility
{
    /**
     * @var \Neos\Flow\Log\SystemLoggerInterface
     * @Flow\Inject
     */
    protected $systemLogger;

    /**
     * @var \Neos\Flow\ObjectManagement\ObjectManager
     * @Flow\Inject
     */
    protected $objectManager;

    /**
     * @var \Neos\Flow\Configuration\ConfigurationManager
     * @Flow\Inject
     */
    protected $configurationManager;

    /**
     * @param array  $searchConfiguration
     * @param array  $query
     * @param string $facet
     *
     * @return object
     */
    public function getSingleObject($searchConfiguration, $query, $facet)
    {
        $facetEntry = ArrayUtility::getOneSubEntryWith($query, 'facet', $facet);
        $objectIdentifier = $facetEntry['value'];
        /** @var \Neos\Flow\Persistence\Repository $objectRepository */
        $objectRepository = $this->objectManager->get($searchConfiguration[$facet]['selector']['repository']);

        return $objectRepository->findByIdentifier($objectIdentifier);
    }

    // -------------------------------------------------------------------------

    /**
     * iterates over all.
     *
     * @todo make it work with multiple values per facet
     *
     * @param string                                $searchName
     * @param array                                 $query
     * @param \Neos\Flow\Persistence\Doctrine\Query $queryObject
     *
     * @return array
     */
    public function buildQuery($searchName, $query, $queryObject)
    {
        $searchConfiguration = $this->configurationManager->getConfiguration(
            'VisualSearch',
            'Searches.'.$searchName.'.autocomplete'
        );

        $demands = [];
        foreach ($query as $queryEntry) {
            if (isset($queryEntry['facet'])) {
                $facet = $queryEntry['facet'];
                if (isset($searchConfiguration[$facet]['selector']['repository'])) {
                    $repositoryClassName = $searchConfiguration[$facet]['selector']['repository'];
                    /** @var \Neos\Flow\Persistence\Doctrine\Repository $repository */
                    $repository = $this->objectManager->get($repositoryClassName);
                    $value = $repository->findByIdentifier($queryEntry['value']);
                    if (is_object($value)) {
                        $this->systemLogger->log('Facet: '.$facet.' = '.$queryEntry['value'].' as Object '.get_class($value), LOG_DEBUG);
                    } else {
                        $this->systemLogger->log('Facet: '.$facet.' = '.$queryEntry['value'].' as literal', LOG_DEBUG);
                    }
                } else {
                    $value = $queryEntry['value'];
                    $this->systemLogger->log('Facet: '.$facet.' = '.$queryEntry['value'].' as string', LOG_DEBUG);
                }
                if (isset($searchConfiguration[$facet]['matches']['equals']) && (is_array($searchConfiguration[$facet]['matches']['equals']))) {
                    $this->systemLogger->log('add equals demand for '.$facet, LOG_DEBUG);
                    $subDemands = [];
                    foreach ($searchConfiguration[$facet]['matches']['equals'] as $matchField) {
                        $subDemands[] = $queryObject->equals($matchField, $value);
                    }
                    $demands[] = $queryObject->logicalOr($subDemands);
                }
                if (isset($searchConfiguration[$facet]['matches']['like']) && (is_array($searchConfiguration[$facet]['matches']['like']))) {
                    $this->systemLogger->log('add like demand for '.$facet, LOG_DEBUG);
                    $subDemands = [];
                    foreach ($searchConfiguration[$facet]['matches']['like'] as $matchField) {
                        $subDemands[] = $queryObject->like($matchField, '%'.$value.'%');
                    }
                    $demands[] = $queryObject->logicalOr($subDemands);
                }
                if (isset($searchConfiguration[$facet]['matches']['%like']) && (is_array($searchConfiguration[$facet]['matches']['%like']))) {
                    $this->systemLogger->log('add %like demand for '.$facet, LOG_DEBUG);
                    $subDemands = [];
                    foreach ($searchConfiguration[$facet]['matches']['%like'] as $matchField) {
                        $subDemands[] = $queryObject->like($matchField, '%'.$value);
                    }
                    $demands[] = $queryObject->logicalOr($subDemands);
                }
                if (isset($searchConfiguration[$facet]['matches']['like%']) && (is_array($searchConfiguration[$facet]['matches']['like%']))) {
                    $this->systemLogger->log('add like% demand for '.$facet, LOG_DEBUG);
                    $subDemands = [];
                    foreach ($searchConfiguration[$facet]['matches']['like%'] as $matchField) {
                        $subDemands[] = $queryObject->like($matchField, $value.'%');
                    }
                    $demands[] = $queryObject->logicalOr($subDemands);
                }
                if (isset($searchConfiguration[$facet]['matches']['sameday']) && (is_array($searchConfiguration[$facet]['matches']['sameday']))) {
                    $this->systemLogger->log('add sameday demand for '.$facet, LOG_DEBUG);
                    $subDemands = [];
                    $dateStartObject = \DateTime::createFromFormat(
                        $searchConfiguration[$facet]['selector']['dateFormat'] ?? 'd.m.Y',
                        $value
                    );
                    if ($dateStartObject instanceof \DateTime) {
                        $dateStartObject->setTime(0, 0);
                        $dateEndObject = clone $dateStartObject;
                        $dateEndObject->setTime(23, 59, 59);

                        foreach ($searchConfiguration[$facet]['matches']['sameday'] as $matchField) {
                            $subDemands[] = $queryObject->logicalAnd(
                                [
                                    $queryObject->greaterThanOrEqual($matchField, $dateStartObject),
                                    $queryObject->lessThanOrEqual($matchField, $dateEndObject),
                                ]
                            );
                        }
                        $demands[] = $queryObject->logicalOr($subDemands);
                    }
                }
            }
        }

        return $demands;
    }
}
