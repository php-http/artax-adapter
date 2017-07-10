<?php

namespace Http\Adapter\Artax\Test;

use Amp\Artax;
use Http\Client\HttpClient;
use Http\Client\Tests\HttpClientTest;
use Http\Adapter\Artax\Client;

class ClientTest extends HttpClientTest
{
    /** @return HttpClient */
    protected function createHttpAdapter()
    {
        $client = new Artax\DefaultClient();
        $client->setOption(Artax\Client::OP_TRANSFER_TIMEOUT, 1000);

        return new Client($client);
    }
}
