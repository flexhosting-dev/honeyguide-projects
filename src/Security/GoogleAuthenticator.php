<?php

namespace App\Security;

use App\Entity\User;
use App\Service\RegistrationRequestService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    private bool $enabled;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private RegistrationRequestService $registrationRequestService,
        #[Autowire('%env(bool:GOOGLE_AUTH_ENABLED)%')]
        bool $enabled = true,
    ) {
        $this->enabled = $enabled;
    }

    public function supports(Request $request): ?bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();
                $googleId = $googleUser->getId();

                // First, try to find user by Google ID (existing users can always log in)
                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['googleId' => $googleId]);

                if ($existingUser) {
                    return $existingUser;
                }

                // If not found by Google ID, check by email and link the account
                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if ($existingUser) {
                    // Link Google account to existing user
                    $existingUser->setGoogleId($googleId);
                    $this->entityManager->flush();
                    return $existingUser;
                }

                // Check domain restriction for new users
                if (!$this->registrationRequestService->isDomainAllowed($email)) {
                    $this->registrationRequestService->createGoogleRequest(
                        $email,
                        $googleUser->getFirstName() ?? '',
                        $googleUser->getLastName() ?? '',
                        $googleId,
                    );

                    throw new CustomUserMessageAuthenticationException(
                        'Your access request has been submitted for review. You will be notified once an administrator approves your account.'
                    );
                }

                // Create new user
                $user = new User();
                $user->setEmail($email);
                $user->setGoogleId($googleId);
                $user->setFirstName($googleUser->getFirstName() ?? '');
                $user->setLastName($googleUser->getLastName() ?? '');
                $user->setIsVerified(true); // Google accounts are verified

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = $exception->getMessage() ?: 'Google authentication failed. Please try again.';
        $request->getSession()->getFlashBag()->add('error', $message);

        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
