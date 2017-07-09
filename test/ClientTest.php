<?php

namespace Kelunik\Http\Adapter\Artax\Test;

use Amp\Artax;
use Http\Client\HttpClient;
use Http\Client\Tests\HttpClientTest;
use Kelunik\Http\Adapter\Artax\Client;

class ClientTest extends HttpClientTest {
    /** @return HttpClient */
    protected function createHttpAdapter() {
        $client = new Artax\DefaultClient;
        $client->setOption(Artax\Client::OP_TRANSFER_TIMEOUT, 1000);

        return new Client($client);
    }

    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    public function testSendRequest($method, $uri, array $headers, $body) {
        if ($method === "TRACE") {
            $this->markTestSkipped("Currently skipped, because Artax refuses to send bodies for TRACE requests");
        }

        parent::testSendRequest($method, $uri, $headers, $body);
    }
}