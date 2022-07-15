<?php

namespace App\Controller;

use App\Entity\VirtualUser;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\VirtualUserRepository;
use App\Repository\DomainNameRepository;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use JMS\Serializer\SerializerInterface;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/api")
 * @IsGranted("ROLE_USER", message="You have to log in to manage Virtual User")
 */
class VirtualUserController extends AbstractFOSRestController
{
    /**
     * @Rest\Get(
     *      "/virtual-users"),
     *      name="virtual_user_list"
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
     *      description="Return list of virtual users",
     *      @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=VirtualUser::class))
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
     * @OA\Tag(name="VirtualUser")   
     * @Rest\View(StatusCode=200)
     * @param VirtualUserRepository $virtualUserRepository
     * @param SerializerInterface $serializer
     * @param TagAwareCacheInterface $cachePool
     * @param $page
     * @param $limit
     * @return JsonResponse
     */
    public function list(
        VirtualUserRepository $virtualUserRepository,
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool,
        $page = 1,
        $limit = 20
    ): JsonResponse {
        $idCache = "listVirtualUsers" . $page . "-" . $limit;
        $virtualUsers = $cachePool->get(
            $idCache,
            function (ItemInterface $item) use ($virtualUserRepository, $serializer, $page, $limit) {
                $item->tag("virtualUserCache");
                $data = $virtualUserRepository->findAllWithPagination($page, $limit);
                return $serializer->serialize($data, 'json');
        });

        return new JsonResponse($virtualUsers, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * @Rest\Get(
     *      "/virtual-users/{id}",
     *      name="virtual_user_show",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return detail of virtual user",
     *      @OA\Schema(type=VirtualUser::class)
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual user",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualUser") 
     * @Rest\View(StatusCode=200)
     * @param VirtualUser $virtualUser
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    public function show(VirtualUser $virtualUser, SerializerInterface $serializer) : JsonResponse
    {
        $data = $serializer->serialize($virtualUser, 'json');
        return new JsonResponse($data, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * @Rest\Post(
     *      path="/virtual-users",
     *      name="virtual_user_new"
     * )
     * @OA\Response(
     *      response=201,
     *      description="The created virtual user"
     * )
     * @OA\RequestBody(@Model(type=VirtualUser::class))
     * @OA\Tag(name="VirtualUser")
     * @Rest\View(StatusCode = 201)
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param TagAwareCacheInterface $cachePool
     * @param ValidatorInterface $validator
     */
    public function new(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        TagAwareCacheInterface $cachePool,
        ValidatorInterface $validator,
        DomainNameRepository $domainNameRepository
        ): JsonResponse {
            $cachePool->invalidateTags(["virtualUserCache"]);
            $virtualUser = $serializer->deserialize($request->getContent(), VirtualUser::class, 'json');
                        
            $content = $request->toArray();
            // just to validate the length and format of the password because it's excluded
            if (array_key_exists("password", $content)) {
                $virtualUser->setPassword($content["password"]); 
            }
            $errors = $validator->validate($virtualUser);
            if (count($errors) || ! $content['idDomainName']) {
                return new JsonResponse(
                    $serializer->serialize($errors, 'json'),
                    JsonResponse::HTTP_BAD_REQUEST,
                    [],
                    true
                );
            }
            
            if (array_key_exists("password", $content)) {
                $virtualUser->setPassword(password_hash($content["password"], PASSWORD_DEFAULT));
            }
            $domainName = $domainNameRepository->find($content['idDomainName']);
            $virtualUser->setDomainName($domainName);
            $virtualUser->setMaildir($domainName->getName() . "/" . explode("@", $virtualUser->getEmail())[0] . "/");

            // force email to use correct domain name in case of error    
            $email = preg_replace('#^(.+)@(.+)$#', '$1@' . $domainName->getName(), $virtualUser->getEmail());
            $virtualUser->setEmail($email);
            
            $em->persist($virtualUser);
            $em->flush();
            
            $jsonVirtualUser = $serializer->serialize($virtualUser, 'json');
            $location = $urlGenerator->generate(
                'virtual_user_show',
                ['id' => $virtualUser->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ); 
            
            return new JsonResponse($jsonVirtualUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * @Rest\Put(
     *      path="/virtual-users/{id}",
     *      name="virtual_user_edit",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Edit a virtual user"
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual user",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualUser")
     * @Rest\View(StatusCode = 204)
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @param ValidatorInterface $validator
     */
    public function edit(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool,
        ValidatorInterface $validator
        ): JsonResponse { 
            $cachePool->invalidateTags(["virtualUserCache"]);
            $virtualUser = $serializer->deserialize($request->getContent(), VirtualUser::class, 'json');

            $content = $request->toArray();
            // just to validate the length and format of the password because it's excluded
            if (array_key_exists("password", $content)) {
                $virtualUser->setPassword($content["password"]);
            }
            $errors = $validator->validate($virtualUser);
            if (count($errors)) {
                return new JsonResponse(
                    $serializer->serialize($errors, 'json'),
                    JsonResponse::HTTP_BAD_REQUEST,
                    [],
                    true
                );
            }
            // update the password if it's provided
            if (array_key_exists("password", $content)) {
                $virtualUser->setPassword(password_hash($content["password"], PASSWORD_DEFAULT));
            }
                        
            $em->persist($virtualUser);
            $em->flush();

            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

    /**
     * @Rest\Delete(
     *      "/virtual-users/{id}",
     *      name="virtual_user_delete",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Delete a virtual user"
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual user",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualUser")
     * @Rest\View(StatusCode=204)
     * @param VirtualUser $virtualUser
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     */
    public function delete(
        VirtualUser $virtualUser,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
            $cachePool->invalidateTags(["virtualUserCache"]);
            $em->remove($virtualUser);
            $em->flush();
            
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
