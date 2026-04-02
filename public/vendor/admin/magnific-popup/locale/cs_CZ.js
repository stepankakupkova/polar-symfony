/*
 * @project rogr
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

(function($) {
    'use strict';
    $.extend(true, $.magnificPopup.defaults, {
        tClose: 'Zavřít (Esc)',
        tLoading: 'Nahrávám...',
        gallery: {
            tPrev: 'Předchozí (Levá šipka)',
            tNext: 'Další (Pravá šipka)',
            tCounter: '%curr% ze %total%'
        },
        image: {
            tError: '<a href="%url%">Obrázek</a> nelze načíst.'
        },
        ajax: {
            tError: '<a href="%url%">Obsah</a> nelze načíst.'
        }
    });
}).apply(this, [jQuery]);