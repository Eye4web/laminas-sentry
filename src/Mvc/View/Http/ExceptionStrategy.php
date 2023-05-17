<?php

declare(strict_types=1);

/**
 * Andre Cardoso LaminasSentry
 *
 * This source file is part of the Andre Cardoso LaminasSentry package
 *
 * @package    LaminasSentry\Mvc\View\Http\ExceptionStrategy
 * @license    MIT License {@link /docs/LICENSE}
 * @copyright  Copyright (c) 2023, Andre Cardoso
 */

namespace LaminasSentry\Mvc\View\Http;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Response;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\ViewModel;

/**
 * For the moment, this is just an augmented copy of the default ZF ExceptionStrategy
 * This is on purpose despite the duplication of code until the module stabilizes and it's clear what need exactly
 *
 * @package    LaminasSentry\Mvc\View\Http\ExceptionStrategy
 */
class ExceptionStrategy extends AbstractListenerAggregate
{
    /**
     * Display exceptions?
     *
     * @var bool
     */
    protected $displayExceptions = false;

    /**
     * Default Exception Message
     *
     * @var string
     */
    protected $defaultExceptionMessage = 'Oh no. Something went wrong, but we have been notified. If you are testing, tell us your eventID: %s';

    /**
     * Name of exception template
     *
     * @var string
     */
    protected $exceptionTemplate = 'error';

    /**
     * {@inheritDoc}
     */
    public function attach(
        EventManagerInterface $events,
        $priority = 1
    ) {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'prepareExceptionViewModel']);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'prepareExceptionViewModel']);
    }

    /**
     * Flag: display exceptions in error pages?
     *
     * @param bool $displayExceptions
     *
     * @return ExceptionStrategy
     */
    public function setDisplayExceptions(bool $displayExceptions): ExceptionStrategy
    {
        $this->displayExceptions = $displayExceptions;
        return $this;
    }

    /**
     * Set the default exception message
     *
     * @param string $defaultExceptionMessage
     *
     * @return self
     */
    public function setDefaultExceptionMessage(string $defaultExceptionMessage): self
    {
        $this->defaultExceptionMessage = $defaultExceptionMessage;
        return $this;
    }

    /**
     * Create an exception view model, and set the HTTP status code
     *
     * @param MvcEvent $e
     *
     * @return void
     */
    public function prepareExceptionViewModel(MvcEvent $e)
    {
        // Do nothing if no error in the event
        $error = $e->getError();
        if (empty($error)) {
            return;
        }

        // Do nothing if the result is a response object
        $result = $e->getResult();
        if ($result instanceof Response) {
            return;
        }

        // Proceed to showing an error page with or without exception
        switch ($error) {
            case Application::ERROR_CONTROLLER_NOT_FOUND:
            case Application::ERROR_CONTROLLER_INVALID:
            case Application::ERROR_ROUTER_NO_MATCH:
                // Specifically not handling these
                return;

            case Application::ERROR_EXCEPTION:
            default:
                // check if there really is an exception
                // ZF also throws normal errors, for example: error-route-unauthorized
                // if there is no exception we have nothing to log
                if ($e->getParam('exception') == null) {
                    return;
                }

                // Log exception to sentry by triggering an exception event
                $eventID = $e->getApplication()->getEventManager()->trigger('logException', $this, ['exception' => $e->getParam('exception')]);

                $model = new ViewModel(
                    [
                        'message' => sprintf($this->defaultExceptionMessage, $eventID->last()),
                        'exception' => $e->getParam('exception'),
                        'display_exceptions' => $this->displayExceptions(),
                    ]
                );
                $model->setTemplate($this->getExceptionTemplate());
                $e->setResult($model);

                /** @var HttpResponse $response */
                $response = $e->getResponse();
                if (! $response) {
                    $response = new HttpResponse();
                    $response->setStatusCode(500);
                    $e->setResponse($response);
                } else {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode === 200) {
                        $response->setStatusCode(500);
                    }
                }

                break;
        }
    }

    /**
     * Should we display exceptions in error pages?
     *
     * @return bool
     */
    public function displayExceptions(): bool
    {
        return $this->displayExceptions;
    }

    /**
     * Retrieve the exception template
     *
     * @return string
     */
    public function getExceptionTemplate(): string
    {
        return $this->exceptionTemplate;
    }

    /**
     * Set the exception template
     *
     * @param string $exceptionTemplate
     *
     * @return ExceptionStrategy
     */
    public function setExceptionTemplate(string $exceptionTemplate): ExceptionStrategy
    {
        $this->exceptionTemplate = $exceptionTemplate;
        return $this;
    }
}
