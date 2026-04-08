<?php

namespace App\Application\Controller\Web;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ApplicationWriteController
{
	public function sendEmail(Request $request): JsonResponse
	{
		// TODO: az bude v projektu mailer, nahradit primy mail() za standardni sluzbu.
		try {
			$heading = htmlspecialchars(trim((string) $request->request->get('h1', '')), ENT_QUOTES, 'UTF-8');
			if (mb_strlen($heading) > 255) {
				return new JsonResponse(['success' => 'nok', 'message' => 'Nadpis je příliš dlouhý.']);
			}

			$url = filter_var((string) $request->request->get('url', ''), FILTER_VALIDATE_URL);
			if (!$url) {
				return new JsonResponse(['success' => 'nok', 'message' => 'Neplatná URL.']);
			}

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
			if ($recaptchaSecret !== '') {
				if ($recaptchaToken === '') {
					return new JsonResponse(['success' => 'nok_recaptcha', 'message' => 'Chybí reCAPTCHA token.']);
				}

				$verifyUrl = 'https://www.google.com/recaptcha/api/siteverify?secret=' . rawurlencode($recaptchaSecret) . '&response=' . rawurlencode($recaptchaToken);
				$response = @file_get_contents($verifyUrl);
				$data = $response ? json_decode($response, true) : null;
				if (!is_array($data) || !($data['success'] ?? false)) {
					return new JsonResponse(['success' => 'nok_recaptcha', 'message' => 'Ověření reCAPTCHA selhalo.']);
				}
				if (($data['score'] ?? 0) < 0.7) {
					return new JsonResponse(['success' => 'nok_recaptcha', 'message' => 'Podezřelé chování. reCAPTCHA skóre je příliš nízké.']);
				}
			}

			$to = 'polar@polar.cz';
			$subject = 'Divák hlásí chybu v článku na polar.cz';
			$body = "URL: {$url}\nNadpis: {$heading}\n----------\n\n{$content}";
			$headers = [
				'MIME-Version: 1.0',
				'Content-type: text/plain; charset=UTF-8',
				'From: polar@polar.cz',
			];

			if (!@mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers))) {
				return new JsonResponse(['success' => 'nok', 'message' => 'Odeslání e-mailu selhalo.']);
			}

			return new JsonResponse(['success' => 'ok', 'message' => null]);
		} catch (\Throwable) {
			return new JsonResponse(['success' => 'nok', 'message' => 'Neočekávaná chyba při odesílání.']);
		}
	}
}