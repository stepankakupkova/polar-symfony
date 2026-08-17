<?php

/**
 * Statická navigace — regiony a města MS kraje
 * Vychází z polar/module/News/config/news.config.php
 * URL formát: /zpravy/{region_url}/{city_url}
 */
return [
    'submenu' => [
        [
            'id'     => 'zive-polar',
            'label'  => '<img src="/img/web/layout/header/zive-polar.svg" alt="Živě Polar">',
            'url'    => '/hd',
            'class'  => '',
            'order'  => 1,
        ],
        [
            'id'    => 'show',
            'label' => 'Pořady',
            'url'   => '/porady',
            'class' => '',
            'order' => 1,
        ],
        [
            'id'     => 'multimedia',
            'label'  => 'Multimedia',
            'url'    => 'https://polarmultimedia.cz/',
            'target' => '_blank',
            'class'  => '',
            'order'  => 3,
        ],
        [
            'id'    => 'menuPageWeb-15',
            'label' => null,
            'url'   => '#',
            'class' => 'p-0',
            'dropdown' => [
                ['label' => 'Volby 2025',           'url' => '/volby',              'order' => 3],
                ['label' => 'Kam vyrazit',          'url' => '/kam-vyrazit',        'order' => 5],
                ['label' => 'Nabídka práce',        'url' => '/nabidka-prace',      'order' => 6],
                ['label' => 'Kamery',               'url' => '/kamery',             'order' => 7],
                ['label' => 'Komerční sdělení',     'url' => '/zpravy/pr-clanky',   'order' => 8],
                ['label' => 'TV program',           'url' => '/program',            'order' => 9],
            ],
        ],
    ],
    'footer' => [
        [
            'id'     => null,
            'label'  => 'Nastavení personalizace',
            'url'    => '?cmpscreen',
            'class'  => null,
            'target' => null,
            'icon'   => null,
        ],
        [
            'id'     => 'menuFooterAccessibility',
            'label'  => 'Prohlášení o přístupnosti',
            'url'    => '/prohlaseni-o-pristupnosti',
            'class'  => null,
            'target' => null,
            'icon'   => null,
        ],
        [
            'id'     => 'menuFooterGdpr',
            'label'  => 'GDPR',
            'url'    => '/data/docs/ochrana_osobnich_udaju_polar.pdf',
            'class'  => null,
            'target' => '_blank',
            'icon'   => null,
        ],
        [
            'id'     => 'menuFooterRss',
            'label'  => 'RSS',
            'url'    => '/rss',
            'class'  => null,
            'target' => null,
            'icon'   => 'fa fa-fw fa-rss',
        ],
        [
            'id'     => 'menuFooterSitemap',
            'label'  => 'Mapa stránek',
            'url'    => '/mapa-stranek',
            'class'  => null,
            'target' => null,
            'icon'   => 'fa fa-fw fa-sitemap',
        ],
    ],
    'regions' => require __DIR__ . '/news_navigation.php',
];
