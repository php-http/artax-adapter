<?php

declare(ticks=1);

namespace Http\Adapter\Artax\Test;

use Amp\Artax;
use Amp\Loop;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Adapter\Artax\Client;
use Http\Client\HttpAsyncClient;
use Http\Client\Tests\HttpAsyncClientTest;

class AsyncClientTest extends HttpAsyncClientTest
{
    /** @return HttpAsyncClient */
    protected function createHttpAsyncClient()
    {
        $client = new Artax\DefaultClient();
        $client->setOption(Artax\Client::OP_TRANSFER_TIMEOUT, 1000);

        return new Client($client);
    }

    public function testPromise()
    {
        $content = $response = $exception = null;

        Loop::run(function () use (&$content, &$response, &$exception) {
            $client = $this->createHttpAsyncClient();
            $request = new Request('GET', 'https://httpbin.org/get');

            try {
                $response = yield $client->sendAsyncRequest($request);
                $content = yield $response->getBody()->getContentsAsync();
            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if (null !== $exception) {
            throw $exception;
        }

        self::assertInstanceOf(Response::class, $response);
        self::assertNotNull($content);
        self::assertNotEmpty($content);
    }

    /**
     * @param ResponseInterface $response
     * @param array             $options
     */
    protected function assertResponse($response, array $options = [])
    {
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);

        $options = array_merge($this->defaultOptions, $options);

        $this->assertSame($options['protocolVersion'], $response->getProtocolVersion());
        $this->assertSame($options['statusCode'], $response->getStatusCode());
        $this->assertSame($options['reasonPhrase'], $response->getReasonPhrase());

        $this->assertNotEmpty($response->getHeaders());

        foreach ($options['headers'] as $name => $value) {
            $this->assertTrue($response->hasHeader($name));
            $this->assertStringStartsWith($value, $response->getHeaderLine($name));
        }

        $content = \Amp\Promise\wait($response->getBody()->getContentsAsync());

        if ($options['body'] === null) {
            $this->assertEmpty($content);
        } else {
            $this->assertContains($options['body'], $content);
        }
    }
}
