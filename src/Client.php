<?php

namespace Kelunik\Http\Adapter\Artax;

use Amp\Artax;
use Amp\Promise;
use Http\Client\Exception\RequestException;
use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Message\ResponseFactory;
use Http\Message\StreamFactory;
use Psr\Http\Message\RequestInterface;
use function Amp\call;

class Client implements HttpClient {
    private $client;
    private $responseFactory;
    private $streamFactory;

    public function __construct(Artax\Client $client = null, ResponseFactory $responseFactory = null, StreamFactory $streamFactory = null) {
        $this->client = $client ?? new Artax\DefaultClient;
        $this->responseFactory = $responseFactory ?? MessageFactoryDiscovery::find();
        $this->streamFactory = $streamFactory ?? StreamFactoryDiscovery::find();
    }

    /** @inheritdoc */
    public function sendRequest(RequestInterface $request) {
        return Promise\wait(call(function () use ($request) {
            /** @var Artax\Request $req */
            $req = new Artax\Request($request->getUri(), $request->getMethod());
            $req = $req->withProtocolVersions([$request->getProtocolVersion()]);
            $req = $req->withHeaders($request->getHeaders());
            $req = $req->withBody((string) $request->getBody());

            try {
                /** @var Artax\Response $resp */
                $resp = yield $this->client->request($req, [
                    Artax\Client::OP_MAX_REDIRECTS => 0,
                ]);
            } catch (Artax\HttpException $e) {
                throw new RequestException($e->getMessage(), $request, $e);
            }

            $respBody = $resp->getBody();
            $bodyStream = $this->streamFactory->createStream();

            while (null !== $chunk = yield $respBody->read()) {
                $bodyStream->write($chunk);
            }

            $bodyStream->rewind();

            return $this->responseFactory->createResponse(
                $resp->getStatus(),
                $resp->getReason(),
                $resp->getHeaders(),
                $bodyStream,
                $resp->getProtocolVersion()
            );
        }));
    }
}