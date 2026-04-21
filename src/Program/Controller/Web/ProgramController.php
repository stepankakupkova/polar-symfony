<?php

namespace App\Program\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Program\Repository\ProgramRepository;
use App\Program\Repository\VideoRepository;
use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Transliterator;

final class ProgramController
{
	public function program(
		PhtmlRenderer $renderer,
		VideoRepository $videoRepository,
	): Response
	{
		$mostWatchedShows = null;
		try {
			$mostWatchedShows = $videoRepository->getMostWatchedShowsForWeb(5);
		} catch (Exception) {
		}

		return new Response($renderer->renderWithLayout('program/web/program', [
			'mostWatchedShows' => $mostWatchedShows,
		]));
	}

	public function getProgramForWeb(
		Request $request,
		ProgramRepository $programRepository,
		UrlGeneratorInterface $urlGenerator,
	): JsonResponse
	{
		$date = $request->query->get('date', '');
		$content = '';
		$success = true;
		$id = null;

		try {
			$program = $programRepository->fetchForWeb($date);

			if ($program) {
				$i = 0;
				$ranges = array(
					1 => 4,
					2 => 4,
					3 => 4,
					4 => 4,
					5 => 4,
				);
				$backgrounds = array(
					1 => 'bg-primary',
					2 => 'bg-primary',
					3 => 'bg-00adee',
					4 => 'bg-00adee',
					5 => 'bg-secondary',
					6 => 'bg-secondary',
				);
				$now = new DateTime();
				$range = new DateTime($date . ' 00:00:00');
				$range2 = new DateTime($date . ' 04:00:00');
				$show = ((($now >= $range) && ($now <= $range2)) ? 'show' : '');
				$collapsed = ((($now >= $range) && ($now <= $range2)) ? '' : ' collapsed');
				$nextTime = false;
				$content .=
					'<div id="accordion" class="accordion accordion-modern-status accordion-modern-status-arrow">' .
					'<div class="card card-default mt-1">' .
					'<div class="card-header" id="collapse' . $i . 'Heading">' .
					'<h3 class="card-title m-0">' .
					'<a class="accordion-toggle text-color-light ' . $collapsed . ' ' . $backgrounds[$i + 1] . ' px-3 py-2" data-bs-toggle="collapse" data-bs-target="#collapse' . $i . '"href="#collapse' . $i . '">' .
					'<i class="fa fa-fw fa-clock"></i> ' .
					$range->format('H:i') .
					' - ' .
					$range2->format('H:i') .
					'</a>' .
					'</h3>' .
					'</div>' .
					'<div id="collapse' . $i . '" class="collapse ' . $show . '" data-bs-parent="#accordion">' .
					'<div class="card-body p-3">';

				foreach ($program as $item) {
					$time = new DateTime($item['time']);

					if ($time >= $range2) {
						$content .=
							'</div>' .
							'</div>' .
							'</div>';
						$i++;
						$range = new DateTime($range2->format('Y-m-d H:i:s'));
						$range2->modify('+' . $ranges[$i] . ' hours');
						$show = ((($now >= $range) && ($now <= $range2)) ? 'show' : '');
						$collapsed = ((($now >= $range) && ($now <= $range2)) ? '' : ' collapsed');
						$content .=
							'<div class="card card mt-1">' .
							'<div class="card-header" id="collapse' . $i . 'Heading">' .
							'<h3 class="card-title m-0">' .
							'<a class="accordion-toggle text-color-light ' . $collapsed . ' ' . $backgrounds[$i + 1] . ' px-3 py-2" data-bs-toggle="collapse" data-bs-target="#collapse' . $i . '"href="#collapse' . $i . '">' .
							'<i class="fa fa-fw fa-clock"></i> ' .
							$range->format('H:i') .
							' - ' .
							$range2->format('H:i') .
							'</a>' .
							'</h3>' .
							'</div>' .
							'<div id="collapse' . $i . '" class="collapse ' . $show . '" data-bs-parent="#accordion">' .
							'<div class="card-body p-3">';
					}

					// Text na UL->LI
					$description = $item['short_description'];
					if ($description && str_contains($description, PHP_EOL)) {
						$tmpDescription = '';
						foreach (explode(PHP_EOL, $description) as $row) {
							$tmpDescription .= trim(preg_replace('/\s\s+/', ' ', $row)) . '; ';
						}
						$description = substr($tmpDescription, 0, -2);
					}

					$content .=
						'<div id="item-' . $this->removeAccent($item['time'], '-') . '" class="item">' .
						'<div class="row">';
					if ($item['video_name'] && $item['show_id'] && $item['show_url'] && ($time < $now)) {
						$content .=
							'<div class="col-12">' .
							'<h4 class="text-4 font-weight-500 text-primary mb-0">' .
							'<span class="time">' .
							$time->format('H:i') .
							'</span>' .
							'&nbsp;&nbsp;<i class="fa fa-angle-right"></i>&nbsp;&nbsp;' .
							'<a class="title" href="' . $urlGenerator->generate('program_show_' . $item['show_id'], ['url' => $item['show_url'], 'program_url' => $item['url']]) . '" title="' . $item['title'] . '">' .
							$item['title'] .
							(($item['premiere']) ? ' (P)' : '') .
							'</a>' .
							'</h4>' .
							'<p class="text-color-secondary mb-3">' .
							$description .
							'</p>' .
							'</div>';
					} else {
						$content .=
							'<div class="col-12">' .
							'<h4 class="text-4 font-weight-500 text-primary mb-0">' .
							'<span class="time">' .
							$time->format('H:i') .
							'</span>' .
							'&nbsp;&nbsp;<i class="fa fa-angle-right"></i>&nbsp;&nbsp;' .
							$item['title'] .
							(($item['premiere']) ? ' (P)' : '') .
							'</h4>' .
							'<p class="text-color-secondary mb-3">' .
							$description .
							'</p>' .
							'</div>';
					}
					$content .=
						'</div>' .
						'</div>';
					if (($now > $time) && ($now->format('Y-m-d') === $time->format('Y-m-d'))) {
						$id = $this->removeAccent($item['time'], '-');
					} else if (!$nextTime) {
						$nextTime = strtotime($time->format('Y-m-d H:i:s'));
						$nextTime -= strtotime($now->format('Y-m-d H:i:s'));
					}
				}
				$content .=
					'</div>' .
					'</div>' .
					'</div>' .
					'</div>';
			} else {
				$content =
					'<div class="alert alert-warning" role="alert">' .
					'The program not found.' .
					'</div>';
			}
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'date' => $date,
			'active' => $id ?? null,
			'content' => $content,
		]);
	}

	public function getProgram2ForWeb(
		ProgramRepository $programRepository,
	): JsonResponse
	{
		$success = true;
		$message = null;
		$content = null;
		$reload = null;

		try {
			$program = $programRepository->getProgram2FromNow();

			if ($program) {
				$content = '';
				$i = 1;
				$first = true;
				foreach ($program as $day) {
					$content .=
						'<div id="program2-' . $i . '" class="nano' . ($first ? '' : ' hide') . '">' .
						'<div class="nano-content">';
					foreach ($day as $item) {
						$datetime = new DateTime($item['time']);
						$content .=
							'<div class="item font-weight-bold' . ($first ? ' text-color-secondary' : '') . '">' .
							$datetime->format('H:i') . ' <i class="fa fa-angle-right"></i> ' . $item['title'] .
							'</div>' .
							'<div class="description text-muted mb-2 line-height-2">' .
							$item['short_description'] .
							'</div>';
						$first = false;
					}
					$content .=
						'</div>' .
						'</div>';
					$i++;
				}
				$reload = new DateTime();
				$nextItemTime = new DateTime($program[$reload->format('Y-m-d')][1]['time']);
				$interval = $nextItemTime->diff($reload);
				$reload = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
				// přičíst vteřinu pro správný reload
				$reload++;
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'content' => $content,
			'reload' => $reload,
			'success' => $success,
			'message' => $message,
		]);
	}

	public function getProgramForHd(
		ProgramRepository $programRepository,
	): JsonResponse
	{
		$success = true;
		$message = null;
		$content = null;
		$reload = null;

		try {
			$program = $programRepository->getProgramFromNow();

			if ($program) {
				$content = '';
				$i = 1;
				$first = true;
				foreach ($program as $day) {
					$content .=
						'<div id="program-' . $i . '" class="nano' . ($first ? '' : ' hide') . '">' .
						'<div class="nano-content">';
					foreach ($day as $item) {
						$datetime = new DateTime($item['time']);
						$content .=
							'<div class="item font-weight-bold' . ($first ? ' text-color-secondary' : '') . '">' .
							$datetime->format('H:i') . ' <i class="fa fa-angle-right"></i> ' . $item['title'] .
							'</div>' .
							'<div class="description text-muted mb-2 line-height-2">' .
							$item['short_description'] .
							'</div>';
						$first = false;
					}
					$content .=
						'</div>' .
						'</div>';
					$i++;
				}
				$reload = new DateTime();
				$nextItemTime = new DateTime($program[$reload->format('Y-m-d')][1]['time']);
				$interval = $nextItemTime->diff($reload);
				$reload = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
				// přičíst vteřinu pro správný reload
				$reload++;
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'content' => $content,
			'reload' => $reload,
			'success' => $success,
			'message' => $message,
		]);
	}

	private function removeAccent(string $text, ?string $replace = null): string
	{
		$transliterator = Transliterator::createFromRules(':: Any-Latin; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC; :: [:Punctuation:] Remove; :: Lower();', Transliterator::FORWARD);
		$textTmp = $text;
		if ($transliterator) {
			$textTmp = $transliterator->transliterate($text);
			$textTmp = preg_replace('/[^a-z0-9]+/', '-', $textTmp);
			$textTmp = strtolower($textTmp);
			if ($replace) {
				$textTmp = str_replace(' ', $replace, $textTmp);
			}
		}
		return $textTmp;
	}
}
