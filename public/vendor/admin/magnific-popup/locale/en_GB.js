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
        tClose: 'Close (Esc)',
        tLoading: 'Loading...',
        gallery: {
            tPrev: 'Previous (Left arrow key)',
            tNext: 'Next (Right arrow key)',
            tCounter: '%curr% of %total%'
        },
        image: {
            tError: '<a href="%url%">The image</a> could not be loaded.'
        },
        ajax: {
            tError: '<a href="%url%">The content</a> could not be loaded.'
        }
    });
}).apply(this, [jQuery]);