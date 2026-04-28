<?php

declare(strict_types=1);

namespace App\Rss\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Program\Repository\ShowRepository;
use Symfony\Component\HttpFoundation\Response;

final class RssController
{
    public function __construct(
        private ShowRepository $showRepository,
    ) {}

    public function ostrava(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/ostrava.xml');
        return new Response($renderer->render('rss/web/ostrava', ['feed' => $feed]));
    }

    public function novyJicin(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/novy-jicin.xml');
        return new Response($renderer->render('rss/web/novy-jicin', ['feed' => $feed]));
    }

    public function stonava(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/stonava.xml');
        return new Response($renderer->render('rss/web/stonava', ['feed' => $feed]));
    }

    public function frydekMistek(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/frydek-mistek.xml');
        return new Response($renderer->render('rss/web/frydek-mistek', ['feed' => $feed]));
    }

    public function frydekMistek2(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/frydek-mistek.xml');
        return new Response($renderer->render('rss/web/frydek-mistek2', ['feed' => $feed]));
    }

    public function havirov(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/havirov.xml');
        return new Response($renderer->render('rss/web/havirov', ['feed' => $feed]));
    }

    public function karvina(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/karvina.xml');
        return new Response($renderer->render('rss/web/karvina', ['feed' => $feed]));
    }

    public function krnov(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/krnov.xml', 4);
        return new Response($renderer->render('rss/web/krnov', ['feed' => $feed]));
    }

    public function bruntal(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/bruntal.xml');
        return new Response($renderer->render('rss/web/bruntal', ['feed' => $feed]));
    }

    public function bruntal2(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/bruntal.xml', 6);
        return new Response($renderer->render('rss/web/bruntal2', ['feed' => $feed]));
    }

    public function opava(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/opava.xml');
        return new Response($renderer->render('rss/web/opava', ['feed' => $feed]));
    }

    public function orlova(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/orlova.xml');
        return new Response($renderer->render('rss/web/orlova', ['feed' => $feed]));
    }

    public function celadna(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/celadna.xml');
        return new Response($renderer->render('rss/web/celadna', ['feed' => $feed]));
    }

    public function studenka(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/studenka.xml');
        return new Response($renderer->render('rss/web/studenka', ['feed' => $feed]));
    }

    public function msk(PhtmlRenderer $renderer): Response
    {
        $shows = null;
        try {
            $shows = $this->showRepository->getShowsForRSS();
        } catch (\Exception $e) {
            //var_dump($e->getMessage());
        }
        return new Response($renderer->render('rss/web/msk', ['shows' => $shows]));
    }

    private function parseFeed(string $url, ?int $limit = null): array
    {
        $data = [
            'title'       => '',
            'link'        => '',
            'description' => '',
            'language'    => '',
            'content'     => [],
        ];

        try {
            $xml = @simplexml_load_string((string) file_get_contents($url));
            if (!$xml) {
                return $data;
            }

            $channel = $xml->channel;
            $data['title']       = (string) $channel->title;
            $data['link']        = (string) $channel->link;
            $data['description'] = (string) $channel->description;
            $data['language']    = (string) $channel->language;

            $i = 1;
            foreach ($channel->item as $item) {
                if ($limit !== null && $i > $limit) break;

                // Enclosure (image)
                $image = 'https://polar.cz/data/microformats/polar.jpg';
                if (isset($item->enclosure)) {
                    $attrs = $item->enclosure->attributes();
                    if (!empty($attrs['url'])) {
                        $image = (string) $attrs['url'];
                    }
                }

                // dateModified
                $dateModified = '';
                try {
                    $date = new \DateTime((string) $item->pubDate);
                    $dateModified = $date->format('d.m.Y');
                } catch (\Exception) {}

                $data['content'][] = [
                    'title'        => (string) $item->title,
                    'description'  => (string) $item->description,
                    'dateModified' => $dateModified,
                    'authors'      => (string) $item->author,
                    'link'         => (string) $item->link,
                    'content'      => (string) $item->children('content', true)->encoded ?? '',
                    'image'        => $image,
                ];
                $i++;
            }
        } catch (\Exception $e) {
            //var_dump($e->getMessage());
        }

        return $data;
    }
}
