<?php

namespace App\Tests\Controllers;

use App\Entity\User;
use App\Tests\AbstractTest;
use App\DataFixtures\AppFixtures;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserControllerTest extends AbstractTest
{
    protected function getFixtures(): array
    {
        return [
            new AppFixtures(
                $this->getContainer()->get(UserPasswordHasherInterface::class),
            )
        ];
    }

    public function testRegisterThenAuthSuccess(): void
    {
        $client = static::getClient();

        $email = "newUser@example.com";
        $password = "newPassword";

        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_CREATED);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue(isset($responseData['token']));

        $userRepository = self::getEntityManager()->getRepository(User::class);
        self::assertEquals(1, $userRepository->count(['email' => $email]));

        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_OK);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue(isset($responseData['token']));
    }


    public function testRegisterFailed(): void
    {
        $client = static::getClient();

        $email = "newUser@example.com";
        $password = "newPassword";

        // Нет username
        $client->jsonRequest('POST', '/api/v1/register', [
            "password" => "string"
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);

        // Нет password
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => "string",
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);

        // Пароль меньше 6 символов
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => $email,
            "password" => "12345"
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);

        // Неверный email
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => "угабуга",
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
    }

    public function testAuthFailed(): void
    {
        // регистрируем пользователя 
        $client = static::getClient();

        $email = "newUser@example.com";
        $password = "newPassword";

        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_CREATED);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue(isset($responseData['token']));

        $userRepository = self::getEntityManager()->getRepository(User::class);
        self::assertEquals(1, $userRepository->count(['email' => $email]));

        // Попытки авторизоваться под аккаунтом нового пользователя
        // Нет username
        $client->jsonRequest('POST', '/api/v1/auth', [
            "password" => "string"
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        // Нет password
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => "string",
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);

        // Неверный email
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => "угабуга",
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);

        // Неверный password
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => $email,
            "password" => "111111111111"
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetCurrentUserAdmin(): void
    {
        $client = static::getClient();

        $email = "admin@example.com";
        $password = "password";

        // авторизация от лица админа
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_OK);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $token = $responseData['token'];
        $this->assertTrue(isset($token));

        // попытка получить данные без токена
        $client->request('GET', '/api/v1/users/current', [], [], []);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);

        // успешное получение данных об авторизованном пользователе
        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertResponseCode(Response::HTTP_OK);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals($email, $responseData['username']);
        self::assertEquals('ROLE_SUPER_ADMIN', $responseData['roles'][0]);
        self::assertEquals(1000, $responseData['balance']);
    }
}
