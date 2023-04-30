<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Annotations as OA;

#[Route('/api')]
class UserController extends AbstractController
{
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private UserPasswordHasherInterface $passwordHasher;
    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorageInterface;
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorageInterface,
        JWTTokenManagerInterface $jwtManager,
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = SerializerBuilder::create()->build();
        $this->validator = $validator;
        $this->passwordHasher = $passwordHasher;
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->jwtManager = $jwtManager;
    }
    
    /**
     * @OA\Post(
     *     path="/api/v1/auth",
     *     summary="User Authentication",
     *     description="User Authentication and Getting a JWT Token"
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *          description="username (user email)",
     *          example="user@example.com",
     *        ),
     *        @OA\Property(
     *          property="password",
     *          type="string",
     *          description="password",
     *          example="password",
     *        ),
     *     )
     *)
     * @OA\Response(
     *     response=200,
     *     description="User Authentication and Getting a JWT Token",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Auth error. Invalid credentials",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          example="401"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Invalid credentials"
     *        ),
     *     )
     * )
     * @OA\Tag(name="User")
     */
    #[Route('/v1/auth', name: 'api_v1_auth', methods: ['POST'])]
    public function auth(Request $request): JsonResponse
    {
        return new JsonResponse(status: Response::HTTP_OK);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/register",
     *     summary="Registering a user and getting a JWT token",
     *     description="Registering a user and getting a JWT token"
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *          description="username (email)",
     *          example="user@example.com",
     *        ),
     *        @OA\Property(
     *          property="password",
     *          type="string",
     *          description="password",
     *          example="password",
     *        ),
     *     )
     *  )
     * )
     * @OA\Response(
     *     response=201,
     *     description="Successfully registered",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="roles",
     *          type="array",
     *          @OA\Items(
     *              type="string",
     *          ),
     *        ),
     *     ),
     * )
     * @OA\Response(
     *     response=400,
     *     description="Validation error",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="errors",
     *          type="array",
     *          @OA\Items(
     *              @OA\Property(
     *                  type="string",
     *                  property="property"
     *              )
     *          )
     *        )
     *     )
     * )
     * @OA\Response(
     *     response=409,
     *     description="Email already exists",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="error",
     *          type="string",
     *          example="Email already exists",
     *        ),
     *     ),
     * )
     * @OA\Tag(name="User")
     */
    #[Route('/v1/register', name: 'api_v1_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $userDto = $this->serializer->deserialize(
            $request->getContent(),
            UserDto::class,
            'json'
        );

        $errors = $this->validator->validate($userDto);
        if ($errors->count() > 0) {
            return new JsonResponse(
                ['errors' => (string)$errors],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userDto->getUsername()]);
        if ($user !== null) {
            return new JsonResponse(
                ['errors' => ['User with presented email already exist']],
                Response::HTTP_CONFLICT
            );
        }

        $user = User::getFromDto($userDto);

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $user->getPassword())
        );

        $this->entityManager->getRepository(User::class)->save($user, true);

        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *     description="Get user data by JWT",
     *     tags={"user"}
     * )
     * @OA\Response(
     *     response=200,
     *     description="The user data",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="roles",
     *          type="array",
     *          @OA\Items(
     *              type="string"
     *          )
     *        ),
     *        @OA\Property(
     *          property="balance",
     *          type="number",
     *          format="float"
     *        )
     *    )
     * )
     * @OA\Response(
     *     response=403,
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          example="403"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Unauthorized"
     *        ),
     *     )
     * )
     * @Security(name="Bearer")
     */
    #[Route('/v1/users/current', name: 'api_v1_user_get', methods: ['GET'])]
    public function getCurrentUser(#[CurrentUser] ?User $user): JsonResponse
    {
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());
        if (!$user) {
            return new JsonResponse(
                ['errors' => ['Required authorization token']],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new JsonResponse([
            'username' => $decodedJwtToken['username'],
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ], Response::HTTP_OK);
    }
}