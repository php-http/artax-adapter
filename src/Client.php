<?php

namespace Http\Adapter\Artax;

use Amp\Artax;
use Amp\CancellationTokenSource;
use Amp\Promise;
use Http\Client\Exception\RequestException;
use Http\Client\Exception\TransferException;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\ResponseFactory;
use Http\Message\StreamFactory;
use Psr\Http\Message\RequestInterface;
use function Amp\call;

class Client implements HttpClient, HttpAsyncClient
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
        return $this->doRequest($request)->wait();
    }

    /** {@inheritdoc} */
    public function sendAsyncRequest(RequestInterface $request)
    {
        return $this->doRequest($request, true);
    }

    protected function doRequest(RequestInterface $request, $async = false): Promise
    {
        return new Internal\Promise(
            call(function () use ($request, $async) {
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
                } catch (\Throwable $e) {
                    throw new TransferException($e->getMessage(), 0, $e);
                }

                $response = $this->responseFactory->createResponse(
                    $resp->getStatus(),
                    $resp->getReason(),
                    $resp->getHeaders(),
                    new Internal\ResponseStream($resp->getBody()->getInputStream(), $cancellationTokenSource, $async),
                    $resp->getProtocolVersion()
                );

                return $response;
            })
        );
    }
}
