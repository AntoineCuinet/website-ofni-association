<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class EmailPseudoAuthenticator extends AbstractAuthenticator
{
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;

    public function __construct(EntityManagerInterface $entityManager, RouterInterface $router)
    {
        $this->entityManager = $entityManager;
        $this->router = $router;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_login' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $usernameOrEmail = $request->request->get('username');
        $password = $request->request->get('password');

        if (!$usernameOrEmail || !$password) {
            throw new CustomUserMessageAuthenticationException('Username/email and password are required.');
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $usernameOrEmail])
            ?? $this->entityManager->getRepository(User::class)->findOneBy(['pseudo' => $usernameOrEmail]);

        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Utilisateur non trouvé.');
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException('Vous devez vérifier votre email avant de pouvoir vous connecter.');
        }

        return new Passport(
            new UserBadge($user->getUserIdentifier()),
            new PasswordCredentials($password)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'Login successful'], Response::HTTP_OK);
        }

        return new RedirectResponse($this->router->generate('home'));

    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())
        ];

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', $data['message']);
        }

        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
    }
}

