<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\CourseType;
use App\Tests\AbstractTest;
use App\Tests\Utils;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;

class CourseControllerTest extends AbstractTest
{
    public function testGetCourses(): void
    {
        $client = static::getClient();

        $client->request('GET', '/api/v1/courses');
        $this->assertResponseOk();

        $courses = Utils::parseJsonResponse($client);

        self::assertCount(5, $courses);
    }

    public function testGetCourse(): void
    {
        $client = static::getClient();

        $client->request('GET', '/api/v1/courses/nympydata');
        $this->assertResponseOk();

        $course = Utils::parseJsonResponse($client);

        self::assertEquals('nympydata', $course['code']);
        self::assertEquals('free', $course['type']);
        self::assertArrayNotHasKey('price', $course);
    }

    public function testPayCourse(): void
    {
        $client = static::getClient();

        $em = self::getEntityManager();
        $userRepository = $em->getRepository(User::class);
        $transactionRepository = $em->getRepository(Transaction::class);

        $transactionsCount = $transactionRepository->count(['type' => 0]);

        // Неавторизован
        $client->request('POST', '/api/v1/courses/figmadesign/pay');
        $this->assertResponseCode(401);

        $utils = new Utils();
        // Пользователь, у которого есть средства
        $utils->login($client, Utils::EMAIL, Utils::PASSWORD);

        // Курс не найден
        $client->request('POST', '/api/v1/courses/jkhkjhjhghjghhj/pay');
        $this->assertResponseCode(404);

        // Успешная оплата курса
        $client->request('POST', '/api/v1/courses/richcourse/pay');
        $this->assertResponseCode(200);

        $payInfo = Utils::parseJsonResponse($client);

        self::assertTrue($payInfo['success']);
        self::assertEquals('rent', $payInfo['course_type']);
        self::assertTrue(
            abs(
                (new DateTime())->add(new DateInterval('P7D'))->getTimestamp() -
                // 2019-05-20T13:45:11+00:00
                DateTime::createFromFormat(DateTimeInterface::ATOM, $payInfo['expires_at'])->getTimestamp()
            ) < 10
        );

        $utils->logout($client);

        // Пользователь без средств
        $utils->login($client, Utils::ADMIN_EMAIL, Utils::ADMIN_PASSWORD);

        // Оплата не нужна
        $client->request('POST', '/api/v1/courses/nympydata/pay');
        $this->assertResponseCode(200);

        $payInfo = Utils::parseJsonResponse($client);
        self::assertTrue($payInfo['success']);
        self::assertEquals('free', $payInfo['course_type']);

        // Проверка снятия средств за покупку 2-х курсов
        $user = $userRepository->findOneBy(['email' => Utils::EMAIL]);
        self::assertEquals(
            950.45,
            $user->getBalance()
        );

        // Добавилось 1 транзакция
        self::assertEquals($transactionsCount + 1, $transactionRepository->count(['type' => 0]));
    }


    public function testAddCourse(): void
    {
        $client = static::getClient();
        $utils = new Utils();
        $utils->login($client, Utils::ADMIN_EMAIL, Utils::ADMIN_PASSWORD);

        $response = $this->subtestPost($client, '/api/v1/courses', 200, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "type" => CourseType::NAMES[CourseType::FREE]
        ]);
        self::assertTrue($response['success']);
        $response = $this->subtestPost($client, '/api/v1/courses', 200, [
            "code" => "new-course-2",
            "name" => "Новый курс",
            "type" => CourseType::NAMES[CourseType::RENT],
            "price" => 200
        ]);
        self::assertTrue($response['success']);
        $response = $this->subtestPost($client, '/api/v1/courses', 200, [
            "code" => "new-course-3",
            "name" => "Новый курс",
            "type" => CourseType::NAMES[CourseType::BUY],
            "price" => 300.32
        ]);
        self::assertTrue($response['success']);

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        self::assertEquals(3, $courseRepository->count(['name' => 'Новый курс']));
    }

    public function testAddCourseFailed(): void
    {
        $client = static::getClient();

        // Без прав админа нет доступа
        $client->request('POST', '/api/v1/courses');
        $this->assertResponseCode(401);


        $utils = new Utils();
        $utils->login($client, Utils::EMAIL, Utils::PASSWORD);
        $client->request('POST', '/api/v1/courses');
        $this->assertResponseCode(403);

        $utils->login($client, Utils::ADMIN_EMAIL, Utils::ADMIN_PASSWORD);

        // Нет кода
        $this->subtestPost($client, '/api/v1/courses', 400, [
            "name" => "Новый курс",
            "type" => CourseType::RENT_NAME,
            "price" => "200"
        ]);
        // Нет типа
        $this->subtestPost($client, '/api/v1/courses', 400, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "price" => "200"
        ]);
        // Неверный тип
        $this->subtestPost($client, '/api/v1/courses', 400, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "type" => "sdgdg",
            "price" => "200"
        ]);
        // Нет цены
        $this->subtestPost($client, '/api/v1/courses', 400, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "type" => CourseType::RENT_NAME,
        ]);
        $this->subtestPost($client, '/api/v1/courses', 400, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "type" => CourseType::BUY_NAME,
        ]);
        // Курс с таким кодом уже существует
        $this->subtestPost($client, '/api/v1/courses', 409, [
            "code" => "nympydata",
            "name" => "Новый курс",
            "type" => "buy",
            "price" => "400"
        ]);
    }

    public function testEditCourse(): void
    {
        $client = static::getClient();
        $utils = new Utils();
        $utils->login($client, Utils::ADMIN_EMAIL, Utils::ADMIN_PASSWORD);

        $response = $this->subtestPost($client, '/api/v1/courses/nympydata', 200, [
            "code" => "updated-course-1",
            "name" => "курс",
            "type" => CourseType::BUY_NAME,
            "price" => 555.55
        ]);
        self::assertTrue($response['success']);

        $client->request('GET', '/api/v1/courses/nympydata');
        $this->assertResponseNotFound();

        $client->request('GET', '/api/v1/courses/updated-course-1');
        $this->assertResponseOk();

        $course = Utils::parseJsonResponse($client);

        self::assertSame('updated-course-1', $course['code']);
        self::assertSame(CourseType::BUY_NAME, $course['type']);
        self::assertSame(555.55, $course['price']);
    }

    public function testEditCourseFailed(): void
    {
        //    $this->assertResponseCode(403);
        $client = static::getClient();

        // Без прав админа нет доступа
        $client->request('POST', '/api/v1/courses/nympydata');
        $this->assertResponseCode(401);
        $utils = new Utils();

        $utils->login($client, Utils::EMAIL, Utils::PASSWORD);
        $client->request('POST', '/api/v1/courses/interactive-sql-trainer');
        $this->assertResponseCode(403);

        $utils->login($client, Utils::ADMIN_EMAIL, Utils::ADMIN_PASSWORD);

        $this->subtestPost($client, '/api/v1/courses/asfagsfsg', 404);
    }

    private function subtestPost(AbstractBrowser $client, $uri, $responseCode, $requestBody = [])
    {
        $requestBody = json_encode($requestBody, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $client->request('POST', $uri, [], [], [], $requestBody);
        $this->assertResponseCode($responseCode);
        return Utils::parseJsonResponse($client);
    }
}