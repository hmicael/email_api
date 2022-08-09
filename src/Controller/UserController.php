<?php

namespace App\Controller;

use App\Entity\User;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\UserRepository;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/api")
 */
class UserController extends AbstractFOSRestController
{

    /**
     * @Rest\Get(
     *      "/users",
     *      name="user_list"
     * )
     * @QueryParam(
     *      name="page",
     *      requirements="\d*",
     *      default=1,
     *      description="Requested page number"
     * )
     * @QueryParam(
     *      name="limit",
     *      requirements="\d*",
     *      default=20,
     *      description="Limit of result"
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return list of users",
     *      @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=User::class))
     *      )
     * )
     * @OA\Parameter(
     *      name="page",
     *      in="query",
     *      description="Requested page number",
     *      @OA\Schema(type="int")
     * )
     * @OA\Parameter(
     *      name="limit",
     *      in="query",
     *      description="Limit of result",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="User")   
     * @Rest\View(StatusCode=200)
     * @IsGranted("ROLE_ADMIN", message="Only user can manage user")
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param TagAwareCacheInterface $cachePool
     * @param $page
     * @param $limit
     * @return JsonResponse
     */
    public function list(
        UserRepository $userRepository,
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool,
        $page = 1,
        $limit = 20
    ): JsonResponse {
        $idCache = "listUsers" . $page . "-" . $limit;
        $users = $cachePool->get(
            $idCache,
            function (ItemInterface $item) use ($userRepository, $serializer, $page, $limit) {
                $item->tag("usersCache");
                $data = $userRepository->findAllWithPagination($page, $limit);
                return $serializer->serialize(
                    $data,
                    'json',
                    SerializationContext::create()->setGroups(array('list'))
                );
        });

        return new JsonResponse($users, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /** 
     * @Rest\Post(
     *      "/users/search",
     *      name="user_search"
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return list of user according to research",
     *      @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=User::class))
     *      )
     * )
     * @OA\Tag(name="User")   
     * @Rest\View(StatusCode=200)
     * @IsGranted("ROLE_ADMIN", message="Only user can manage user")
     * @param Request $request
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
    */
    public function search(
        Request $request,
        UserRepository $userRepository,
        SerializerInterface $serializer
    ): JsonResponse {
        $content = $request->toArray();
        $keyword = htmlspecialchars($content["keyword"]) ?? "";
        $data = $userRepository->search($keyword);

        return new JsonResponse(
            $serializer->serialize(
                $data,
                'json',
                SerializationContext::create()->setGroups(array('list'))
            ),
            Response::HTTP_OK,
            ['accept' => 'json'],
            true
        );
    }

    /**
     * @Rest\Get(
     *      "/users/{id}",
     *      name="user_show",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return detail of user",
     *      @OA\Schema(type=User::class)
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the user",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="User") 
     * @Rest\View(StatusCode=200)
     * @IsGranted("ROLE_ADMIN", message="Only user can manage user")
     * @param User $user
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    public function show(User $user, SerializerInterface $serializer) : JsonResponse
    {
        $data = $serializer->serialize(
            $user,
            'json',
            SerializationContext::create()->setGroups(array('list'))
        );
        return new JsonResponse($data, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * @Rest\Post(
     *      path="/users",
     *      name="user_new"
     * )
     * @OA\Response(
     *      response=201,
     *      description="The created user"
     * )
     * @OA\RequestBody(@Model(type=User::class))
     * @OA\Tag(name="User")
     * @Rest\View(StatusCode = 201)
     * @ParamConverter("user", converter="fos_rest.request_body")
     * @IsGranted("ROLE_ADMIN", message="Only admin can create user")
     * @IsGranted("ROLE_ADMIN", message="Only user can manage user")
     * @param User $user
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ConstraintViolationList $violations
     * @param TagAwareCacheInterface $cachePool
     * @param UserPasswordHasherInterface $passwordHasher
     */
    public function new(
        User $user,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        ConstraintViolationList $violations,
        TagAwareCacheInterface $cachePool,
        UserPasswordHasherInterface $passwordHasher
        ): JsonResponse {
            $cachePool->invalidateTags(["usersCache"]);
            if (count($violations)) {
                return new JsonResponse(
                    $serializer->serialize($violations, 'json'),
                    JsonResponse::HTTP_BAD_REQUEST,
                    [],
                    true
                );
            }
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $user->getPassword()
            );
            $user->setPassword($hashedPassword);

            $em->persist($user);
            $em->flush();

            $jsonUser = $serializer->serialize(
                $user,
                'json',
                SerializationContext::create()->setGroups(array('list'))
            );
            $location = $urlGenerator->generate(
                'user_show',
                ['id' => $user->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ); 
            
            return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * @Rest\Put(
     *      path="/users/{id}",
     *      name="user_edit",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Edit a user"
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the user",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="User")
     * @Rest\View(StatusCode = 204)
     * @ParamConverter("user", converter="fos_rest.request_body")
     * @IsGranted("ROLE_ADMIN", message="Only admin can manage user")
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param User $user
     * @param ConstraintViolationList $violations
     * @param TagAwareCacheInterface $cachePool
     * @param UserPasswordHasherInterface $passwordHasher
     */
    public function edit(
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        User $user,
        ConstraintViolationList $violations,
        TagAwareCacheInterface $cachePool,
        UserPasswordHasherInterface $passwordHasher
        ): JsonResponse { 
            $cachePool->invalidateTags(["usersCache"]);
            if (count($violations)) {
                return new JsonResponse(
                    $serializer->serialize($violations, 'json'),
                    JsonResponse::HTTP_BAD_REQUEST,
                    [],
                    true
                );
            }

            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $user->getPassword()
            );
            $user->setPassword($hashedPassword);

            $em->persist($user);
            $em->flush();

            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

    /**
     * @Rest\Delete(
     *      "/users/{id}",
     *      name="user_delete",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Delete a user"
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the user",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="User")
     * @Rest\View(StatusCode=204)
     * @IsGranted("ROLE_ADMIN", message="Only user can manage user")
     * @param User $user
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     */
    public function delete(
        User $user,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
            $cachePool->invalidateTags(["usersCache"]);
            $em->remove($user);
            $em->flush();
            
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
