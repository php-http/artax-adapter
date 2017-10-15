<?php

namespace Http\Adapter\Artax\Internal;

use Amp\Promise as AmpPromise;
use Http\Client\Exception;
use Http\Promise\Promise as HttpPromise;
use Psr\Http\Message\ResponseInterface;

/**
 * Promise adapter between artax and php-http, which allow to use the sendAsyncRequest and also the coroutine system by still respecting the Amp Promise.
 *
 * @internal
 */
class Promise implements HttpPromise, AmpPromise
{
    /** @var string */
    private $state = HttpPromise::PENDING;

    /** @var ResponseInterface */
    private $response;

    /** @var Exception */
    private $exception;

    /** @var AmpPromise */
    private $promise;

    /** @var callable|null */
    private $onFulfilled;

    /** @var callable|null */
    private $onRejected;

    /**
     * @param AmpPromise $promise Underlying amp promise which MUST resolve with a ResponseInterface or MUST fail with a Http\Client\Exception
     */
    public function __construct(AmpPromise $promise)
    {
        $this->promise = $promise;
        $this->promise->onResolve(function ($error, $result) {
            if ($error !== null) {
                $this->reject($error);
            } else {
                $this->resolve($result);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        $deferred = new \Amp\Deferred();
        $newPromise = new self($deferred->promise());

        $onFulfilled = $onFulfilled ?? function (ResponseInterface $response) {
            return $response;
        };

        $onRejected = $onRejected ?? function (Exception $exception) {
            throw $exception;
        };

        $this->onFulfilled = function (ResponseInterface $response) use ($onFulfilled, $deferred) {
            try {
                $deferred->resolve($onFulfilled($response));
            } catch (Exception $exception) {
                $deferred->fail($exception);
            }
        };

        $this->onRejected = function (Exception $exception) use ($onRejected, $deferred) {
            try {
                $deferred->resolve($onRejected($exception));
            } catch (Exception $exception) {
                $deferred->fail($exception);
            }
        };

        if ($this->state === HttpPromise::FULFILLED) {
            $this->resolve($this->response);
        }

        if ($this->state === HttpPromise::REJECTED) {
            $this->reject($this->exception);
        }

        return $newPromise;
    }

    /**
     * {@inheritdoc}
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * {@inheritdoc}
     */
    public function wait($unwrap = true)
    {
        try {
            AmpPromise\wait($this->promise);
        } catch (Exception $exception) {
        }

        if ($unwrap) {
            if (HttpPromise::REJECTED === $this->getState()) {
                throw $this->exception;
            }

            return $this->response;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onResolve(callable $onResolved)
    {
        $this->promise->onResolve($onResolved);
    }

    private function resolve(ResponseInterface $response)
    {
        $this->state = HttpPromise::FULFILLED;
        $this->response = $response;
        $onFulfilled = $this->onFulfilled;

        if (null !== $onFulfilled) {
            $onFulfilled($response);
        }
    }

    private function reject(Exception $exception)
    {
        $this->state = HttpPromise::REJECTED;
        $this->exception = $exception;
        $onRejected = $this->onRejected;

        if (null !== $onRejected) {
            $onRejected($exception);
        }
    }
}
