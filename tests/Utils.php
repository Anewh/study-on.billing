<?php

namespace App\Tests;

use App\Tests\AbstractTest;
use Symfony\Component\BrowserKit\AbstractBrowser;

class Utils extends AbstractTest
{
    public const EMAIL = 'user@example.com';
    public const PASSWORD = 'password';
    public const ADMIN_EMAIL = 'admin@example.com';
    public const ADMIN_PASSWORD = 'password';

    public static function parseJsonResponse(AbstractBrowser $client)
    {
        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function login(AbstractBrowser $client, string $email, string $password): array
    {
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(200);

        $responseData = self::parseJsonResponse($client);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $responseData['token']);

        return $responseData;
    }

    public function logout($client): void
    {
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
    }
}