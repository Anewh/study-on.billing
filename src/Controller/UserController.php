<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
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
     * @OA\Response(
     *     response=400,
     *     description="Bad request",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          example="400"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Bad request"
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
     *     path="/api/v1/token/refresh",
     *     description="Get new valid JWT token",
     *     tags={"User"},
     * @OA\RequestBody(
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(
     *              property="refresh_token", 
     *              type="string"
     *          )
     *      )
     * ),
     * @OA\Response(
     *      response=200,
     *      description="JWT token",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(
     *              property="token", 
     *              type="string"
     *          ),
     *          @OA\Property(
     *              property="refresh_token", 
     *              type="string"
     *          )
     *       )
     *    )
     * )
     * Managed by lexik/jwt-authentication-bundle. Used for only OA doc
     */
    // #[Route('/v1/token/refresh', name: 'api_v1_refresh_token', methods: ['POST'])]
    // public function refreshToken()
    // {
    //     return new JsonResponse(status: Response::HTTP_OK);
    //     //dd();
    //     //throw new \RuntimeException();
    // }




    #[Route('/v1/token/refresh', name: 'api_refresh_token', methods: ['POST'])]
    public function refresh()
    {
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
     *     description="User with presented email already exist",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="error",
     *          type="string",
     *          example="User with presented email already exist",
     *        ),
     *     ),
     * )
     * @OA\Tag(name="User")
     */
    #[Route('/v1/register', name: 'api_v1_register', methods: ['POST'])]
    public function register(Request $request, RefreshTokenGeneratorInterface $refreshTokenGenerator, RefreshTokenManagerInterface $refreshTokenManager): JsonResponse
    {
        $userDto = $this->serializer->deserialize(
            $request->getContent(),
            UserDto::class,
            'json'
        );

        $errors = $this->validator->validate($userDto);
        if ($errors->count() > 0) {
            return new JsonResponse(
                //['errors' => (string)$errors],
                ['errors' => ['Bad request']],
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
        $refreshToken = $refreshTokenGenerator->createForUserWithTtl($user, (new \DateTime())->modify('+1 month')->getTimestamp());
        $refreshTokenManager->save($refreshToken);

        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
            'roles' => $user->getRoles(),
            'refresh_token' => $refreshToken->getRefreshToken()
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *     description="Get user data by JWT",
     *     summary="Get user data by JWT",
     *     tags={"User"}
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
    public function getCurrentUser(#[CurrentUser] ?User $user,  RefreshTokenGeneratorInterface $refreshTokenGenerator): JsonResponse
    {
        //$refreshToken = $refreshTokenGenerator->createForUserWithTtl($user, (new \DateTime())->modify('+1 month')->getTimestamp());
        //$this->tokenStorageInterface->setToken($refreshToken->getRefreshToken());
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