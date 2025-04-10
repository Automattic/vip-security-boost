<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class XmlRpcTest extends TestCase
{
    /**
     * Test that accessing XML-RPC endpoint returns 403 Forbidden when disabled.
     *
     * @return void
     */
    public function testXmlRpcReturns403WhenDisabled(): void
    {
        $url = 'http://vip-security-boost.vipdev.lndo.site/xmlrpc.php';

        $xmlPayload = <<<XML
        <?xml version="1.0"?>
            <methodCall>
                <methodName>system.listMethods</methodName>
                <params></params>
            </methodCall>
        XML;

        $headers = [
            'X-Integration-Test' => 'true',
            'X-Integration-Test-Configs' => json_encode(
                array(
                    'enabled_modules' => [ 'xml-rpc' ],
                    'module_configs' => array(
                        'xml-rpc' => array(
                            'mode' => 'DISABLE',
                        ),
                    ),
                )
            ),
        ];

        $client = new Client([
            'headers' => $headers,
            'http_errors' => false,
            'timeout' => 10.0,
        ]);
        $response = $client->request('POST', $url, [
            'body' => $xmlPayload,
        ]);

        $this->assertEquals(
            403,
            $response->getStatusCode(),
            "Expected HTTP status code 403 Forbidden, but received {$response->getStatusCode()}."
        );
    }
}