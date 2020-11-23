<?php

namespace Http\Adapter\Artax;

use Amp\CancellationTokenSource;
use Amp\Http\Client\HttpClient as AmpHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Promise;
use Http\Client\Exception\RequestException;
use Http\Client\Exception\TransferException;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Message\ResponseFactory;
use Http\Message\StreamFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function Amp\call;

class Client implements HttpClient, HttpAsyncClient
{
    private $client;

    private $responseFactory;

    /**
     * @param AmpHttpClient $client          HTTP client implementation.
     * @param ResponseFactory        $responseFactory Response factory to use or `null` to attempt auto-discovery.
     * @param StreamFactory          $streamFactory   This parameter will be ignored and removed in the next major version.
     */
    public function __construct(
        AmpHttpClient $client = null,
        ResponseFactory $responseFactory = null,
        StreamFactory $streamFactory = null
    ) {
        $this->client = $client ?? HttpClientBuilder::buildDefault();
        $this->responseFactory = $responseFactory ?? Psr17FactoryDiscovery::findResponseFactory();

        if (null === $streamFactory || 3 === \func_num_args()) {
            @\trigger_error('The $streamFactory parameter is deprecated and ignored.', \E_USER_DEPRECATED);
        }
    }

    /** {@inheritdoc} */
    public function sendRequest(RequestInterface $request) : ResponseInterface
    {
        return $this->doRequest($request)->wait();
    }

    /** {@inheritdoc} */
    public function sendAsyncRequest(RequestInterface $request)
    {
        return $this->doRequest($request, false);
    }

    protected function doRequest(RequestInterface $request, $useInternalStream = true): Promise
    {
        return new Internal\Promise(
            call(function () use ($request, $useInternalStream) {
                $cancellationTokenSource = new CancellationTokenSource();

                $req = new Request($request->getUri(), $request->getMethod(), (string) $request->getBody());
//                $req = $req->withProtocolVersions([$request->getProtocolVersion()]);
                foreach ($request->getHeaders() as $headerName => $headerValue) {
                    $req->addHeader($headerName, $headerValue);
                }

                try {
                    $resp = yield $this->client->request($req, $cancellationTokenSource->getToken());
                } catch (HttpException $e) {
                    throw new RequestException($e->getMessage(), $request, $e);
                } catch (\Throwable $e) {
                    throw new TransferException($e->getMessage(), 0, $e);
                }

                if ($useInternalStream) {
                    $body = new Internal\ResponseStream($resp->getBody()->getInputStream(), $cancellationTokenSource);
                } else {
                    $body = yield $resp->getBody();
                }

                return $this->responseFactory->createResponse(
                    $resp->getStatus(),
                    $resp->getReason(),
                    $resp->getHeaders(),
                    $body,
                    $resp->getProtocolVersion()
                );
            })
        );
    }
}
