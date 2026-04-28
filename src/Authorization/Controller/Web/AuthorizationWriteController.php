<?php

namespace App\Authorization\Controller\Web;

use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Authorization\Repository\AuthorizationRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AuthorizationWriteController
{
	public function __construct(
		private PhtmlRenderer $renderer,
		private AuthorizationRepository $authorizationRepository,
		private MailerInterface $mailer,
		private UrlGeneratorInterface $urlGenerator,
		private Logger $logger,
	) {}

	public function reset(Request $request): Response
	{
		$message = null;
		$alert = null;
		$email = '';

		if ($request->isMethod('POST')) {
			$email = trim((string) $request->request->get('email', ''));

			try {
				$authorization = $this->authorizationRepository->findPostBy('username', $email);

				if ($authorization === null) {
					throw new \InvalidArgumentException('Email nenalezen.');
				}

				$hash = $this->getRandString(30);

				$this->authorizationRepository->setHash((int) $authorization['id'], $hash);

				// Log
				$this->logger->notice('AUTHORIZATION - Reset password', [
					'description' => 'Hash has been sent by email',
					'user' => $authorization['username'],
					'file' => __FILE__,
				]);

				// Link pro reset hesla
				$link = $request->getSchemeAndHttpHost() . $this->urlGenerator->generate('authorization_reset_password', ['hash' => $hash]);

				// Email - text
				$messageText = file_get_contents(dirname(__DIR__, 4) . '/templates/authorization/partial/email/reset/text.phtml');
				$messageText = str_replace(
					['{{text1}}', '{{text2}}', '{{text3}}', '{{text4}}', '{{text5}}', '{{text6}}', '{{text7}}', '{{site}}', '{{date}}', '{{action_url}}'],
					[
						'Použijte tento odkaz pro reset hesla. Odkaz je platný pouze 24 hodin.',
						'POLAR televize Ostrava',
						'Dobrý den',
						'Nedávno jste požádali o resetování hesla k vašemu účtu. Pro reset hesla použijte tlačítko níže. Toto resetování hesla je platné pouze pro příštích 24 hodin.',
						'Resetovat heslo',
						'Pokud jste nepožádali o resetování hesla, ignorujte tento e-mail nebo kontaktujte podporu.',
						'Pokud výše uvedené tlačítko nefunguje, zkopírujte a vložte níže uvedenou URL do svého webového prohlížeče.',
						$_SERVER['HTTP_HOST'],
						date('Y'),
						$link,
					],
					$messageText
				);

				// Email - html
				$messageHtml = file_get_contents(dirname(__DIR__, 4) . '/templates/authorization/partial/email/reset/html.phtml');
				$messageHtml = str_replace(
					['{{text1}}', '{{text2}}', '{{text3}}', '{{text4}}', '{{text5}}', '{{text6}}', '{{text7}}', '{{site}}', '{{date}}', '{{action_url}}'],
					[
						'Použijte tento odkaz pro reset hesla. Odkaz je platný pouze 24 hodin.',
						'POLAR televize Ostrava',
						'Dobrý den',
						'Nedávno jste požádali o resetování hesla k vašemu účtu. Pro reset hesla použijte tlačítko níže. <strong>Toto resetování hesla je platné pouze pro příštích 24 hodin.</strong>',
						'Resetovat heslo',
						'Pokud jste nepožádali o resetování hesla, ignorujte tento e-mail nebo kontaktujte podporu.',
						'Pokud výše uvedené tlačítko nefunguje, zkopírujte a vložte níže uvedenou URL do svého webového prohlížeče.',
						$_SERVER['HTTP_HOST'],
						date('Y'),
						$link,
					],
					$messageHtml
				);

				$emailMessage = (new Email())
					->from('polar@polar.cz')
					->to($authorization['username'])
					->subject('Reset hesla')
					->text($messageText)
					->html($messageHtml);

				$this->mailer->send($emailMessage);

				$message = 'Instrukce pro obnovení hesla byly odeslány na váš e-mail.';
				$alert = 'alert-success';
				$email = '';
			} catch (\InvalidArgumentException | \RuntimeException $e) {
				$message = 'E-mail nebyl nalezen. Zadejte správnou e-mailovou adresu.';
				$alert = 'alert-danger';
			}
		}

		return new Response($this->renderer->renderWithLayout('authorization/reset', [
			'message' => $message,
			'alert' => $alert,
			'email' => $email,
		]));
	}

	public function resetPassword(Request $request, string $hash): Response
	{
		$message = null;
		$alert = null;

		try {
			$authorization = $this->authorizationRepository->findPostBy('hash', $hash);

			if ($authorization === null) {
				throw new \InvalidArgumentException('Hash nenalezen.');
			}

			$password = $this->getRandString();

			$passwordHash = password_hash($password, PASSWORD_BCRYPT);

			$this->authorizationRepository->updatePassword((int) $authorization['id'], $passwordHash);
			$this->authorizationRepository->clearHash((int) $authorization['id']);

			// Log
			$this->logger->notice('AUTHORIZATION - Reset password', [
				'description' => 'New password has been sent by email',
				'user' => $authorization['username'],
				'file' => __FILE__,
			]);

			// Link pro přihlášení
			$link = $request->getSchemeAndHttpHost() . $this->urlGenerator->generate('app_login');

			// Email - text
			$messageText = file_get_contents(dirname(__DIR__, 4) . '/templates/authorization/partial/email/reset-password/text.phtml');
			$messageText = str_replace(
				['{{text1}}', '{{text2}}', '{{text3}}', '{{text4}}', '{{text5}}', '{{text6}}', '{{text7}}', '{{text8}}', '{{site}}', '{{date}}', '{{action_url}}'],
				[
					'Přihlaste se níže novým heslem.',
					'POLAR televize Ostrava',
					'Dobrý den',
					'Nedávno jste požádali o resetování hesla k vašemu účtu. Přihlaste se níže novým heslem.',
					$password,
					'Pokud jste nepožádali o resetování hesla, kontaktujte prosím podporu.',
					'Přihlásit se',
					'Pokud výše uvedené tlačítko nefunguje, zkopírujte a vložte níže uvedenou URL do svého webového prohlížeče.',
					$_SERVER['HTTP_HOST'],
					date('Y'),
					$link,
				],
				$messageText
			);

			// Email - html
			$messageHtml = file_get_contents(dirname(__DIR__, 4) . '/templates/authorization/partial/email/reset-password/html.phtml');
			$messageHtml = str_replace(
				['{{text1}}', '{{text2}}', '{{text3}}', '{{text4}}', '{{text5}}', '{{text6}}', '{{text7}}', '{{text8}}', '{{site}}', '{{date}}', '{{action_url}}'],
				[
					'Přihlaste se níže novým heslem.',
					'POLAR televize Ostrava',
					'Dobrý den',
					'Nedávno jste požádali o resetování hesla k vašemu účtu. <strong>Přihlaste se níže novým heslem</strong>.',
					$password,
					'Pokud jste nepožádali o resetování hesla, kontaktujte prosím podporu.',
					'Přihlásit se',
					'Pokud výše uvedené tlačítko nefunguje, zkopírujte a vložte níže uvedenou URL do svého webového prohlížeče.',
					$_SERVER['HTTP_HOST'],
					date('Y'),
					$link,
				],
				$messageHtml
			);

			$emailMessage = (new Email())
				->from('polar@polar.cz')
				->to($authorization['username'])
				->subject('Nové heslo')
				->text($messageText)
				->html($messageHtml);

			$this->mailer->send($emailMessage);

			$message = 'Nové heslo bylo odesláno na váš e-mail.';
			$alert = 'alert-success';
		} catch (\InvalidArgumentException | \RuntimeException $e) {
			$message = 'Hash nebyl nalezen! Kontaktujte podporu.';
			$alert = 'alert-danger';
		}

		return new Response($this->renderer->renderWithLayout('authorization/reset-password', [
			'message' => $message,
			'alert' => $alert,
		]));
	}

	private function getRandString(int $length = 10): string
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$randomString = '';

		try {
			for ($i = 0; $i < $length; $i++) {
				$randomString .= $characters[random_int(0, strlen($characters) - 1)];
			}
		} catch (\Exception $e) {
			return $randomString;
		}

		return $randomString;
	}
}
