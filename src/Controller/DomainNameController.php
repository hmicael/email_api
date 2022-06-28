<?php

namespace App\Controller;

use App\Entity\DomainName;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\DomainNameRepository;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @Route("/api")
 * @IsGranted("ROLE_ADMIN", message="Only admin can manage domain name")
 */
class DomainNameController extends AbstractFOSRestController
{

    /**
     * @Rest\Get(
     *      "/domain_names"),
     *      name="domain_name_list"
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
     * @Rest\View(StatusCode=200)
     * @param DomainNameRepository $domainNameRepository
     * @param SerializerInterface $serializer
     * @param TagAwareCacheInterface $cachePool
     * @param $page
     * @param $limit
     * @return JsonResponse
     */
    public function list(
        DomainNameRepository $domainNameRepository,
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool,
        $page = 1,
        $limit = 20
    ): JsonResponse {
        $idCache = "listDomaineNames" . $page . "-" . $limit;
        $domainNames = $cachePool->get(
            $idCache,
            function (ItemInterface $item) use ($domainNameRepository, $serializer, $page, $limit) {
                $item->tag("domaineNamesCache");
                $data = $domainNameRepository->findAllWithPagination($page, $limit);
                return $serializer->serialize($data, 'json');
        });

        return new JsonResponse($domainNames, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * @Rest\Get(
     *      "/domain_names/{id}",
     *      name="domain_name_show",
     *      requirements = {"id"="\d+"}
     * )
     * @Rest\View(StatusCode=200)
     * @param DomainName $domainName
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    public function show(DomainName $domainName, SerializerInterface $serializer) : JsonResponse
    {
        $data = $serializer->serialize($domainName, 'json');
        return new JsonResponse($data, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * @Rest\Post(
     *      path="/domain_names",
     *      name="domaine_name_new"
     * )
     * @Rest\View(StatusCode = 201)
     * @ParamConverter("domainName", converter="fos_rest.request_body")
     * @param DomainName $domainName
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ConstraintViolationList $violations
     * @param TagAwareCacheInterface $cachePool
     */
    public function new(
        DomainName $domainName,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        ConstraintViolationList $violations,
        TagAwareCacheInterface $cachePool
        ): JsonResponse {
            $cachePool->invalidateTags(["domaineNamesCache"]);
            if (count($violations)) {
                return new JsonResponse($serializer->serialize($violations, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
            }

            $em->persist($domainName);
            $em->flush();

            $jsonDomainName = $serializer->serialize($domainName, 'json');
            $location = $urlGenerator->generate('domain_name_show', ['id' => $domainName->getId()], UrlGeneratorInterface::ABSOLUTE_URL); 
            
            return new JsonResponse($jsonDomainName, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * @Rest\Put(
     *      path="/domain_names/{id}",
     *      name="domaine_name_edit",
     *      requirements = {"id"="\d+"}
     * )
     * @Rest\View(StatusCode = 204)
     * @ParamConverter("domainName", converter="fos_rest.request_body")
     * @param EntityManagerInterface $em
     * @param DomainName $domainName
     * @param ConstraintViolationList $violations
     * @param TagAwareCacheInterface $cachePool
     */
    public function edit(
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        DomainName $domainName,
        ConstraintViolationList $violations,
        TagAwareCacheInterface $cachePool
        ): JsonResponse { 
            $cachePool->invalidateTags(["domaineNamesCache"]);
            if (count($violations)) {
                return new JsonResponse($serializer->serialize($violations, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
            }

            $em->persist($domainName);
            $em->flush();

            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

    /**
     * @Rest\Delete(
     *      "/domain_names/{id}",
     *      name="domain_name_delete",
     *      requirements = {"id"="\d+"}
     * )
     * @Rest\View(StatusCode=200)
     * @param DomainName $domainName
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     */
    public function delete(
        DomainName $domainName,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool
    ): JsonResponse{
            $cachePool->invalidateTags(["domaineNamesCache"]);
            $em->remove($domainName);
            $em->flush();
            
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
