<?php

declare(ticks=1);

namespace Http\Adapter\Artax\Test;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Loop;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Adapter\Artax\Client;
use Http\Client\HttpAsyncClient;
use Http\Client\Tests\HttpAsyncClientTest;

class AsyncClientTest extends HttpAsyncClientTest
{
    /** @return HttpAsyncClient */
    protected function createHttpAsyncClient() : HttpAsyncClient
    {
        $client = HttpClientBuilder::buildDefault();

        return new Client($client);
    }

    /**
     * Test using the async method in an existing loop.
     *
     * As an example a stream implementation of PSR7 using the \Amp\wait function
     * would fail in an existing loop. This test prevent regression of this behavior.
     */
    public function testInLoop()
    {
        Loop::run(function () use (&$content, &$response, &$exception) {
            $client = $this->createHttpAsyncClient();
            $request = new Request('GET', 'https://httpbin.org/get');

            try {
                $response = yield $client->sendAsyncRequest($request);
                $content = (string) $response->getBody();
            } catch (\Throwable $e) {
                $exception = $e;
            }

            Loop::stop();
        });

        if (null !== $exception) {
            throw $exception;
        }

        self::assertInstanceOf(Response::class, $response);
        self::assertNotNull($content);
        self::assertNotEmpty($content);
    }
}
