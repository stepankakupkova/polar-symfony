<?php

namespace App\User\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Authorization\Repository\AuthorizationRepository;
use App\Security\User;
use App\User\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UserListController
{
	public function __construct(
		private UserRepository $userRepository,
		private AuthorizationRepository $authorizationRepository,
		private PhtmlRenderer $renderer,
		private Security $security,
	) {}

	public function index(): Response
	{
		/** @var User $identity */
		$identity = $this->security->getUser();

		return new Response($this->renderer->renderWithAdminLayout('user/admin/index', [
			'identity' => $identity,
			'pageTitle' => 'Uživatelé',
			'countUsers' => $this->userRepository->getCount(),
			'countUsersActive' => $this->userRepository->getCount(true),
		]));
	}

	public function list(): Response
	{
		/** @var User $identity */
		$identity = $this->security->getUser();

		return new Response($this->renderer->renderWithAdminLayout('user/admin/list', [
			'identity' => $identity,
			'pageTitle' => 'Uživatelé',
		]));
	}

	public function getList(Request $request): JsonResponse
	{
		$params = $request->query->all();

		try {
			$rows = $this->userRepository->fetchForBootstrapTable($params);
			$total = $this->userRepository->getCountForBootstrapTable($params);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => $e->getMessage(),
				'rows' => null,
				'total' => 0,
			]);
		}

		return new JsonResponse([
			'success' => true,
			'rows' => $rows,
			'total' => $total,
		]);
	}

	public function getUser(Request $request): JsonResponse
	{
		try {
			$userId = $request->request->getInt('id');
			$user = $this->userRepository->findPostBy('id', $userId);
			$authorization = $this->authorizationRepository->findPostBy('id', $user['authorization_id']);

			$user['username'] = $authorization['username'];

			return new JsonResponse([
				'success' => true,
				'message' => null,
				'user' => $user,
			]);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => false,
				'message' => $e->getMessage(),
				'user' => null,
			]);
		}
	}
}
