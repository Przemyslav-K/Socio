<?php

namespace App\Message\Handler\User;

use App\Entity\Token;
use App\Entity\User;
use App\Message\Handler\CommandHandler;
use App\Message\User\SendResetPasswordEmailCommand;
use App\Repository\TokenRepository;
use App\Repository\UserRepository;
use App\Util\StringUtil;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use ApiPlatform\Core\Api\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Mailer\MailerInterface;

class SendResetPasswordEmailCommandHandler implements CommandHandler
{
	public function __construct(
		private TokenRepository $tokenRepository,
		private UserRepository $userRepository,
		private TranslatorInterface $translator,
		private EntityManagerInterface $entityManager,
		private UrlGeneratorInterface $urlGenerator,
		private MailerInterface $mailer
	) {
	}

	public function __invoke(SendResetPasswordEmailCommand $command): void
	{
		$this->tokenRepository->deleteByTypeAndUserId(
			Token::RESET_PASSWORD_TYPE, 
			$command->getUserId()
		);
		$this->sendEmail($this->userRepository->find($command->getUserId()));
	}

	private function sendEmail(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from($_ENV['APP_EMAIL'])
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.forgotPassword.subject'))
            ->htmlTemplate('emails/forgot_password.html.twig')
            ->context([
            	'user' => $user,
            	'link' => $this->generateLink($this->generateToken($user)),
            ]);

        $this->mailer->send($email);
    }

    private function generateLink(Token $token): string
    {
    	return $this->urlGenerator->generate('page_reset_password', [
    		'token' => $token->getValue(),
    	], UrlGeneratorInterface::ABS_URL);
    }

    private function generateToken(User $user): Token
    {
    	$token = new Token();
    	$token->setType(Token::RESET_PASSWORD_TYPE);
    	$token->setValue(StringUtil::generateRandom());
    	$token->setUser($user);
    	$token->setExpiresAt(new \DateTimeImmutable('+2 hours'));
    	
    	$this->entityManager->persist($token);
    	$this->entityManager->flush();

    	return $token;
    }
}