<?php

namespace App\Job\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Job\Repository\JobOurRepository;
use App\Job\Repository\JobRepository;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class JobListController
{
	public function index(
		PhtmlRenderer $renderer,
		JobRepository $jobRepository,
		JobOurRepository $jobOurRepository,
	): Response
	{
		return new Response($renderer->renderWithAdminLayout('job/admin/job/index', [
			'pageTitle' => 'Jobs',
			'countJob' => $jobRepository->getCount(),
			'countJobOurs' => $jobOurRepository->getCount(),
		]));
	}

	public function list(PhtmlRenderer $renderer): Response
	{
		return new Response($renderer->renderWithAdminLayout('job/admin/job/list', [
			'pageTitle' => 'Jobs',
		]));
	}

	public function getList(Request $request, JobRepository $jobRepository): JsonResponse
	{
		$params = $request->query->all();
		$rows = null;
		$total = 0;
		$success = true;

		try {
			$rows = $jobRepository->fetchForBootstrapTable($params);
			$total = $jobRepository->getCountForBootstrapTable($params);
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'rows' => $rows,
			'total' => $total,
		]);
	}

	public function getListOurs(Request $request, JobOurRepository $jobOurRepository): JsonResponse
	{
		$params = $request->query->all();
		$rows = null;
		$total = 0;
		$success = true;

		try {
			$rows = $jobOurRepository->fetchForBootstrapTable($params);
			$total = $jobOurRepository->getCountForBootstrapTable($params);
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'rows' => $rows,
			'total' => $total,
		]);
	}
}
