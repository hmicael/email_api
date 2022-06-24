<?php

namespace App\Controller;

use App\Entity\DomainName;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\DomainNameRepository;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;


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
     * @param $page
     * @param $limit
     * @return JsonResponse
     */
    public function list(
        DomainNameRepository $domainNameRepository,
        SerializerInterface $serializer,
        $page = 1,
        $limit = 20
    ): JsonResponse {
        $domainNames = $domainNameRepository->findAllWithPaginate($page, $limit);
        $data = $serializer->serialize($domainNames, 'json');

        return new JsonResponse($data, Response::HTTP_OK, ['accept' => 'json'], true);
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
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function new(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator
        ): JsonResponse {
        $domainName = $serializer->deserialize($request->getContent(), DomainName::class, 'json');
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
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param DomainNameRepository $domainNameRepository
     * @param DomainName $currentDomaineName
     */
    public function edit(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        DomainNameRepository $domainNameRepository,
        DomainName $currentDomaineName
        ): JsonResponse {
            $updatedDomainName = $serializer->deserialize(
                $request->getContent(), 
                DomainName::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $currentDomaineName]
            );            
            $em->persist($updatedDomainName);
            $em->flush();
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

    /**
     * @Rest\Delete(
     *      "/domain_names/{id}",
     *      name="domain_name_show",
     *      requirements = {"id"="\d+"}
     * )
     * @Rest\View(StatusCode=200)
     * @param DomainName $domainName
     * @param ManagerRegistry $doctrine
     */
    public function delete(DomainName $domainName, ManagerRegistry $doctrine): JsonResponse
    {
        $em = $doctrine->getManager();
        $em->remove($domainName);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
