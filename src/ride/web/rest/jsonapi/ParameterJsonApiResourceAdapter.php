<?php

namespace ride\web\rest\jsonapi;

use ride\library\http\jsonapi\exception\JsonApiException;
use ride\library\http\jsonapi\JsonApiDocument;
use ride\library\http\jsonapi\JsonApiResourceAdapter;

use ride\web\WebApplication;

/**
 * JSON API Resource adapter for the system parameters
 */
class ParameterJsonApiResourceAdapter implements JsonApiResourceAdapter {

    /**
     * Constructs a new model resource adapter
     * @param \ride\web\WebApplication $web Instance of the web application
     * @param string $type Resource type for the parameters
     * @return null
     */
    public function __construct(WebApplication $web, $type = null) {
        if ($type === null) {
            $type = 'parameters';
        }

        $this->web = $web;
        $this->type = $type;
    }

    /**
     * Gets a resource instance for the provided parameter
     * @param mixed $parameter Parameter to adapt
     * @param \ride\library\http\jsonapi\JsonApiDocument $document Document
     * which is requested
     * @param string $relationshipPath dot-separated list of relationship names
     * @return JsonApiResource|null
     */
    public function getResource($parameter, JsonApiDocument $document, $relationshipPath = null) {
        if ($parameter === null) {
            return null;
        } elseif (!is_array($parameter) || !isset($parameter['key']) || !isset($parameter['value'])) {
            throw new JsonApiException('Could not get resource: provided data is not a parameter');
        }

        $query = $document->getQuery();
        $api = $document->getApi();
        $id = $parameter['key'];

        $resource = $api->createResource($this->type, $id, $relationshipPath);
        $resource->setLink('self', $this->web->getUrl('api.parameters.detail', array('id' => $id)));

        if ($query->isFieldRequested($this->type, 'key')) {
            $resource->setAttribute('key', $parameter['key']);
        }
        if ($query->isFieldRequested($this->type, 'value')) {
            $resource->setAttribute('value', $parameter['value']);
        }

        return $resource;
    }

}
