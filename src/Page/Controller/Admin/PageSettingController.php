<?php

namespace App\Page\Controller\Admin;

use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Page\Repository\PageSettingRepository;
use Exception;
use Imagine\Gd\Font;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PageSettingController
{
	/**
	 * @var string
	 */
	private string $imageDefault;

	public function __construct(
		private Logger $logger,
		private PageSettingRepository $settingRepository,
		private PhtmlRenderer $renderer,
		private Security $security,
		private UrlGeneratorInterface $urlGenerator,
		private string $PUBLIC_PATH,
	) {
		$this->imageDefault = 'data/page/!default-page.png';
	}

	/**
	 * @return Response|RedirectResponse
	 */
	public function setting(Request $request): Response
	{
		$identity = $this->security->getUser();
		try {
			$setting = $this->settingRepository->fetchSetting();
		} catch (Exception) {
			return new RedirectResponse($this->urlGenerator->generate('admin_page_index'));
		}

		$success = null;
		$error = null;

		try {
			if (!$request->isMethod('POST')) {
				return new Response($this->renderer->renderWithAdminLayout('page/admin/setting', [
					'pageTitle' => 'Stránky - nastavení',
					'setting' => $setting,
					'success' => $success,
					'error' => $error,
				]));
			}

			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($this->urlGenerator->generate('admin_page_index'));
			}

			// Uložení nastavení
			$this->settingRepository->updateSetting('img_width', $post['img_width'] ?? '800');
			$this->settingRepository->updateSetting('img_height', $post['img_height'] ?? '450');
			$this->settingRepository->updateSetting('footer_number_1', $post['footer_number_1'] ?? '');
			$this->settingRepository->updateSetting('footer_number_2', $post['footer_number_2'] ?? '');
			$this->settingRepository->updateSetting('footer_number_3', $post['footer_number_3'] ?? '');
			$this->settingRepository->updateSetting('footer_number_4', $post['footer_number_4'] ?? '');

			$setting = $this->settingRepository->fetchSetting();

			// Generování výchozího obrázku
			try {
				$imgWidth = (int) ($setting['img_width'] ?? 800);
				$imgHeight = (int) ($setting['img_height'] ?? 450);

				$imagine = new Imagine();
				$size = new Box($imgWidth, $imgHeight);
				$palette = new RGB();
				$color = $palette->color('#ccc', 100);
				$font = new Font($this->PUBLIC_PATH . '/vendor/admin/font-google/webfont/OpenSans.woff', 15, $palette->color('#000', 100));
				$image = $imagine->create($size, $color);
				$image->draw()->text($imgWidth . ' px x ' . $imgHeight . ' px', $font, new Point(10, $imgHeight - 25));
				$image->save($this->PUBLIC_PATH . '/' . $this->imageDefault, ['png_compression_level' => 8]);
				unset($image);

				$this->logger->notice('PAGE - Edit settings', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);

				$success = 'Nastavení uloženo';
			} catch (\Imagine\Exception\RuntimeException $e) {
				$this->logger->err('PAGE - Edit settings', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $e->getMessage(),
				]);
				$error = $e->getMessage();
			}
		} catch (Exception $e) {
			$this->logger->err('PAGE - Edit settings', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
			$error = $e->getMessage();
		}

		return new Response($this->renderer->renderWithAdminLayout('page/admin/setting', [
			'pageTitle' => 'Stránky - nastavení',
			'setting' => $setting,
			'success' => $success,
			'error' => $error,
		]));
	}
}
