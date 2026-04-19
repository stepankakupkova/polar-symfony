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
            'id'     => 'zive-polar2',
            'label'  => '<img src="/img/web/layout/header/zive-polar2.svg" alt="Živě Polar 2" class="me-3">',
            'url'    => '/polar2',
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
                ['label' => 'Volby 2025',          'url' => '/volby-2025'],
                ['label' => 'Kam vyrazit',          'url' => '/kam-vyrazit'],
                ['label' => 'Nabídka práce',        'url' => '/nabidka-prace'],
                ['label' => 'Kamery',               'url' => '/kamery'],
                ['label' => 'Komerční sdělení',     'url' => '/zpravy/pr-clanky'],
                ['label' => 'TV program',           'url' => '/tv-program'],
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
            'id'     => 'menuFooterGdpr',
            'label'  => 'GDPR',
            'url'    => '/gdpr',
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
            'url'    => '/sitemap',
            'class'  => null,
            'target' => null,
            'icon'   => 'fa fa-fw fa-sitemap',
        ],
    ],
    'regions' => [
        [
            'id'     => 'region-all',
            'label'  => 'MS kraj',
            'url'    => '/zpravy',
            'cities' => [],
        ],
        [
            'id'    => 'region-ostrava',
            'label' => 'Ostrava',
            'url'   => '/zpravy/ostrava',
            'cities' => [
                ['label' => 'Ostrava-město',           'url' => '/zpravy/ostrava/ostrava-mesto'],
                ['label' => 'Ostrava-Centrum',         'url' => '/zpravy/ostrava/ostrava-centrum'],
                ['label' => 'Ostrava-Poruba',          'url' => '/zpravy/ostrava/ostrava-poruba'],
                ['label' => 'Ostrava-Vítkovice',       'url' => '/zpravy/ostrava/ostrava-vitkovice'],
                ['label' => 'Ostrava-Slezská Ostrava', 'url' => '/zpravy/ostrava/slezska-ostrava'],
                ['label' => 'Ostrava-Jih',             'url' => '/zpravy/ostrava/ostrava-jih'],
                ['label' => 'Ostrava-Mariánské hory',  'url' => '/zpravy/ostrava/ostrava-marianske-hory'],
                ['label' => 'Ostrava-Svinov',          'url' => '/zpravy/ostrava/ostrava-svinov'],
            ],
        ],
        [
            'id'    => 'region-karvinsko',
            'label' => 'Karvinsko',
            'url'   => '/zpravy/karvinsko',
            'cities' => [
                ['label' => 'Havířov',      'url' => '/zpravy/karvinsko/havirov'],
                ['label' => 'Karviná',      'url' => '/zpravy/karvinsko/karvina'],
                ['label' => 'Horní Suchá',  'url' => '/zpravy/karvinsko/horni-sucha'],
                ['label' => 'Rychvald',     'url' => '/zpravy/karvinsko/rychvald'],
                ['label' => 'Stonava',      'url' => '/zpravy/karvinsko/stonava'],
                ['label' => 'Těrlicko',     'url' => '/zpravy/karvinsko/terlicko'],
                ['label' => 'Dolní Lutyně', 'url' => '/zpravy/karvinsko/dolni-lutyne'],
                ['label' => 'Český Těšín',  'url' => '/zpravy/karvinsko/cesky-tesin'],
                ['label' => 'Orlová',       'url' => '/zpravy/karvinsko/orlova'],
                ['label' => 'Albrechtice',  'url' => '/zpravy/karvinsko/albrechtice'],
            ],
        ],
        [
            'id'    => 'region-frydeckomistecko',
            'label' => 'Frýdeckomístecko',
            'url'   => '/zpravy/frydeckomistecko',
            'cities' => [
                ['label' => 'Jablunkov',               'url' => '/zpravy/frydeckomistecko/jablunkov'],
                ['label' => 'Nošovice',                'url' => '/zpravy/frydeckomistecko/nosovice'],
                ['label' => 'Palkovice',               'url' => '/zpravy/frydeckomistecko/palkovice'],
                ['label' => 'Frýdek-Místek',           'url' => '/zpravy/frydeckomistecko/frydek-mistek'],
                ['label' => 'Čeladná',                 'url' => '/zpravy/frydeckomistecko/celadna'],
                ['label' => 'Frýdlant nad Ostravicí',  'url' => '/zpravy/frydeckomistecko/frydlant-nad-ostravici'],
            ],
        ],
        [
            'id'    => 'region-opavsko',
            'label' => 'Opavsko',
            'url'   => '/zpravy/opavsko',
            'cities' => [
                ['label' => 'Ludgeřovice', 'url' => '/zpravy/opavsko/ludgerovice'],
                ['label' => 'Opava',       'url' => '/zpravy/opavsko/opava'],
            ],
        ],
        [
            'id'    => 'region-novojicinsko',
            'label' => 'Novojičínsko',
            'url'   => '/zpravy/novojicinsko',
            'cities' => [
                ['label' => 'Studénka',   'url' => '/zpravy/novojicinsko/studenka'],
                ['label' => 'Nový Jičín', 'url' => '/zpravy/novojicinsko/novy-jicin'],
            ],
        ],
        [
            'id'    => 'region-bruntalsko',
            'label' => 'Bruntálsko',
            'url'   => '/zpravy/bruntalsko',
            'cities' => [
                ['label' => 'Bruntál', 'url' => '/zpravy/bruntalsko/bruntal'],
                ['label' => 'Krnov',   'url' => '/zpravy/bruntalsko/krnov'],
            ],
        ],
    ],
];
