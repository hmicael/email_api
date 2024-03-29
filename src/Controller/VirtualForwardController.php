<?php

namespace App\Controller;

use App\Entity\VirtualForward;
use App\Entity\VirtualUser;
use App\Repository\DomainNameRepository;
use App\Repository\VirtualForwardRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @Route("/api")
 * @IsGranted("ROLE_USER", message="Only user can manage Virtual Forward")
 */
class VirtualForwardController extends AbstractFOSRestController
{

    /**
     * List all Virutal Forwards
     * 
     * @Rest\Get(
     *      "/virtual-forwards",
     *      name="virtual_forward_list"
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
     *      description="Return list of virtual forwards",
     *      @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=VirtualForward::class, groups={"list"}))
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
     * @OA\Tag(name="VirtualForward")
     * @param VirtualForwardRepository $virtualForwardRepository
     * @param SerializerInterface $serializer
     * @param TagAwareCacheInterface $cachePool
     * @param $page
     * @param $limit
     * @return JsonResponse
     */
    public function list(
        VirtualForwardRepository $virtualForwardRepository,
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool,
        $page = 1,
        $limit = 20
    ): JsonResponse
    {
        $idCache = "listVirtualForwards" . $page . "-" . $limit;
        $virtualForwards = $cachePool->get(
            $idCache,
            function (ItemInterface $item) use ($virtualForwardRepository, $serializer, $page, $limit) {
                $item->tag("virtualForwardsCache");
                $data = $virtualForwardRepository->findAllWithPagination($page, $limit);
                $total = $virtualForwardRepository->getTotalElement();
                $data = [
                    "page" => (int) $page,
                    "limit" => (int) $limit,
                    "total" => (int) $total,
                    "data" => $data
                ];
                return $serializer->serialize(
                    $data,
                    'json',
                    SerializationContext::create()->setGroups(array('list'))
                );
            });

        return new JsonResponse($virtualForwards, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Return results of research
     * 
     * @Rest\Post(
     *      "/virtual-forwards/search",
     *      name="virtual_forward_search"
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return list of virtual forwards according to research",
     *      @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=VirtualUser::class, groups={"list", "getDomainNames"}))
     *      )
     * )
     * @OA\Tag(name="VirtualUser")
     * @param Request $request
     * @param VirtualForwardRepository $virtualForwadRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    public function search(
        Request $request,
        VirtualForwardRepository $virtualForwadRepository,
        SerializerInterface $serializer
    ): JsonResponse
    {
        $content = $request->toArray();
        $keyword = htmlspecialchars($content["keyword"]) ?? "";
        $domainId = (int)($content["domainId"]) ?? -1;
        $data = $virtualForwadRepository->search($domainId, $keyword);

        return new JsonResponse(
            $serializer->serialize(
                $data,
                'json',
                SerializationContext::create()->setGroups(array('list', 'getDomainNames'))
            ),
            Response::HTTP_OK,
            ['accept' => 'json'],
            true
        );
    }

    /**
     * The Virtual Forward
     * 
     * @Rest\Get(
     *      "/virtual-forwards/{id}",
     *      name="virtual_forward_show",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return detail of virtual forward",
     *      @Model(type=VirtualForward::class, groups={"list", "getDomainNames", "getVirtualUsers"} )
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual forward",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualForward")
     * @param VirtualForward $virtualForward
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    public function show(VirtualForward $virtualForward, SerializerInterface $serializer): JsonResponse
    {
        $data = $serializer->serialize(
            $virtualForward,
            'json',
            SerializationContext::create()->setGroups(array('list', 'getDomainNames', 'getVirtualUsers'))
        );
        return new JsonResponse($data, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Create a Virtual Forward
     * 
     * @Rest\Post(
     *      path="/virtual-forwards",
     *      name="virtual_forward_new"
     * )
     * @OA\Response(
     *      response=201,
     *      description="The created virtual forward"
     * )
     * @OA\RequestBody(
     *      description="VirtualForward to be created",
     *      required=True,
     *      @Model(type=VirtualForward::class)
     * )
     * @OA\Tag(name="VirtualForward")
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param TagAwareCacheInterface $cachePool
     * @param ValidatorInterface $validator
     * @param DomainNameRepository $domainNameRepository
     */
    public function new(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        TagAwareCacheInterface $cachePool,
        ValidatorInterface $validator,
        DomainNameRepository $domainNameRepository
    ): JsonResponse
    {
        $cachePool->invalidateTags(["virtualForwardsCache"]);
        $virtualForward = $serializer->deserialize($request->getContent(), VirtualForward::class, 'json');
        $content = $request->toArray();

        $errors = $validator->validate($virtualForward);
        if (count($errors) > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $domainName = $domainNameRepository->find($content['domainNameId'] ?? -1);
        if (!$domainName) {
            $errors = ["message" => "Domain id " . $content['domainNameId'] . " doesn't exist"];
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_NOT_FOUND,
                [],
                true
            );
        }
        $virtualForward->setDomainName($domainName);
        // force source to use correct domain name in case of error
        $source = preg_replace('#^(.+)@(.+)$#', '$1@' . $domainName->getName(), $virtualForward->getSource());
        $virtualForward->setSource($source);

        $em->persist($virtualForward);
        $em->flush();

        $jsonVirtualForward = $serializer->serialize(
            $virtualForward,
            'json',
            SerializationContext::create()->setGroups(array('list', 'getDomainNames', 'getVirtualUsers'))
        );
        $location = $urlGenerator->generate(
            'virtual_forward_show',
            ['id' => $virtualForward->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse($jsonVirtualForward, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Edit a Virtual Forward
     * 
     * @Rest\Put(
     *      path="/virtual-forwards/{id}",
     *      name="virtual_forward_edit",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Edit a virtual forward"
     * )
     * @OA\RequestBody(
     *      description="VirtualForward to be edited",
     *      required=True,
     *      @Model(type=VirtualForward::class)
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual forward",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualForward")
     * @ParamConverter("virtualForward", converter="fos_rest.request_body")
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @param ValidatorInterface $validator
     * @param VirtualForward $virtualForward
     * @param DomainNameRepository $domainNameRepository
     * @return JsonResponse
     */
    public function edit(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool,
        ValidatorInterface $validator,
        VirtualForward $virtualForward,
        DomainNameRepository $domainNameRepository
    ): JsonResponse
    {
        $cachePool->invalidateTags(["virtualForwardsCache"]);
        $content = $request->toArray();
        $errors = $validator->validate($virtualForward);
        if (count($errors) > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $domainName = $domainNameRepository->find($content['domainNameId'] ?? -1);
        if (!$domainName) {
            $errors = ["message" => "Domain id " . $content['domainNameId'] . " doesn't exist"];
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_NOT_FOUND,
                [],
                true
            );
        }
        $virtualForward->setDomainName($domainName);
        // force source to use correct domain name in case of error
        $source = preg_replace('#^(.+)@(.+)$#', '$1@' . $domainName->getName(), $virtualForward->getSource());
        $virtualForward->setSource($source);

        $em->persist($virtualForward);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Delete a Virtual Forward
     * 
     * @Rest\Delete(
     *      "/virtual-forwards/{id}",
     *      name="virtual_forward_delete",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Delete a virtual forward"
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual forward",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualForward")
     * @param VirtualForward $virtualForward
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    public function delete(
        VirtualForward $virtualForward,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool
    ): JsonResponse
    {
        $cachePool->invalidateTags(["virtualForwardsCache"]);
        $em->remove($virtualForward);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Bind a Virtual Forward and Virtual User
     * 
     * @Rest\Patch(
     *     "/virtual-forwards/{id}/attach/{userId}",
     *     name="virtual_forwards_attach_user",
     *     requirements={"id":"\d+", "userId":"\d+"}
     * )
     * @Entity("virtualUser", expr="repository.find(userId)")
     * @OA\Response(
     *      response=204,
     *      description="Attach user to forward"
     * )
     * @OA\Tag(name="VirtualForward")
     * @param VirtualForward $virtualForward
     * @param VirtualUser $virtualUser
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    public function attachUser(
        VirtualForward $virtualForward,
        VirtualUser $virtualUser,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $virtualForward->addVirtualUser($virtualUser);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Unbind Virtual Forward and Virtual User
     * 
     * @Rest\Delete(
     *     "/virtual-forwards/{id}/dettach/{userId}",
     *     name="virtual_forwards_dettach_user",
     *     requirements={"id":"\d+", "userId":"\d+"}
     * )
     * @Entity("virtualUser", expr="repository.find(userId)")
     * @OA\Response(
     *      response=204,
     *      description="Dettach user from forward"
     * )
     * @OA\Tag(name="VirtualForward")
     * @param VirtualForward $virtualForward
     * @param VirtualUser $virtualUser
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    public function dettachUser(
        VirtualForward $virtualForward,
        VirtualUser $virtualUser,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $virtualForward->removeVirtualUser($virtualUser);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
