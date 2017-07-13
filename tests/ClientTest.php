<?php

namespace Http\Adapter\Artax\Test;

use Amp\Artax;
use Http\Adapter\Artax\Client;
use Http\Client\HttpClient;
use Http\Client\Tests\HttpClientTest;
use Http\Message\StreamFactory;

class ClientTest extends HttpClientTest
{
    /** @return HttpClient */
    protected function createHttpAdapter()
    {
        $client = new Artax\DefaultClient();
        $client->setOption(Artax\Client::OP_TRANSFER_TIMEOUT, 1000);

        return new Client($client);
    }

    public function testStreamFactoryDeprecation()
    {
        $invoked = false;

        try {
            \set_error_handler(function () use (&$invoked) {
                $invoked = true;
            }, \E_USER_DEPRECATED);

            new Client(null, null, null);
        } finally {
            \restore_error_handler();
        }

        $this->assertTrue($invoked);
    }
}
