<?php

namespace App\Controller;

use App\Entity\VirtualUser;
use App\Repository\DomainNameRepository;
use App\Repository\VirtualUserRepository;
use App\Service\Tools;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @Route("/api")
 * @IsGranted("ROLE_USER", message="Only user can manage Virtual User")
 */
class VirtualUserController extends AbstractFOSRestController
{
    /**
     * List all Virtual Users
     * 
     * @Rest\Get(
     *      "/virtual-users",
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
     *         @OA\Items(ref=@Model(type=VirtualUser::class, groups={"list", "getDomainNames"}))
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
     * @param VirtualUserRepository $virtualUserRepository
     * @param SerializerInterface $serializer
     * @param TagAwareCacheInterface $cachePool
     * @param integer $page
     * @param integer $limit
     * @return JsonResponse
     */
    public function list(
        VirtualUserRepository $virtualUserRepository,
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool,
        $page = 1,
        $limit = 20
    ): JsonResponse
    {
        $idCache = "listVirtualUsers" . $page . "-" . $limit;
        $virtualUsers = $cachePool->get(
            $idCache,
            function (ItemInterface $item) use ($virtualUserRepository, $serializer, $page, $limit) {
                $item->tag("virtualUserCache");
                $data = $virtualUserRepository->findAllWithPagination($page, $limit);
                return $serializer->serialize(
                    $data,
                    'json',
                    SerializationContext::create()->setGroups(array('list', 'getDomainNames'))
                );
            });

        return new JsonResponse($virtualUsers, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Return results of research
     * 
     * @Rest\Post(
     *      "/virtual-users/search",
     *      name="virtual_user_search"
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return list of virtual users according to research",
     *      @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=VirtualUser::class, groups={"list", "getDomainNames"}))
     *      )
     * )
     * @OA\Tag(name="VirtualUser")
     * @param Request $request
     * @param VirtualUserRepository $virtualUserRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    public function search(
        Request $request,
        VirtualUserRepository $virtualUserRepository,
        SerializerInterface $serializer
    ): JsonResponse
    {
        $content = $request->toArray();
        $keyword = htmlspecialchars($content["keyword"]) ?? "";
        $domainId = (int)($content["domainId"]) ?? -1;
        $data = $virtualUserRepository->search($domainId, $keyword);

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
     * The Virutal User
     * 
     * @Rest\Get(
     *      "/virtual-users/{id}",
     *      name="virtual_user_show",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=200,
     *      description="Return detail of virtual user",
     *      @Model(type=VirtualUser::class, groups={"list", "getDomainNames", "getAliases", "getForwards"})
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
    public function show(VirtualUser $virtualUser, SerializerInterface $serializer): JsonResponse
    {
        $data = $serializer->serialize(
            $virtualUser,
            'json',
            SerializationContext::create()->setGroups(array('list', 'getDomainNames', 'getAliases', "getForwards"))
        );

        return new JsonResponse($data, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Create Virtual User
     * 
     * @Rest\Post(
     *      path="/virtual-users",
     *      name="virtual_user_new"
     * )
     * @OA\Response(
     *      response=201,
     *      description="The created virtual user"
     * )
     * @OA\RequestBody(
     *      description="VirtualUser to be created",
     *      required=True,
     *      @Model(type=VirtualUser::class)
     * )
     * @OA\Tag(name="VirtualUser")
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param TagAwareCacheInterface $cachePool
     * @param ValidatorInterface $validator
     * @param MailerInterface $mailer
     * @return JsonResponse
     */
    public function new(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        TagAwareCacheInterface $cachePool,
        ValidatorInterface $validator,
        DomainNameRepository $domainNameRepository,
        MailerInterface $mailer
    ): JsonResponse
    {
        $cachePool->invalidateTags(["virtualUserCache"]);
        $virtualUser = $serializer->deserialize($request->getContent(), VirtualUser::class, 'json');

        $content = $request->toArray();
        $errors = $validator->validate($virtualUser);
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
        $virtualUser->setDomainName($domainName);
        $virtualUser->setMaildir($domainName->getName() . "/" . explode("@", $virtualUser->getEmail())[0] . "/");

        // force email to use correct domain name in case of error    
        $email = preg_replace('#^(.+)@(.+)$#', '$1@' . $domainName->getName(), $virtualUser->getEmail());
        $virtualUser->setEmail($email);

        $em->persist($virtualUser);
        $em->flush();

        //sending email to admin and HR
        $email = (new TemplatedEmail())
            ->subject('New email account created !')
            ->htmlTemplate('email/new_account.html.twig')
            ->context([
                'name' => $virtualUser->getName(),
                'firstname' => $virtualUser->getFirstName(),
                'user_email' => $virtualUser->getEmail(),
                'password' => $content["password"]
            ]);
        $mailer->send($email);

        $jsonVirtualUser = $serializer->serialize(
            $virtualUser,
            'json',
            SerializationContext::create()->setGroups(array('list', 'getDomainNames', 'getAliases', "getForwards"))
        );
        $location = $urlGenerator->generate(
            'virtual_user_show',
            ['id' => $virtualUser->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse($jsonVirtualUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Edit Virtual User
     * 
     * @Rest\Put(
     *      path="/virtual-users/{id}",
     *      name="virtual_user_edit",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Edit a virtual user"
     * )
     * @OA\RequestBody(
     *      description="VirtualUser to be edited",
     *      required=True,
     *      @Model(type=VirtualUser::class)
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual user",
     *      @OA\Schema(type="int")
     * )
     * @ParamConverter("virtualUser", converter="fos_rest.request_body")
     * @OA\Tag(name="VirtualUser")
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @param ValidatorInterface $validator
     * @param VirtualUser $virtualUser
     * @param DomainNameRepository $domainNameRepository
     * @return JsonResponse
     */
    public function edit(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool,
        ValidatorInterface $validator,
        VirtualUser $virtualUser,
        DomainNameRepository $domainNameRepository
    ): JsonResponse
    {
        $cachePool->invalidateTags(["virtualUserCache"]);
        $content = $request->toArray();
        $errors = $validator->validate($virtualUser);
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
        $virtualUser->setDomainName($domainName);

        $em->persist($virtualUser);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Reset Virtual User's password
     * 
     * @Rest\Patch(
     *      "/virtual-users/{id}/reset-password",
     *      name="virtual_user_reset_password",
     *      requirements = {"id"="\d+"}
     * )
     * @OA\Response(
     *      response=204,
     *      description="Reset virtual user's password"
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Id of the virtual user",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="VirtualUser")
     * @param VirtualUser $virtualUser
     * @param EntityManagerInterface $em
     * @param Tools $tools
     * @param MailerInterface $mailer
     * @return JsonResponse
     */
    public function resetPassword(
        VirtualUser $virtualUser,
        EntityManagerInterface $em,
        Tools $tools,
        MailerInterface $mailer
    ): JsonResponse
    {
        $newPassword = $tools->getRandomPassword();
        $virtualUser->setPassword($newPassword);

        $em->persist($virtualUser);
        $em->flush();

        //sending email to admin and HR
        $email = (new TemplatedEmail())
            ->subject('Password reseted !')
            ->htmlTemplate('email/account_password_reset.html.twig')
            ->context([
                'name' => $virtualUser->getName(),
                'firstname' => $virtualUser->getFirstName(),
                'user_email' => $virtualUser->getEmail(),
                'password' => $newPassword
            ]);
        $mailer->send($email);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Delete Virtual User
     * 
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
     * @param VirtualUser $virtualUser
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    public function delete(
        VirtualUser $virtualUser,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool
    ): JsonResponse
    {
        $cachePool->invalidateTags(["virtualUserCache"]);
        $em->remove($virtualUser);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
