<?php

namespace App\Controller;

use App\Entity\VirtualAlias;
use App\Entity\VirtualUser;
use App\Repository\DomainNameRepository;
use App\Repository\VirtualAliasRepository;
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
 * @IsGranted("ROLE_USER", message="Only user can manage Virtual Alias")
 */
class VirtualAliasController extends AbstractFOSRestController
{

    /**
     * List all Virtual Aliases
     * 
     * @Rest\Get(
     *      "/virtual-aliases",
     *      name="virtual_alias_list"
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
     *      description="Return list of virtual aliases",
     *      @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=VirtualAlias::class, groups={"list"}))
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
     * @OA\Tag(name="VirtualAlias")
     *
     * @param VirtualAliasRepository $virtualAliasRepository
     * @param SerializerInterface $serializer
     * @param TagAwareCacheInterface $cachePool
     * @param integer $page
     * @param integer $limit
     * @return JsonResponse
     */
    public function list(
        VirtualAliasRepository $virtualAliasRepository,
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool,
        $page = 1,
        $limit = 20
    ): JsonResponse
    {
        $idCache = "listVirtualAliases" . $page . "-" . $limit;
        $virtualAliass = $cachePool->get(
            $idCache,
            function (ItemInterface $item) use ($virtualAliasRepository, $serializer, $page, $limit) {
                $item->tag("virtualAliasesCache");
                $data = $virtualAliasRepository->findAllWithPagination($page, $limit);
                return $serializer->serialize(
                    $data,
                    'json',
                    SerializationContext::create()->setGroups(array('list'))
                );
            });

        return new JsonResponse($virtualAliass, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Return results of the research
     * 
     * @Rest\Post(
     *      "/virtual-aliases/search",
     *      name="virtual_alias_search"
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return list of virtual aliases according to research",
     *      @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=VirtualUser::class, groups={"list", "getDomainNames"}))
     *      )
     * )
     * @OA\Tag(name="VirtualUser")
     * @param Request $request
     * @param VirtualAliasRepository $virtualAliasRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    public function search(
        Request $request,
        VirtualAliasRepository $virtualAliasRepository,
        SerializerInterface $serializer
    ): JsonResponse
    {
        $content = $request->toArray();
        $keyword = htmlspecialchars($content["keyword"]) ?? "";
        $domainId = (int)($content["domainId"]) ?? -1;
        $data = $virtualAliasRepository->search($domainId, $keyword);

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
     * The Virtual Alias
     * 
     * @Rest\Get(
     *      "/virtual-aliases/{id}",
     *      name="virtual_alias_show",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return detail of virtual alias",
     *      @Model(type=VirtualAlias::class, groups={"list", "getDomainNames", "getVirtualUsers"})
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual alias",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualAlias")
     * @param VirtualAlias $virtualAlias
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    public function show(VirtualAlias $virtualAlias, SerializerInterface $serializer): JsonResponse
    {
        $data = $serializer->serialize(
            $virtualAlias,
            'json',
            SerializationContext::create()->setGroups(array('list', 'getDomainNames', 'getVirtualUsers'))
        );
        return new JsonResponse($data, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Create a Virtual Alias
     * 
     * @Rest\Post(
     *      path="/virtual-aliases",
     *      name="virtual_alias_new"
     * )
     * @OA\Response(
     *      response=201,
     *      description="The created virtual alias"
     * )
     * @OA\RequestBody(
     *      description="VirtualAlias to be created",
     *      required=True,
     *      @Model(type=VirtualAlias::class)
     * )
     * @OA\Tag(name="VirtualAlias")
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param TagAwareCacheInterface $cachePool
     * @param ValidatorInterface $validator
     * @param DomainNameRepository $domainNameRepository
     * @return JsonResponse
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
        $cachePool->invalidateTags(["virtualAliasesCache"]);
        $virtualAlias = $serializer->deserialize($request->getContent(), VirtualAlias::class, 'json');
        $content = $request->toArray();

        $errors = $validator->validate($virtualAlias);
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
        $virtualAlias->setDomainName($domainName);
        // force source to use correct domain name in case of error
        $source = preg_replace('#^(.+)@(.+)$#', '$1@' . $domainName->getName(), $virtualAlias->getSource());
        $virtualAlias->setSource($source);

        $em->persist($virtualAlias);
        $em->flush();

        $jsonVirtualAlias = $serializer->serialize(
            $virtualAlias,
            'json',
            SerializationContext::create()->setGroups(array('list', 'getDomainNames', 'getVirtualUsers'))
        );
        $location = $urlGenerator->generate(
            'virtual_alias_show',
            ['id' => $virtualAlias->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse($jsonVirtualAlias, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Edit Virtual Alias
     * 
     * @Rest\Put(
     *      path="/virtual-aliases/{id}",
     *      name="virtual_alias_edit",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Edit a virtual alias"
     * )
     * @OA\RequestBody(
     *      description="VirtualAlias to be edited",
     *      required=True,
     *      @Model(type=VirtualAlias::class)
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual alias",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualAlias")
     * @ParamConverter("virtualAlias", converter="fos_rest.request_body")
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @param ValidatorInterface $validator
     * @param VirtualAlias $virtualAlias
     * @param DomainNameRepository $domainNameRepository
     * @return JsonResponse
     */
    public function edit(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool,
        ValidatorInterface $validator,
        VirtualAlias $virtualAlias,
        DomainNameRepository $domainNameRepository
    ): JsonResponse
    {
        $cachePool->invalidateTags(["virtualAliasesCache"]);
        $content = $request->toArray();
        $errors = $validator->validate($virtualAlias);
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
        $virtualAlias->setDomainName($domainName);
        // force source to use correct domain name in case of error
        $source = preg_replace('#^(.+)@(.+)$#', '$1@' . $domainName->getName(), $virtualAlias->getSource());
        $virtualAlias->setSource($source);

        $em->persist($virtualAlias);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Delete a Virtual Alias
     * 
     * @Rest\Delete(
     *      "/virtual-aliases/{id}",
     *      name="virtual_alias_delete",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Delete a virtual alias"
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual alias",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualAlias")
     * @param VirtualAlias $virtualAlias
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    public function delete(
        VirtualAlias $virtualAlias,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool
    ): JsonResponse
    {
        $cachePool->invalidateTags(["virtualAliasesCache"]);
        $em->remove($virtualAlias);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Bind a Virtual Alias and a Virtual User
     * 
     * @Rest\Patch(
     *     "/virtual-aliases/{id}/attach/{userId}",
     *     name="virtual_aliases_attach_user",
     *     requirements={"id":"\d+", "userId":"\d+"}
     * )
     * @Entity("virtualUser", expr="repository.find(userId)")
     * @OA\Response(
     *      response=204,
     *      description="Attach user to alias"
     * )
     * @OA\Tag(name="VirtualAlias")
     * @param VirtualAlias $virtualAlias
     * @param VirtualUser $virtualUser
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    public function attachUser(
        VirtualAlias $virtualAlias,
        VirtualUser $virtualUser,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $virtualAlias->addVirtualUser($virtualUser);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Unbind Virtual Alias from Virtual User
     * 
     * @Rest\Delete(
     *     "/virtual-aliases/{id}/dettach/{userId}",
     *     name="virtual_aliases_dettach_user",
     *     requirements={"id":"\d+", "userId":"\d+"}
     * )
     * @Entity("virtualUser", expr="repository.find(userId)")
     * @OA\Response(
     *      response=204,
     *      description="Dettach user from alias"
     * )
     * @OA\Tag(name="VirtualAlias")
     * @param VirtualAlias $virtualAlias
     * @param VirtualUser $virtualUser
     * @param EntityManagerInterface $em
     * @return JsonResponse
     * 
     */
    public function dettachUser(
        VirtualAlias $virtualAlias,
        VirtualUser $virtualUser,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $virtualAlias->removeVirtualUser($virtualUser);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
