<?php

namespace App\User\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Authorization\Repository\AuthorizationRepository;
use App\Security\User;
use App\User\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class UserWriteController
{
	public function __construct(
		private FlashMessenger $flashMessenger,
		private Logger $logger,
		private UserRepository $userRepository,
		private AuthorizationRepository $authorizationRepository,
		private PhtmlRenderer $renderer,
		private Security $security,
		private UrlGeneratorInterface $urlGenerator,
		private string $PUBLIC_PATH,
	) {}

	public function add(Request $request): Response
	{
		/** @var User $identity */
		$identity = $this->security->getUser();

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
			}

			// Validace
			$errors = $this->validateForm($post, true);
			if (!empty($errors)) {
				return new Response($this->renderer->renderWithAdminLayout('user/admin/add', [
					'identity' => $identity,
					'pageTitle' => 'Uživatelé',
					'post' => $post,
					'errors' => $errors,
				]));
			}

			try {
				$password = password_hash($post['password'], PASSWORD_BCRYPT);

				$authorizationId = $this->authorizationRepository->insertPost([
					'username' => $post['username'],
					'password' => $password,
					'active' => !empty($post['active']),
					'role' => $post['role'] ?? '',
				]);

				$user = $this->userRepository->findPostBy('authorization_id', $authorizationId);
				if ($user) {
					$image = $post['image'] ?? 'data/user/!default-user.png';

					if (str_contains($image, '/tmp/')) {
						$folder = 'data/user/' . $user['id'] . '/';
						if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
							mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
						}
						$newImage = $folder . substr($image, strrpos($image, '/') + 1);
						rename($this->PUBLIC_PATH . '/' . $image, $this->PUBLIC_PATH . '/' . $newImage);
						$image = $newImage;
					}

					$this->userRepository->updatePost($user['id'], [
						'first_name' => $post['first_name'] ?? '',
						'last_name' => $post['last_name'] ?? '',
						'image' => $image,
						'created_user' => $identity->getUserIdentifier(),
						'updated_user' => $identity->getUserIdentifier(),
					]);
				}

				$this->flashMessenger->addMessage(
					'success',
					'Uživatelé',
					'Uživatel <strong>' . htmlspecialchars($post['username']) . '</strong> byl vytvořen'
				);

				$this->logger->notice('USER - Add user', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);

				return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
			} catch (\Exception $e) {
				$this->logger->err('USER - Add user', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $e->getMessage(),
				]);
				return new Response($this->renderer->renderWithAdminLayout('user/admin/add', [
					'identity' => $identity,
					'pageTitle' => 'Uživatelé',
					'post' => $post,
					'errors' => ['general' => $e->getMessage()],
				]));
			}
		}

		return new Response($this->renderer->renderWithAdminLayout('user/admin/add', [
			'identity' => $identity,
			'pageTitle' => 'Uživatelé',
			'post' => [],
			'errors' => [],
		]));
	}

	public function edit(Request $request, int $id): Response
	{
		/** @var User $identity */
		$identity = $this->security->getUser();

		$user = $this->userRepository->findPostBy('id', $id);
		if (!$user) {
			return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
		}

		$authorization = $this->authorizationRepository->findPostBy('id', $user['authorization_id']);
		if (!$authorization) {
			return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
		}

		// Načíst role
		$roles = $this->userRepository->getRolesForUser((int) $user['authorization_id']);
		$currentRole = $roles[0] ?? '';

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
			}

			$errors = $this->validateForm($post, false);
			if (!empty($errors)) {
				return new Response($this->renderer->renderWithAdminLayout('user/admin/edit', [
					'identity' => $identity,
					'pageTitle' => 'Uživatelé',
					'id' => $id,
					'post' => array_merge($user, $authorization, $post),
					'currentRole' => $post['role'] ?? $currentRole,
					'errors' => $errors,
				]));
			}

			try {
				$updateAuth = [
					'username' => $post['username'],
					'active' => !empty($post['active']),
					'role' => $post['role'] ?? '',
				];

				if (!empty($post['password'])) {
					$updateAuth['password'] = password_hash($post['password'], PASSWORD_BCRYPT);
				}

				$this->authorizationRepository->updatePost((int) $authorization['id'], $updateAuth);

				$image = $post['image'] ?? $user['image'];

				if (str_contains($image, '/tmp/')) {
					$folder = 'data/user/' . $user['id'] . '/';
					if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
						mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
					}
					$newImage = $folder . substr($image, strrpos($image, '/') + 1);
					rename($this->PUBLIC_PATH . '/' . $image, $this->PUBLIC_PATH . '/' . $newImage);
					$image = $newImage;
				}

				$this->userRepository->updatePost($id, [
					'first_name' => $post['first_name'] ?? '',
					'last_name' => $post['last_name'] ?? '',
					'image' => $image,
					'updated_user' => $identity->getUserIdentifier(),
				]);

				$this->flashMessenger->addMessage(
					'success',
					'Uživatelé',
					'Uživatel <strong>' . htmlspecialchars($post['username']) . '</strong> byl upraven'
				);

				$this->logger->notice('USER - Edit user', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);

				return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
			} catch (\Exception $e) {
				$this->logger->err('USER - Edit user', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $e->getMessage(),
				]);
				return new Response($this->renderer->renderWithAdminLayout('user/admin/edit', [
					'identity' => $identity,
					'pageTitle' => 'Uživatelé',
					'id' => $id,
					'post' => array_merge($user, $authorization, $post),
					'currentRole' => $post['role'] ?? $currentRole,
					'errors' => ['general' => $e->getMessage()],
				]));
			}
		}

		$post = array_merge($user, $authorization);

		return new Response($this->renderer->renderWithAdminLayout('user/admin/edit', [
			'identity' => $identity,
			'pageTitle' => 'Uživatelé',
			'id' => $id,
			'post' => $post,
			'currentRole' => $currentRole,
			'errors' => [],
		]));
	}

	public function deleteUser(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();

		try {
			$userId = $request->request->getInt('id');
			$user = $this->userRepository->findPostBy('id', $userId);

			if ($user) {
				// Smazat adresář s obrázky
				$dir = $this->PUBLIC_PATH . '/data/user/' . $user['id'] . '/';
				if (is_dir($dir)) {
					$this->deleteDir($dir);
				}

				$this->authorizationRepository->deletePost((int) $user['authorization_id']);

				$this->logger->notice('USER - Delete user', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);

				return new JsonResponse([
					'success' => true,
					'message' => null,
					'user_id' => $userId,
				]);
			}

			$this->logger->err('USER - Delete user', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => 'Uživatel nenalezen',
			]);

			return new JsonResponse([
				'success' => false,
				'message' => 'Uživatel nenalezen',
				'user_id' => $userId,
			]);
		} catch (\Exception $e) {
			$this->logger->err('USER - Delete user', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);

			return new JsonResponse([
				'success' => false,
				'message' => $e->getMessage(),
				'user_id' => null,
			]);
		}
	}

	public function uploadImage(Request $request): JsonResponse
	{
		$file = $request->files->get('file');

		if (!$file) {
			return new JsonResponse(['error' => 'Soubor nenalezen']);
		}

		$userId = $request->request->get('user_id');

		$folder = 'data/user/';
		if ($userId && $userId !== 'null') {
			$folder .= $userId . '/';
		} else {
			$folder .= 'tmp/';
		}

		if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
			mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
		}

		$ext = $file->guessExtension() ?: 'jpg';
		$filename = 'avatar-' . date('YmdHis') . '_' . random_int(100, 999) . '.' . $ext;
		$file->move($this->PUBLIC_PATH . '/' . $folder, $filename);
		$imageFileName = $folder . $filename;

		if ($userId && $userId !== 'null') {
			$user = $this->userRepository->findPostBy('id', (int) $userId);
			if ($user && $user['image'] !== 'data/user/!default-user.png') {
				$oldImage = $this->PUBLIC_PATH . '/' . $user['image'];
				if (file_exists($oldImage)) {
					unlink($oldImage);
				}
			}
			if ($user) {
				$this->userRepository->updatePost((int) $user['id'], ['image' => $imageFileName]);
			}
		}

		return new JsonResponse([
			'name' => $file->getClientOriginalName(),
			'url' => $imageFileName,
			'type' => $file->getClientMimeType(),
		]);
	}

	private function validateForm(array $post, bool $isNew): array
	{
		$errors = [];

		if (empty($post['username'])) {
			$errors['username'] = 'Email je povinný';
		}
		if ($isNew && empty($post['password'])) {
			$errors['password'] = 'Heslo je povinné';
		}
		if (!empty($post['password']) && !empty($post['password2']) && $post['password'] !== $post['password2']) {
			$errors['password2'] = 'Hesla se neshodují';
		}
		if (empty($post['role'])) {
			$errors['role'] = 'Role je povinná';
		}

		return $errors;
	}

	private function deleteDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		$items = array_diff(scandir($dir), ['.', '..']);
		foreach ($items as $item) {
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir($path) ? $this->deleteDir($path) : unlink($path);
		}
		rmdir($dir);
	}
}
