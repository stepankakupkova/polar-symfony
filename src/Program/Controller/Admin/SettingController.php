<?php

namespace App\Program\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Application\View\PhtmlRenderer;
use App\Program\Repository\SettingRepository;
use App\Security\User;
use Imagine\Gd\Font;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SettingController
{
	private string $showImageDefault = 'data/program/show/!default-show.png';

	public function __construct(
		private string $PUBLIC_PATH,
		private Security $security,
		private FlashMessenger $flashMessenger,
	) {}

	public function index(
		PhtmlRenderer $renderer,
	): Response
	{
		return new Response($renderer->renderWithAdminLayout('program/setting/index', [
			'pageTitle' => 'Setting | Program',
		]));
	}

	public function setting(
		Request $request,
		PhtmlRenderer $renderer,
		SettingRepository $settingRepository,
		LoggerInterface $logger,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		/** @var User $identity */
		$identity = $this->security->getUser();
		try {
			$setting = $settingRepository->fetchSetting();
		} catch (\Exception) {
			return new RedirectResponse($urlGenerator->generate('admin_program_setting'));
		}

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($urlGenerator->generate('admin_program_setting'));
			}

			$setting['show_img_width'] = $post['show_img_width'] ?? $setting['show_img_width'];
			$setting['show_img_height'] = $post['show_img_height'] ?? $setting['show_img_height'];

			try {
				$settingRepository->updateSetting([
					'show_img_width' => $setting['show_img_width'],
					'show_img_height' => $setting['show_img_height'],
				]);

				// Generování výchozího obrázku
				try {
					$imagine = new Imagine();
					$size = new Box((int) $setting['show_img_width'], (int) $setting['show_img_height']);
					$palette = new RGB();
					$color = $palette->color('#ccc', 100);
					$font = new Font($this->PUBLIC_PATH . '/vendor/admin/font-google/webfont/OpenSans.woff', 15, $palette->color('#000', 100));
					$image = $imagine->create($size, $color);
					$image->draw()->text($setting['show_img_width'] . ' px x ' . $setting['show_img_height'] . ' px', $font, new Point(10, (int) $setting['show_img_height'] - 25));
					$image->save($this->PUBLIC_PATH . '/' . $this->showImageDefault, ['png_compression_level' => 8]);
					unset($image);

					$this->flashMessenger->addMessage('success', 'Úspěšně uloženo', 'Settings saved');

					// Log
					$logger->notice('PROGRAM - Edit settings', [
						'description' => 'OK',
						'user' => $identity->getUserIdentifier(),
						'file' => __FILE__
					]);
				} catch (\Imagine\Exception\RuntimeException $e) {
					$this->flashMessenger->addMessage('error', 'Chyba', $e->getMessage());

					// Log
					$logger->error('PROGRAM - Edit settings', [
						'description' => 'ERROR',
						'user' => $identity->getUserIdentifier(),
						'file' => __FILE__,
						'trace' => $e->getMessage(),
					]);
				}
			} catch (\Exception $e) {
				$this->flashMessenger->addMessage('error', 'Chyba', $e->getMessage());

				// Log
				$logger->error('PROGRAM - Edit settings', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $e->getMessage(),
				]);
			}
		}

		return new Response($renderer->renderWithAdminLayout('program/setting/setting', [
			'pageTitle' => 'Setting | Program',
			'setting' => $setting,
		]));
	}
}
