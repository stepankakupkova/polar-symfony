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
        tClose: 'Schließen (ESC)',
        tLoading: 'Wird geladen...',
        gallery: {
            tPrev: 'Zurück (linke Pfeiltaste)',
            tNext: 'Weiter (rechte Pfeiltaste)',
            tCounter: '%curr% von %total%'
        },
        image: {
            tError: '<a href="%url%">Das Bild</a> konnte nicht geladen werden.'
        },
        ajax: {
            tError: '<a href="%url%">Der Inhalt</a> konnte nicht geladen werden.'
        }
    });
}).apply(this, [jQuery]);