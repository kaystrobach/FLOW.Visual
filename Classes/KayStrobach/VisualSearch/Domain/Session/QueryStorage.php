<?php
/**
 * Created by PhpStorm.
 * User: kay
 * Date: 24.04.15
 * Time: 13:11.
 */

namespace KayStrobach\VisualSearch\Domain\Session;

use KayStrobach\VisualSearch\Utility\ArrayUtility;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;

/**
 * the goal of this class is to store all filters and restore them on load, this way we can store the queries in the
 * session without a large effort.
 *
 * @Flow\Scope("session")
 */
class QueryStorage
{
    /**
     * contains all the stored queries as key => valueLabel relation.
     *
     * @var array
     */
    protected $queries = [];

    /**
     * @Flow\Inject
     *
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @param string $name
     *
     * @return array
     */
    public function getQuery($name)
    {
        if (isset($this->queries[$name])) {
            return $this->queries[$name];
        }

        return [];
    }

    /**
     * @param string $name
     * @param string $facet
     *
     * @return bool
     */
    public function isFacetInQuery($name, $facet)
    {
        return ArrayUtility::hasSubEntryWith(
            $this->getQuery($name),
            'facet',
            $facet
        );
    }

    /**
     * @param string $name
     * @param string $facet
     *
     * @return array|null
     */
    public function getOneFacetInQuery($name, $facet)
    {
        return ArrayUtility::getOneSubEntryWith(
            $this->getQuery($name),
            'facet',
            $facet
        );
    }

    /**
     * @param string name
     * @param array $query
     *
     * @return void
     */
    public function setQuery($name, $query)
    {
        $this->queries[$name] = $query;
    }

    /**
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * @param string $name
     * @param string $facet
     * @param mixed  $valueLabel
     */
    public function addQueryConstraint($name, $facetLabel, $facet, $valueLabel, $value)
    {
        $id = '';
        if (is_string($facet)) {
            $id = $facet;
        }
        if (is_object($facet)) {
            $id = $this->persistenceManager->getIdentifierByObject($valueLabel);
        }
        if ($id !== null) {
            if (!isset($this->queries[$name])) {
                $this->queries[$name] = [];
            }
            $this->queries[$name][] = [
                'facetLabel' => $facetLabel,
                'facet'      => $id,
                'valueLabel' => $valueLabel,
                'value'      => $value,
            ];
        }
    }

    /**
     * @param string $name
     *
     * @return int
     */
    public function getNumberOfConstraints($name)
    {
        if (isset($this->queries[$name])) {
            return count($this->queries[$name]);
        }

        return 0;
    }
}
