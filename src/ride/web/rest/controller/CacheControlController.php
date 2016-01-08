<?php

namespace ride\web\rest\controller;

use ride\library\http\jsonapi\JsonApiQuery;

/**
 * Controller for the cache JSON API interface
 */
class CacheControlController extends AbstractResourceJsonApiController {

    /**
     * Hook to perform extra initializing
     * @return null
     */
    protected function initialize() {
        $this->addSupportedExtension(self::EXTENSION_BULK);

        $this->setType('caches');
        $this->setIdField('id');
        $this->setAttribute('name');
        $this->setAttribute('isLocked');
        $this->setAttribute('isEnabled');

        $this->setRoute(self::ROUTE_INDEX, 'api.caches.index');
        $this->setRoute(self::ROUTE_DETAIL, 'api.caches.detail');
    }

    /**
     * Gets the resources for the provided query
     * @param \ride\library\http\jsonapi\JsonApiQuery $query
     * @param integer $total Total number of entries before pagination
     * @return mixed Array with resource data or false when an error occured
     */
    protected function getResources(JsonApiQuery $query, &$total) {
        $cacheControls = $this->dependencyInjector->getAll('ride\\library\\cache\\control\\CacheControl');

        $nameQuery = null;
        $lockedQuery = null;
        $enabledQuery = null;

        $filters = $query->getFilters();
        foreach ($filters as $filterName => $filterValue) {
            switch ($filterName) {
                case 'name':
                    $nameQuery = $filterValue;

                    break;
                case 'isLocked':
                    $lockedQuery = $filterValue;

                    break;
                case 'isEnabled':
                    $enabledQuery = $filterValue;

                    break;
                default:
                    $this->addFilterNotFoundError($this->type, $filterName);

                    break;
            }
        }

        $sorter = $this->createSorter($this->type, array('name', 'isLocked', 'isEnabled'));

        if ($this->document->getErrors()) {
            return false;
        }

        // perform filter
        if ($nameQuery || $lockedQuery || $enabledQuery) {
            foreach ($cacheControls as $key => $cacheControl) {
                if ($nameQuery && $this->filterStringValue($nameQuery, $cacheControl->getName()) === false) {
                    unset($cacheControls[$key]);
                } elseif ($lockedQuery !== null && (($lockedQuery && !$cacheControl->isLocked()) || (!$lockedQuery && $cacheControl->isLocked()))) {
                    unset($cacheControls[$key]);
                } elseif ($enabledQuery !== null && (($enabledQuery && !$cacheControl->isEnabled()) || (!$enabledQuery && $cacheControl->isEnabled()))) {
                    unset($cacheControls[$key]);
                }
            }
        }

        // create resource data from the parameters
        foreach ($cacheControls as $key => $cacheControl) {
            $cacheControls[$key] = array(
                'id' => $cacheControl->getName(),
                'name' => $cacheControl->getName(),
                'isLocked' => !$cacheControl->canToggle(),
                'isEnabled' => $cacheControl->isEnabled(),
                'control' => $cacheControl,
            );
        }

        // perform sort
        $cacheControls = $sorter->sort($cacheControls);

        // perform pagination
        $total = count($cacheControls);
        $cacheControls = array_slice($cacheControls, $query->getOffset(), $query->getLimit(100));

        // return
        return $cacheControls;
    }

    /**
     * Gets the resource for the provided id
     * @param string $id Id of the resource
     * @param boolean $addError Set to false to skip adding the error when the
     * resource is not found
     * @return mixed Resource data if found or false when an error occured
     */
    protected function getResource($id, $addError = true) {
        try {
            $cacheControl = $this->dependencyInjector->get('ride\\library\\cache\\control\\CacheControl', $id);
        } catch (Exception $exception) {
            if ($addError) {
                $this->addResourceNotFoundError($this->type, $id);
            }

            return false;
        }

        return array(
            'id' => $id,
            'name' => $cacheControl->getName(),
            'isLocked' => !$cacheControl->canToggle(),
            'isEnabled' => $cacheControl->isEnabled(),
            'control' => $cacheControl,
        );
    }

    /**
     * Validates a resource
     * @param mixed $resource Resource data
     * @param string $index
     * @return null
     */
    protected function validateResource($resource, $index = null) {
        if ($resource['name'] != $resource['control']->getName()) {
            $this->addAttributeReadonlyError($this->type, 'name');
        }

        if ($resource['isLocked'] != !$resource['control']->canToggle()) {
            $this->addAttributeReadonlyError($this->type, 'isLocked');
        }

        if (!is_bool($resource['isEnabled'])) {
            $this->addAttributeValidationError($this->type, 'isEnabled', 'should be a boolean', $index);
        } elseif ($resource['isEnabled'] != $resource['control']->isEnabled() && !$resource['control']->canToggle()) {
            $this->addAttributeReadonlyError($this->type, 'isEnabled');
        }
    }

    /**
     * Saves a resource to the data store
     * @param mixed $resource Resource data
     * @return null
     */
    protected function saveResource(&$resource) {
        if ($resource['isEnabled'] == $resource['control']->isEnabled()) {
            return;
        } elseif ($resource['isEnabled']) {
            $resource['control']->enable();
        } else {
            $resource['control']->disable();
        }
    }

    /**
     * Action to warm the provided cache
     * @param string $id Id of the cache
     * @return null
     */
    public function warmAction($id) {
        $resource = $this->getResource($id);
        if (!$resource) {
            return;
        }

        $resource['control']->warm();
    }

    /**
     * Action to clear the provided cache
     * @param string $id Id of the cache
     * @return null
     */
    public function clearAction($id) {
        $resource = $this->getResource($id);
        if (!$resource) {
            return;
        }

        $resource['control']->clear();
    }

}
