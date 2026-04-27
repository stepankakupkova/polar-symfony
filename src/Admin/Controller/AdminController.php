<?php

namespace App\Admin\Controller;

use App\Application\View\PhtmlRenderer;
use App\Camera\Repository\CameraRepository;
use App\Election\Repository\ElectionRepository2025;
use App\Job\Repository\JobOurRepository;
use App\Job\Repository\JobRepository;
use App\Page\Repository\PageRepository;
use App\Program\Repository\ProgramRepository;
use App\Program\Repository\ShowRepository;
use App\Program\Repository\VideoRepository;
use App\User\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;

final class AdminController
{
	public function dashboard(
		PhtmlRenderer $renderer,
		ProgramRepository $programRepository,
		VideoRepository $videoRepository,
		ShowRepository $showRepository,
		CameraRepository $cameraRepository,
		JobRepository $jobRepository,
		JobOurRepository $jobOurRepository,
		PageRepository $pageRepository,
		UserRepository $userRepository,
		ElectionRepository2025 $electionRepository,
	): Response
	{
		return new Response($renderer->renderWithAdminLayout('admin/dashboard', [
			'pageTitle' => 'Přehled',
			'countProgram' => $programRepository->getCount(),
			'countProgramPremiere' => $programRepository->getCount(true),
			'countVideo' => $videoRepository->getCount(),
			'countShow' => $showRepository->getCount(),
			'countShowActive' => $showRepository->getCount(true),
			'countCamera' => $cameraRepository->getCount(),
			'countJob' => $jobRepository->getCount(),
			'countJobOurs' => $jobOurRepository->getCount(),
			'countPage' => $pageRepository->getCount(),
			'countPageActive' => $pageRepository->getCount(true),
			'countUsers' => $userRepository->getCount(false),
			'countUsersActive' => $userRepository->getCount(true),
			'countElection' => $electionRepository->getCount(),
		]));
	}
}

