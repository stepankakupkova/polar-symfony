<?php

namespace App\Job\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Job\Repository\JobOurRepository;
use App\Job\Repository\JobRepository;
use App\News\Repository\NewsRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class JobController
{
	private const OKRESY_URL = [
		3807 => 'ostrava',
		3803 => 'karvina',
		3802 => 'frydek-mistek',
		3806 => 'opava',
		3804 => 'novy-jicin',
		3801 => 'bruntal',
	];

	private const OKRESY_NAZEV = [
		'ostrava'       => 'Ostravsko',
		'karvina'       => 'Karvinsko',
		'frydek-mistek' => 'Frýdeckomístecko',
		'opava'         => 'Opavsko',
		'novy-jicin'    => 'Novojíčínsko',
		'bruntal'       => 'Bruntálsko',
	];

	public function jobs(
		Request $request,
		PhtmlRenderer $renderer,
		JobRepository $jobRepository,
		JobOurRepository $jobOurRepository,
		NewsRepository $newsRepository,
	): Response
	{
		$page = max(1, $request->query->getInt('strana', 1));
		$limit = 10;

		$oboryCinnosti = $jobRepository->getAllOboryCinnostiVmForMenu();
		$pracovnepravniVztahy = $jobRepository->getAllPracovnepravniVztahyForMenu();
		$vzdelaniAll = $jobRepository->getAllVzdelaniForMenu();

		$obor_cinnosti_vm = null;
		$obor_cinnosti_vm_code = null;
		$obor_cinnosti_vm_pos = 5;
		$obory_cinnosti_vm = $oboryCinnosti;
		if ($oUrl = $request->query->get('o')) {
			$oUrl = preg_replace('/[^a-z0-9\-]/', '', strtolower($oUrl));
			$obor_cinnosti_vm = $jobRepository->getOborCinnostiVmByUrl($oUrl);
			if ($obor_cinnosti_vm) {
				$obor_cinnosti_vm_code = $obor_cinnosti_vm['kod'];
				$obor_cinnosti_vm_pos = 1 + (int) array_search($oUrl, array_column($oboryCinnosti, 'url'), true);
				if ($obor_cinnosti_vm_pos < 5) {
					$obor_cinnosti_vm_pos = 5;
				}
			}
		}

		$pracovnepravni_vztah = null;
		$pracovnepravni_vztah_code = null;
		if ($pUrl = $request->query->get('p')) {
			$pUrl = preg_replace('/[^a-z0-9\-]/', '', strtolower($pUrl));
			$pracovnepravni_vztah = $jobRepository->getPracovnepravniVztahByUrl($pUrl);
			if ($pracovnepravni_vztah) {
				$pracovnepravni_vztah_code = $pracovnepravni_vztah['kod'];
			}
		}

		$vzdelani = null;
		$vzdelani_code = null;
		$vzdelani_pos = 5;
		if ($vUrl = $request->query->get('v')) {
			$vUrl = preg_replace('/[^a-z0-9\-]/', '', strtolower($vUrl));
			$vzdelani = $jobRepository->getVzdelaniByUrl($vUrl);
			if ($vzdelani) {
				$vzdelani_code = $vzdelani['kod'];
				$vzdelani_pos = 1 + (int) array_search($vUrl, array_column($vzdelaniAll, 'url'), true);
				if ($vzdelani_pos < 5) {
					$vzdelani_pos = 5;
				}
			}
		}

		$paginator = $jobRepository->getPaginator(132, null, $page, $limit, $obor_cinnosti_vm_code, $pracovnepravni_vztah_code, $vzdelani_code);
		$jobs = $paginator['items'];
		$total = $paginator['total'];
		$jobsOur = $jobOurRepository->fetchRandForWeb(5);

		$pr = $newsRepository->getPrArticles(7);

		return new Response($renderer->renderWithLayout('job/web/jobs', [
			'jobs' => $jobs,
			'jobsOur' => $jobsOur,
			'page' => $page,
			'total' => $total,
			'limit' => $limit,
			'obor_cinnosti_vm' => $obor_cinnosti_vm,
			'obor_cinnosti_vm_pos' => $obor_cinnosti_vm_pos,
			'obory_cinnosti_vm' => $obory_cinnosti_vm,
			'pracovnepravni_vztah' => $pracovnepravni_vztah,
			'pracovnepravni_vztahy' => $pracovnepravniVztahy,
			'vzdelani' => $vzdelani,
			'vzdelani_pos' => $vzdelani_pos,
			'vzdelani_all' => $vzdelaniAll,
			'okresy_url' => self::OKRESY_URL,
			'pr' => $pr,
		]));
	}

	public function city(
		string $city_url,
		Request $request,
		PhtmlRenderer $renderer,
		JobRepository $jobRepository,
		NewsRepository $newsRepository,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		$page = max(1, $request->query->getInt('strana', 1));
		$limit = 10;

		$okres_id = array_search($city_url, self::OKRESY_URL);
		if ($okres_id === false) {
			return new RedirectResponse($urlGenerator->generate('job_list'));
		}

		$oboryCinnosti = $jobRepository->getAllOboryCinnostiVmForMenu();
		$pracovnepravniVztahy = $jobRepository->getAllPracovnepravniVztahyForMenu();
		$vzdelaniAll = $jobRepository->getAllVzdelaniForMenu();

		$obor_cinnosti_vm = null;
		$obor_cinnosti_vm_code = null;
		$obor_cinnosti_vm_pos = 5;
		$obory_cinnosti_vm = $oboryCinnosti;
		if ($oUrl = $request->query->get('o')) {
			$oUrl = preg_replace('/[^a-z0-9\-]/', '', strtolower($oUrl));
			$obor_cinnosti_vm = $jobRepository->getOborCinnostiVmByUrl($oUrl);
			if ($obor_cinnosti_vm) {
				$obor_cinnosti_vm_code = $obor_cinnosti_vm['kod'];
				$obor_cinnosti_vm_pos = 1 + (int) array_search($oUrl, array_column($oboryCinnosti, 'url'), true);
				if ($obor_cinnosti_vm_pos < 5) {
					$obor_cinnosti_vm_pos = 5;
				}
			}
		}

		$pracovnepravni_vztah = null;
		$pracovnepravni_vztah_code = null;
		if ($pUrl = $request->query->get('p')) {
			$pUrl = preg_replace('/[^a-z0-9\-]/', '', strtolower($pUrl));
			$pracovnepravni_vztah = $jobRepository->getPracovnepravniVztahByUrl($pUrl);
			if ($pracovnepravni_vztah) {
				$pracovnepravni_vztah_code = $pracovnepravni_vztah['kod'];
			}
		}

		$vzdelani = null;
		$vzdelani_code = null;
		$vzdelani_pos = 5;
		if ($vUrl = $request->query->get('v')) {
			$vUrl = preg_replace('/[^a-z0-9\-]/', '', strtolower($vUrl));
			$vzdelani = $jobRepository->getVzdelaniByUrl($vUrl);
			if ($vzdelani) {
				$vzdelani_code = $vzdelani['kod'];
				$vzdelani_pos = 1 + (int) array_search($vUrl, array_column($vzdelaniAll, 'url'), true);
				if ($vzdelani_pos < 5) {
					$vzdelani_pos = 5;
				}
			}
		}

		$paginator = $jobRepository->getPaginator(132, $okres_id, $page, $limit, $obor_cinnosti_vm_code, $pracovnepravni_vztah_code, $vzdelani_code);
		$jobs = $paginator['items'];
		$total = $paginator['total'];

		$pr = $newsRepository->getPrArticles(7);

		return new Response($renderer->renderWithLayout('job/web/city', [
			'jobs' => $jobs,
			'page' => $page,
			'total' => $total,
			'limit' => $limit,
			'city_url' => $city_url,
			'city_name' => self::OKRESY_NAZEV[$city_url] ?? $city_url,
			'obor_cinnosti_vm' => $obor_cinnosti_vm,
			'obor_cinnosti_vm_pos' => $obor_cinnosti_vm_pos,
			'obory_cinnosti_vm' => $obory_cinnosti_vm,
			'pracovnepravni_vztah' => $pracovnepravni_vztah,
			'pracovnepravni_vztahy' => $pracovnepravniVztahy,
			'vzdelani' => $vzdelani,
			'vzdelani_pos' => $vzdelani_pos,
			'vzdelani_all' => $vzdelaniAll,
			'okresy_url' => self::OKRESY_URL,
			'pr' => $pr,
		]));
	}

	public function job(
		string $city_url,
		int $job_id,
		PhtmlRenderer $renderer,
		JobRepository $jobRepository,
		JobOurRepository $jobOurRepository,
		NewsRepository $newsRepository,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		$job = $jobRepository->getForWeb($job_id);
		if (!$job) {
			return new RedirectResponse($urlGenerator->generate('job_list'));
		}

		$joboffersManual = $jobOurRepository->fetchRandForWeb(5);
		$pr = $newsRepository->getPrArticles(7);

		$cityMap = [
			'ostrava'       => 'Ostravsko',
			'karvina'       => 'Karvinsko',
			'frydek-mistek' => 'Frýdeckomístecko',
			'opava'         => 'Opavsko',
			'novy-jicin'    => 'Novojíčínsko',
			'bruntal'       => 'Bruntálsko',
		];

		return new Response($renderer->renderWithLayout('job/web/job', [
			'job' => $job,
			'city_url' => $city_url,
			'cityName' => $cityMap[$city_url] ?? '',
			'joboffersManual' => $joboffersManual,
			'pr' => $pr,
		]));
	}

	public function jobOur(
		int $job_id,
		PhtmlRenderer $renderer,
		JobOurRepository $jobOurRepository,
		NewsRepository $newsRepository,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		$job = $jobOurRepository->getPostForWeb($job_id);
		if (!$job) {
			return new RedirectResponse($urlGenerator->generate('job_list'));
		}

		$pr = $newsRepository->getPrArticles(7);

		return new Response($renderer->renderWithLayout('job/web/job-our', [
			'job' => $job,
			'pr' => $pr,
		]));
	}
}
