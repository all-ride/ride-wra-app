<?php

namespace ride\web\rest\jsonapi;

use ride\library\http\jsonapi\exception\JsonApiException;
use ride\library\http\jsonapi\JsonApiDocument;
use ride\library\http\jsonapi\JsonApiResourceAdapter;

use ride\web\WebApplication;

/**
 * JSON API Resource adapter for the system caches
 */
class CacheControlJsonApiResourceAdapter implements JsonApiResourceAdapter {

    /**
     * Constructs a new model resource adapter
     * @param \ride\web\WebApplication $web Instance of the web application
     * @param string $type Resource type for the parameters
     * @return null
     */
    public function __construct(WebApplication $web, $type = null) {
        if ($type === null) {
            $type = 'caches';
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
    public function getResource($cacheControl, JsonApiDocument $document, $relationshipPath = null) {
        if ($cacheControl === null) {
            return null;
        } elseif (!is_array($cacheControl) || !isset($cacheControl['name']) || !isset($cacheControl['control'])) {
            throw new JsonApiException('Could not get resource: provided data is not a cache control');
        }

        $query = $document->getQuery();
        $api = $document->getApi();
        $id = $cacheControl['id'];

        $resource = $api->createResource($this->type, $id, $relationshipPath);
        $resource->setLink('self', $this->web->getUrl('api.caches.detail', array('id' => $id)));

        if ($query->isFieldRequested($this->type, 'name')) {
            $resource->setAttribute('name', $cacheControl['name']);
        }
        if ($query->isFieldRequested($this->type, 'isLocked')) {
            $resource->setAttribute('isLocked', $cacheControl['isLocked']);
        }
        if ($query->isFieldRequested($this->type, 'isEnabled')) {
            $resource->setAttribute('isEnabled', $cacheControl['isEnabled']);
        }

        return $resource;
    }

}
