/*
 * @project rogr
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

(function($) {

    'use strict';

    if (window.sessionStorage.getItem("scheme") === null) {
        // Tmavé schéma
        if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
            let scheme = "dark";
            setTheme(scheme);
        }
    }

    function setTheme(scheme)
    {
        let html = $("html");
        // Logo
        let logo = $("header.header a.logo img");
        let logoSide = $("aside#sidebar-left a.logo img");

        if (scheme === "dark") {
            html.addClass("dark").removeClass("light sidebar-light");
        } else {
            html.addClass("light sidebar-light").removeClass("dark");
        }
        logo.attr("src", "/img/admin/logo-" + scheme + ".svg");
        logoSide.attr("src", "/img/admin/logo-short-" + scheme + ".svg");

        $.post('/admin/json-write/set-scheme',
            {
                scheme: scheme
            },
            function(json) {
                if (json.success) {
                    window.sessionStorage.setItem("scheme", scheme);
                } else {
                    alert ("Error");
                }
            },
            'json'
        );
    }

    // Barevné schéma
    $("#btnThemeChange").on("click", function (event) {
        event.preventDefault();
        let scheme = "dark";
        let html = $("html");

        if (html.hasClass("dark")) {
            scheme = "light";
        }
        setTheme(scheme);
    });

    // Form validate
    $("form").each(function() {
        $(this).validate({
            highlight: function(element) {
                $(element)
                    .addClass('is-invalid')
                    .removeClass('is-valid')
                    .parent()
                    .removeClass('has-success')
                    .addClass('has-danger');
            },
            success: function(label, element) {
                $(element)
                    .removeClass('is-invalid')
                    .addClass('is-valid')
                    .parent()
                    .removeClass('has-danger')
                    .addClass('has-success')
                    .find('label.error')
                    .remove();
            },
            errorPlacement: function(error, element) {
                if (element.attr('type') === 'radio' || element.attr('type') === 'checkbox') {
                    error.appendTo(element.parent().parent());
                } else if (element.is('select')) {
                    error.insertAfter(element.parent());
                } else if (element.parent().hasClass("input-group")) {
                    error.insertAfter(element.parent());
                } else {
                    error.insertAfter(element);
                }
            }
        });
    });
    $('form select.selectpicker').on('change', function() {
        $(this).valid();
    });
    
    $("label.required").append("<i class=\"fa fa-asterisk\"></i>")

    // MagnificPopup
    $.extend(true, $.magnificPopup.defaults, {
        closeOnContentClick: true,
        closeBtnInside: false,
        fixedContentPos: true,
        mainClass: "mfp-no-margins mfp-with-zoom"
    });
    $(document).on("click", ".modal-dismiss", function (e) {
        e.preventDefault();
        $.magnificPopup.close();
    });

    // PNotify
    if ( typeof PNotify != 'undefined' ) {
        $.extend(true, PNotify.prototype.options, {
            addclass: "click-2-close",
            buttons: {
                sticker: false
            }
        });
    }

    // Bootstrap Table
    if (typeof($.fn.bootstrapTable) !== 'undefined') {
        $.extend($.fn.bootstrapTable.defaults, {
            sortClass: 'text-primary',
            undefinedText: '',
            iconsPrefix: 'fa',
            icons: {
                paginationSwitchDown: 'fa-chevron-down',
                paginationSwitchUp: 'fa-chevron-up',
                refresh: 'fa-sync',
                toggleOff: 'fa-toggle-off',
                toggleOn: 'fa-toggle-on',
                columns: 'fa-th-list',
                detailOpen: 'fa-fw fa-lg fa-info mt-1 mb-1',
                detailClose: 'fa-fw fa-lg fa-info-circle mt-1 mb-1',
                fullscreen: 'fa-expand-arrows-alt',
                search: 'fa-search',
                clearSearch: 'fa-trash-alt',
                print: 'fa-print'
            },
            contentType: 'application/json; charset=utf-8',
            pagination: true,
            pageSize: 10,
            pageList: [10, 25, 50, 100],
            sidePagination: 'server',
            search: true,
            showColumns: true,
            showRefresh: true,
            showFullscreen: true,
            showPrint: (!$.browser.mobile),
            paginationPreText: '<i class="fa fa-chevron-left"></i>',
            paginationNextText: '<i class="fa fa-chevron-right"></i>',
            cookie: true,
            cookieExpire: '14d',
            onPostBody: function(){
                $('.fa').parents("td").css("padding", "6px 3px 4px 3px");
                $('.btn .fa').parents("td").css("padding", "8px");
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
        $.extend($.fn.bootstrapTable.columnDefaults, {
            //valign: 'top'
        });

        function emailFormatter(value) {
            if (value) {
                return '<a href="mailto:' + value + '" title="">' + value + '</a>';
            }
            return null;
        }

        function phoneFormatter(value) {
            if (value) {
                return '<a href="tel:' + value.replace(/\s/g, "").replace("(", "").replace(")", "") + '" title="">' + value + '</a>';
            }
            return null;
        }
    }

    // Bootstrap Select
    if (typeof($.fn.selectpicker) !== 'undefined') {
        $.extend($.fn.selectpicker.defaults, {
            style: 'btn-default',
            iconBase: 'fa fa-fw',
            tickIcon: 'fa-check',
            showTick: true
        });
    }

    // Widget Summary
    $(".widget-summary .summary-icon").on("click", function (e) {
        e.preventDefault();
        let link = $(this).closest(".widget-summary").find(".summary-footer a");
        if (link.length) {
            $(location).attr("href", link.attr("href"));
        }
    });

    // Bootstrap Maxlength
    $.extend(theme.PluginMaxLength.defaults, {
        alwaysShow: true,
        appendToParent: true,
        placement: 'top',
        warningClass: 'badge badge-success',
        limitReachedClass: 'badge badge-danger'
    });

    // Zobrazení NENÍ ULOŽENO
    $("form input, form textarea").keyup(function (){
        $("#msgNotSaved").removeClass("d-none");
    });
    $("form select").on("changed.bs.select", function (e, clickedIndex, isSelected, previousValue){
        $("#msgNotSaved").removeClass("d-none");
    });
    $("form [data-plugin-datepicker]").on("changeDate", function(e) {
        $("#msgNotSaved").removeClass("d-none");
    });

    // Zobrazení varování po kliknutí na odkaz "showWarning"
    function showWarning() {
        $(".showWarning").on("click", function (e) {
            let msgEl = $("#msgNotSaved");
            if (msgEl.length && !msgEl.hasClass("d-none")) {
                e.preventDefault();
                $("#modalNotSaved .panel-footer a").attr("href", $(this).attr("href"));
                $.magnificPopup.open({items: {src: "#modalNotSaved"}});
            }
        });
    }
    showWarning();

}).apply(this, [jQuery]);

// Left menu
if (typeof localStorage !== 'undefined') {
    if (localStorage.getItem('sidebar-left-position') !== null) {
        let sidebarLeft = document.querySelector('#sidebar-left .nano-content');
        sidebarLeft.scrollTop = parseInt(localStorage.getItem('sidebar-left-position'), 10);
    }
}

// Tooltip and Popover
(function($) {
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="popover"]').popover();
})(jQuery);

// Tabs
$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    $(this).parents('.nav-tabs').find('.active').removeClass('active');
    $(this).parents('.nav-pills').find('.active').removeClass('active');
    $(this).addClass('active').parent().addClass('active');
});

// Bootstrap Datepicker
if (typeof($.fn.datepicker) != 'undefined') {
    $.fn.bootstrapDP = $.fn.datepicker.noConflict();
}

//NUMBER FORMAT
function number_format(number, decimals, dec_point, thousands_sep) {
    number = (number + "").replace(/[^0-9+\-Ee.]/g, "");
    let n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === "undefined") ? "," : thousands_sep,
        dec = (typeof dec_point === "undefined") ? "." : dec_point,
        s = "",
        toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return "" + (Math.round(n * k) / k).toFixed(prec);
        };
    // Fix for IE parseFloat(0.55).toFixed(0) = 0;
    s = (prec ? toFixedFix(n, prec) : "" + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || "").length < prec) {
        s[1] = s[1] || "";
        s[1] += new Array(prec - s[1].length + 1).join("0");
    }
    return s.join(dec);
}