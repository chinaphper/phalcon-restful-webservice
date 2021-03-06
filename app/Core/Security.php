<?php


namespace Jowy\Phrest\Core;


use Phalcon\Dispatcher;
use Phalcon\Events\Event;
use Phalcon\Mvc\User\Plugin;
use Jowy\Phrest\Core\Engine as SecurityEngine;
use Phalcon\Exception as PhalconException;

class Security extends Plugin
{
    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher)
    {
        try {
            // read class annotation
            $class_annotation = $this->annotations->get($dispatcher->getHandlerClass())->getClassAnnotations();
            $api_annotation = $class_annotation->get("Api");

            // read method annotation
            $method_annotation = $this->annotations->getMethod(
                $dispatcher->getHandlerClass(),
                $dispatcher->getActiveMethod()
            );

            $engine = new SecurityEngine();

            // check API key
            $key = $engine->checkKeyLevel($this->request->getHeader("HTTP_X_API_KEY"), $api_annotation);

            // check authentication if exist
            $engine->checkAuth($method_annotation);

            // check IP whitelist
            $engine->checkWhitelist($method_annotation);

            $hasLimit = $api_annotation->hasNamedArgument("limits") || $method_annotation->has("Limit");

            // check limit
            if (!$key->getIgnoreLimit() && $hasLimit) {
                $engine->checkKeyLimitOnClass($key, $api_annotation->getNamedArgument("limits"));
                $engine->checkMethodLimitByKey($key, $method_annotation->get("Limit")->getArguments());
            }

            // write logs to db
            $engine->log(
                $key->getApiKeyId(),
                $this->request->getClientAddress(),
                $this->request->getMethod(),
                $this->request->get("_url")
            );

        } catch (PhalconException $e) {
            $this->apiResponse->withError($e->getMessage(), $e->getCode());
            return false;
        }

        return true;
    }
}

// EOF
