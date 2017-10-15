<?php

namespace Http\Adapter\Artax\Test;

use Amp\Artax;
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
}
