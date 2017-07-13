<?php

namespace Http\Adapter\Artax;

use Amp\Artax;
use Amp\CancellationTokenSource;
use Amp\Promise;
use Http\Adapter\Artax\Internal\ResponseStream;
use Http\Client\Exception\RequestException;
use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\ResponseFactory;
use Http\Message\StreamFactory;
use Psr\Http\Message\RequestInterface;
use function Amp\call;

class Client implements HttpClient
{
    private $client;
    private $responseFactory;

    /**
     * @param Artax\Client    $client          HTTP client implementation.
     * @param ResponseFactory $responseFactory Response factory to use or `null` to attempt auto-discovery.
     * @param StreamFactory   $streamFactory   This parameter will be ignored and removed in the next major version.
     */
    public function __construct(
        Artax\Client $client = null,
        ResponseFactory $responseFactory = null,
        StreamFactory $streamFactory = null
    ) {
        $this->client = $client ?? new Artax\DefaultClient();
        $this->responseFactory = $responseFactory ?? MessageFactoryDiscovery::find();

        if ($streamFactory !== null || \func_num_args() === 3) {
            @\trigger_error('The $streamFactory parameter is deprecated and ignored.', \E_USER_DEPRECATED);
        }
    }

    /** {@inheritdoc} */
    public function sendRequest(RequestInterface $request)
    {
        return Promise\wait(call(function () use ($request) {
            $cancellationTokenSource = new CancellationTokenSource();

            /** @var Artax\Request $req */
            $req = new Artax\Request($request->getUri(), $request->getMethod());
            $req = $req->withProtocolVersions([$request->getProtocolVersion()]);
            $req = $req->withHeaders($request->getHeaders());
            $req = $req->withBody((string) $request->getBody());

            try {
                /** @var Artax\Response $resp */
                $resp = yield $this->client->request($req, [
                    Artax\Client::OP_MAX_REDIRECTS => 0,
                ], $cancellationTokenSource->getToken());
            } catch (Artax\HttpException $e) {
                throw new RequestException($e->getMessage(), $request, $e);
            }

            return $this->responseFactory->createResponse(
                $resp->getStatus(),
                $resp->getReason(),
                $resp->getHeaders(),
                new ResponseStream($resp->getBody()->getInputStream(), $cancellationTokenSource),
                $resp->getProtocolVersion()
            );
        }));
    }
}
