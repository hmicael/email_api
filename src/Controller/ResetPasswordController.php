<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use JMS\Serializer\SerializerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * @Route("/api")
 */
class ResetPasswordController extends AbstractFOSRestController
{
    use ResetPasswordControllerTrait;

    private ResetPasswordHelperInterface $resetPasswordHelper;
    private EntityManagerInterface $entityManager;

    public function __construct(ResetPasswordHelperInterface $resetPasswordHelper, EntityManagerInterface $entityManager)
    {
        $this->resetPasswordHelper = $resetPasswordHelper;
        $this->entityManager = $entityManager;
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     *
     * @Rest\Post(
     *      "/users/reset-password/{token}",
     *      name="user_reset_password"
     * )
     * @OA\Response(
     *      response=204,
     *      description="Reset a reset password"
     * )
     * @OA\Tag(name="User")
     * @Rest\View(StatusCode = 204)
     *
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @param MailerInterface $mailer
     * @param string|null $token
     * @return JsonResponse
     */
    public function reset(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        MailerInterface $mailer,
        string $token = null
    ): JsonResponse
    {
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $errors) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $content = $request->toArray();
        $plainPassowrd = $content['plainPassword'] ?? "";
        $user->setPassword($plainPassowrd);

        // Check if the password is valid
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // Encode(hash) the plain password, and set it.
        $encodedPassword = $userPasswordHasher->hashPassword(
            $user,
            $plainPassowrd
        );
        $user->setPassword($encodedPassword);

        $this->entityManager->flush();

        // A password reset token should be used only once, remove it.
        $this->resetPasswordHelper->removeResetRequest($token);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Send reset password email
     *
     * @Rest\Post(
     *      "/users/forgot-password",
     *      name="user_forgot_password"
     * )
     * @OA\Response(
     *      response=204,
     *      description="Request a reset password"
     * )
     * @OA\Tag(name="User")
     * @Rest\View(StatusCode = 204)
     *
     * @param Request $request
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @param MailerInterface $mailer
     * @return JsonResponse
     */
    public function processSendingPasswordResetEmail(
        Request $request,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        MailerInterface $mailer
    ): JsonResponse
    {
        $content = $request->toArray();
        $email = $content['email'] ?? "";
        $emailConstraint = new EmailConstraint();
        $errors = $validator->validateProperty($emailConstraint, $email);
        if (count($errors) > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user = $userRepository->findOneByEmail($email);
        if (!$user) {
            $errors = ["message" => "Email " . $content['email'] . " doesn't exist"];
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_NOT_FOUND,
                [],
                true
            );
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $errors) {
            // If you want to tell the user why a reset email was not sent, uncomment
            // the lines below and change the redirect to 'app_forgot_password_request'.
            // Caution: This may reveal if a user is registered or not.
            //
            // $this->addFlash('reset_password_error', sprintf(
            //     '%s - %s',
            //     ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE,
            //     $e->getReason()
            // ));

            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $email = (new TemplatedEmail())
            ->from(new Address('mailer@ingenosya.mg', 'Admin'))
            // ->to($user->getEmail())
            ->to('handriamahadimby@ingenosya.mg')
            ->subject('Your password reset request')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ]);
        $mailer->send($email);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
