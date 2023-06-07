<?php

namespace App\Tests\Controller;

use App\Tests\AbstractTest;
use App\Tests\Utils;
use Symfony\Component\BrowserKit\AbstractBrowser;

class TransactionControllerTest extends AbstractTest
{
    public function testGetTransactions(): void
    {
        $client = self::getClient(false, [], [
            'HTTP_ACCEPT' => 'application/json'
        ]);

        $client->request('GET', '/api/v1/transactions');
        $this->assertResponseCode(401);

        $utils = new Utils();

        $utils->login($client, Utils::EMAIL, Utils::PASSWORD);

        $this->subtestGetTransactions($client, 5, []);
        $this->subtestGetTransactions($client, 1, [
            'filter' => [
                'type' => 'deposit'
            ]
        ]);
        $this->subtestGetTransactions($client, 4, [
            'filter' => [
                'type' => 'payment'
            ]
        ]);
        $this->subtestGetTransactions($client, 4, [
            'filter' => [
                'type' => 'payment',
                'skip_expired' => false
            ]
        ]);
        $this->subtestGetTransactions($client, 3, [
            'filter' => [
                'type' => 'payment',
                'skip_expired' => true
            ]
        ]);

        $client->request('POST', '/api/v1/courses/other/pay');
        $this->assertResponseCode(200);

        $this->subtestGetTransactions($client, 4, [
            'filter' => [
                'type' => 'payment',
                'skip_expired' => true
            ]
        ]);
        $this->subtestGetTransactions($client, 1, [
            'filter' => [
                'type' => 'payment',
                'skip_expired' => true,
                'course_code' => 'other'
            ]
        ]);
        $this->subtestGetTransactions($client, 1, [
            'filter' => [
                'type' => 'payment',
                'course_code' => 'other'
            ]
        ]);
        $this->subtestGetTransactions($client, 0, [
            'filter' => [
                'course_code' => 'some-code'
            ]
        ]);
    }

    private function subtestGetTransactions(AbstractBrowser $client, int $expectedCount, array $queryFilter)
    {
        $client->request('GET', '/api/v1/transactions', $queryFilter);
        $this->assertResponseCode(200);
        $transactions = Utils::parseJsonResponse($client);
        self::assertCount($expectedCount, $transactions);
        return $transactions;
    }
}