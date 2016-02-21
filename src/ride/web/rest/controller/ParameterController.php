<?php

namespace ride\web\rest\controller;

use ride\library\http\jsonapi\JsonApiQuery;

/**
 * Controller for the parameter JSON API interface
 */
class ParameterController extends AbstractResourceJsonApiController {

    /**
     * Hook to perform extra initializing
     * @return null
     */
    protected function initialize() {
        $this->addSupportedExtension(self::EXTENSION_BULK);

        $this->setType('parameters');
        $this->setIdField('id');
        $this->setAttribute('key');
        $this->setAttribute('value');

        $this->setRoute(self::ROUTE_INDEX, 'api.parameters.index');
        $this->setRoute(self::ROUTE_DETAIL, 'api.parameters.detail');
    }

    /**
     * Gets the resources for the provided query
     * @param \ride\library\http\jsonapi\JsonApiQuery $query
     * @param integer $total Total number of entries before pagination
     * @return mixed Array with resource data or false when an error occured
     */
    protected function getResources(JsonApiQuery $query, &$total) {
        $config = $this->getConfig();

        $parameters = $config->getAll();
        $parameters = $config->getConfigHelper()->flattenConfig($parameters);

        $keyQuery = null;
        $valueQuery = null;

        $filters = $query->getFilters();
        foreach ($filters as $filterName => $filterValue) {
            switch ($filterName) {
                case 'key':
                    $keyQuery = $filterValue;

                    break;
                case 'value':
                    $valueQuery = $filterValue;

                    break;
                default:
                    $this->addFilterNotFoundError($this->type, $filterName);

                    break;
            }
        }

        $sorter = $this->createSorter($this->type, array('key', 'value'));

        if ($this->document->getErrors()) {
            return false;
        }

        // perform filter
        if ($keyQuery || $valueQuery) {
            foreach ($parameters as $key => $value) {
                if ($keyQuery && $this->filterStringValue($keyQuery, $key) === false) {
                    unset($parameters[$key]);
                } elseif ($valueQuery && $this->filterStringValue($valueQuery, $value) === false) {
                    unset($parameters[$key]);
                }
            }
        }

        // create resource data from the parameters
        foreach ($parameters as $key => $value) {
            $parameters[$key] = array(
                'id' => $key,
                'key' => $key,
                'value' => $value,
            );
        }

        // perform sort
        $parameters = $sorter->sort($parameters);

        // perform pagination
        $total = count($parameters);
        $parameters = array_slice($parameters, $query->getOffset(), $query->getLimit(100));

        // return
        return $parameters;
    }

    /**
     * Gets the resource for the provided id
     * @param string $id Id of the resource
     * @param boolean $addError Set to false to skip adding the error when the
     * resource is not found
     * @return mixed Resource data if found or false when an error occured
     */
    protected function getResource($id, $addError = true) {
        $value = $this->getConfig()->get($id);
        if ($value === null) {
            if ($addError) {
                $this->addResourceNotFoundError($this->type, $id);
            }

            return false;
        }

        return array(
            'id' => $id,
            'key' => $id,
            'value' => $value,
        );
    }

    /**
     * Validates a resource
     * @param mixed $resource Resource data
     * @param string $index
     * @return null
     */
    protected function validateResource($resource, $index = null) {
        if (!$resource['key']) {
            $this->addAttributeValidationError($this->type, 'key', 'is required', $index);
        } elseif (!is_string($resource['key'])) {
            $this->addAttributeValidationError($this->type, 'key', 'should be a string', $index);
        } elseif (!strpos($resource['key'], '.')) {
            $this->addAttributeValidationError($this->type, 'key', 'should contain a . (dot)', $index);
        }

        if ($resource['value'] === null) {
            $this->addAttributeValidationError($this->type, 'value', 'is required', $index);
        }
    }

    /**
     * Saves a resource to the data store
     * @param mixed $resource Resource data
     * @return null
     */
    protected function saveResource(&$resource) {
        $this->getConfig()->set($resource['key'], $resource['value']);

        $resource['id'] = $resource['key'];
    }

    /**
     * Deletes a resource from the data store
     * @param mixed $resource Resource data
     * @return null
     */
    protected function deleteResource($resource) {
        $this->getConfig()->set($resource['key'], null);
    }

    /**
     * Gets a resource out of the submitted data
     * @return mixed
     */
    protected function getResourceFromData($data, $id = null, $index = null) {
        $resource = parent::getResourceFromData($data, $id, $index);

        if ($this->request->isPost() && $resource['key']) {
            $storeResource = $this->getResource($resource['key'], false);
            if ($storeResource) {
                return $this->addDataExistsError($index);
            }
        }

        return $resource;
    }

}
