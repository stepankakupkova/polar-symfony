<?php

namespace App\Application\Controller\Web;

use App\Application\Service\Logger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ApplicationWriteController
{
	public function __construct(
		private Logger $logger,
		private MailerInterface $mailer,
	) {}

	public function sendEmail(Request $request): JsonResponse
	{
		try {
			$heading = htmlspecialchars(trim((string) $request->request->get('h1', '')), ENT_QUOTES, 'UTF-8');
			if (mb_strlen($heading) > 255) {
				return new JsonResponse(['success' => 'nok', 'message' => 'Nadpis je příliš dlouhý.']);
			}

			$url = filter_var((string) $request->request->get('url', ''), FILTER_VALIDATE_URL);
			if (!$url) {
				return new JsonResponse(['success' => 'nok', 'message' => 'Neplatná URL.']);
			}
			$url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

			$content = htmlspecialchars(trim((string) $request->request->get('content', '')), ENT_QUOTES, 'UTF-8');
			if ($content === '') {
				return new JsonResponse(['success' => 'nok', 'message' => 'Popis chyby je prázdný.']);
			}
			if (mb_strlen($content) > 2000) {
				return new JsonResponse(['success' => 'nok', 'message' => 'Zpráva je příliš dlouhá.']);
			}

			$blockedPatterns = '/(\/etc\/passwd|nslookup|curl|wget|<script|onerror|onload|document\.cookie|base64_decode|system|exec|shell_exec|passthru|eval|file_get_contents|fopen|fsockopen|popen)/i';
			if (preg_match($blockedPatterns, $content)) {
				return new JsonResponse(['success' => 'nok', 'message' => 'Obsah zprávy obsahuje zakázané výrazy.']);
			}

			$recaptchaSecret = getenv('GOOGLE_RECAPTCHA_SECRET') ?: '';
			$recaptchaToken = (string) $request->request->get('recaptchaToken', '');
			if ($recaptchaToken === '') {
				return new JsonResponse(['success' => 'nok_recaptcha', 'message' => 'Chybí reCAPTCHA token.']);
			}

			if ($recaptchaSecret !== '') {
				$verifyUrl = 'https://www.google.com/recaptcha/api/siteverify?secret=' . rawurlencode($recaptchaSecret) . '&response=' . rawurlencode($recaptchaToken);
				$response = @file_get_contents($verifyUrl);
				$data = $response ? json_decode($response, true) : null;
				if (!is_array($data) || !($data['success'] ?? false)) {
					return new JsonResponse(['success' => 'nok_recaptcha', 'message' => 'Ověření reCAPTCHA selhalo.']);
				}

				if (($data['hostname'] ?? '') !== $request->getHost()) {
					return new JsonResponse(['success' => 'nok_recaptcha', 'message' => 'reCAPTCHA byla použita z jiného webu.']);
				}

				if (($data['score'] ?? 0) < 0.7) {
					$this->logger->err('Odeslání chyby návštěvníkem - reCAPTCHA fail', [
						'description' => 'ERROR',
						'user' => '',
						'file' => __FILE__,
						'trace' => 'reCAPTCHA fail: IP=' . $request->getClientIp() . ' Score=' . ($data['score'] ?? 0),
					]);
					return new JsonResponse(['success' => 'nok_recaptcha', 'message' => 'Podezřelé chování. reCAPTCHA skóre je příliš nízké.']);
				}

				$challengeTime = strtotime($data['challenge_ts'] ?? '');
				if (time() - $challengeTime > 120) {
					return new JsonResponse(['success' => 'nok_recaptcha', 'message' => 'reCAPTCHA token je příliš starý.']);
				}
			}

			$email = (new Email())
				->from('polar@polar.cz')
				->to('polar@polar.cz')
				->subject('Divák hlásí chybu v článku na polar.cz')
				->text("URL: {$url}\nNadpis: {$heading}\n----------\n\n{$content}");

			$this->mailer->send($email);

			return new JsonResponse(['success' => 'ok', 'message' => null]);
		} catch (\Throwable) {
			return new JsonResponse(['success' => 'nok', 'message' => 'Neočekávaná chyba při odesílání.']);
		}
	}
}