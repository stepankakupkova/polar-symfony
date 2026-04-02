/*
 * @project rogr
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

(function($) {

    'use strict';

    // Bootstrap Maxlength
    if (typeof($.fn.adminPluginMaxLength) !== 'undefined') {
        $.extend(admin.PluginMaxLength.defaults, {
            alwaysShow: true,
            appendToParent: true,
            placement: 'top',
            warningClass: 'badge badge-success',
            limitReachedClass: 'badge badge-danger'
        });
    }

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
                } else if (element.is('select') || element.parent().hasClass("input-group")) {
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

    // Bootstrap-table
    if (typeof($.fn.bootstrapTable) !== 'undefined') {
        $.extend($.fn.bootstrapTable.defaults, {
            sortClass: 'text-danger',
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
            paginationPreText: '<i class="fa fa-chevron-left"></i>',
            paginationNextText: '<i class="fa fa-chevron-right"></i>',
            search: true,
            showSearchButton: true,
            showSearchClearButton: true,
            smartDisplay: true,
            showColumns: true,
            showRefresh: true,
            showFullscreen: true,
            showPrint: (!$.browser.mobile),
            cookie: true,
            cookieExpire: '14d',
            onPostBody: function(){
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
        $.extend($.fn.bootstrapTable.columnDefaults, {
            //valign: 'top'
        });
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

    // Notifications
    if ( typeof PNotify != 'undefined' ) {
        PNotify.prototype.options.styling = "fontawesome";

        $.extend(true, PNotify.prototype.options, {
            shadow: false,
            stack: {
                spacing1: 15,
                spacing2: 15
            }
        });

        $.extend(PNotify.styling.fontawesome, {
            container: "notification",
            notice: "notification-warning",
            info: "notification-info",
            success: "notification-success",
            error: "notification-danger",
            notice_icon: "fas fa-exclamation",
            info_icon: "fas fa-info",
            success_icon: "fas fa-check",
            error_icon: "fas fa-times"
        });
    }

}).apply(this, [jQuery]);


// Ticker
let speed = 10000;
let interval = null;
let padLeft = 22;

function getTickerData() {
	$.post("/zpravy/json/get-ticker",
		{},
		function(json) {
			$("#ticker .ticker-container").html(json.content);
			$(".ticker-container ul div").each(function(i) {
				$(this).find("li").width($(window).width() - padLeft - (parseInt($(this).css("left"))));
				if (i == 0) {
					$(this).addClass("ticker-active");
				} else {
					$(this).addClass("not-active");
				}
				$(this).find("li").css("width", $(this).find("li span").width());
			});

			animateTickerElementHorz();
		},
		"json"
	);
}
function startTicker() {
	return setInterval(function() {
		$(".ticker-container ul div.ticker-active")
			.removeClass("ticker-active")
			.addClass("remove");

		if ($(".ticker-container ul div.remove").next().length) {
			$(".ticker-container ul div.remove")
				.next()
				.addClass("next");
		} else {
			getTickerData();

			$(".ticker-container ul div")
				.first()
				.addClass("next");
		}
		$(".ticker-container ul div.next")
			.removeClass("not-active next")
			.addClass("ticker-active");

		setTimeout(function() {
			$(".ticker-container ul div.remove")
				.css("transition", "0s ease-in-out")
				.removeClass("remove")
				.addClass("not-active finished")
				.find("li").css("margin-left", padLeft);

			if ($(".ticker-container ul div.finished li").width() < ($(window).width() - (parseInt($(".ticker-container ul div.ticker-active").css("left"))))) {
				$(".ticker-container ul div.finished").removeClass("finished");
			}
			setTimeout(function() {
				$(".ticker-container ul div")
					.css("transition", "0.5s ease-in-out");
			}, 75);
			animateTickerElementHorz();
		}, 500);
	}, speed);
}
function animateTickerElementHorz() {
	if ($(".ticker-container ul div.ticker-active li").width() > ($(".ticker-container").width() - padLeft)) {
		setTimeout(function() {
			$(".ticker-container ul div.ticker-active li").animate({
				"margin-left": Math.abs($(".ticker-container ul div.ticker-active li span").width() - $(".ticker-container").width() + padLeft + 5 + (($(".ticker-container div.label-active").length == 1) ? $(".ticker-container div.label-active").width() : 0)) * -1
			}, ((speed * 2) * (Math.abs($(".ticker-container ul div.ticker-active li span").width() - $(".ticker-container").width() + padLeft + 5 + (($(".ticker-container div.label-active").length == 1) ? $(".ticker-container div.label-active").width() : 0)) / 1000)), "linear", function() {
				$(".ticker-container ul div.finished").removeClass("finished");
			});
		}, ((speed / 5) / 2));
	}
}

// Crawl
function getCrawlData() {
	$.post("/zpravy/json/get-crawl",
		{},
		function(json) {
			if ((json.start != null) && (moment() > moment(json.start)) && ((json.stop == null) || (moment() < moment(json.stop)))) {
				$("#crawl .crawl-container").html(json.content);
				$("#crawl").removeClass("hide");
				let crawl = $("#crawl .crawl-container");
				let paddingLeft = 160;
				if ($.browser.mobile) {
					paddingLeft = 22;
					$("#crawl #crawlIcon").html("<i class=\"fa fa-fw fa-exclamation\"></i>");
				}
				crawl.each(function() {
					let text = $(this);
					let indent = text.width();
					text.crawl = function() {
						indent--;
						text.css("text-indent", indent);
						if ((indent - paddingLeft) < -1 * text.children("div").width()) {
							indent = text.width();
						}
					};
					text.data("interval", setInterval(text.crawl,1000 / 90));
				});
			} else {
				$("#crawl").addClass("hide");
			}

		},
		"json"
	);
}

// Cookies od 1.1.2022
// Zakomentováno, řešeno v CMP seznam.cz, cookies lišta pak není nutná. CMP zrušeno, nyní řešeno v cookies-spravne.cz
/*
var cookieconsent = initCookieConsent();
cookieconsent.run({
	current_lang : 'cs',
	page_scripts: true,
	autorun : true,
	delay : 0,
	autoclear_cookies : true,
	theme_css : '/vendor/web/cookieconsent/cookieconsent.css',
	revision: 1,

	gui_options: {
	  	consent_modal : {
			layout : 'cloud',               // box/cloud/bar
			position : 'bottom center',     // bottom/top + left/right/center
			transition: 'slide'             // zoom/slide
	  	},
	  	settings_modal : {
			layout : 'box',                 // box/bar
			transition: 'slide',            // zoom/slide
		}
	},

	onAccept: function(cookies){
		if(cookieconsent.allowedCategory('necessary')){
			var dataLayer = window.dataLayer || [];
			dataLayer.push({
				event:"CookieConsent",
				consentType:"necessary"
		  	});
		}

		if(cookieconsent.allowedCategory('tracking')){
			var dataLayer = window.dataLayer || [];
			dataLayer.push({
				event:"CookieConsent",
				consentType:"tracking"
		  	});
		}

		if(cookieconsent.allowedCategory('performance')){
			var dataLayer = window.dataLayer || [];
			dataLayer.push({
				event:"CookieConsent",
				consentType:"performance"
		  	});
		}
	},
	languages : {
		'cs' : {
			consent_modal : {
				title :  "Cookies",
				description :  'Tento web potřebuje Váš souhlas k využití jednotlivých dat, aby Vám mimo jiné mohl ukazovat informace týkající se Vašich zájmů. Souhlas udělíte kliknutím na políčko "OK".',
				primary_btn: {
					text: 'OK',
					role: 'accept_all'  //'accept_selected' or 'accept_all'
				},
				secondary_btn: {
					text : 'Nastavení',
					role : 'settings'   //'settings' or 'accept_necessary'
				}
			},
			settings_modal : {
				title : 'Cookies - nastavení',
				save_settings_btn : "Souhlasím s použitím vybraných souborů cookies",
				accept_all_btn : "Přijmout vše",
				close_btn_label: "Zavřít",
				cookie_table_headers : [
					{col1: "Cookie" },
					{col2: "Popis" },
				],
				blocks : [
					{
						title: 'Tento web potřebuje Váš souhlas s použitím souborů cookies, aby Vám mohl zobrazovat informace v souladu s Vašimi zájmy.',
						description: 'Zde máte možnost přizpůsobit soubory cookie podle kategorií, v souladu s vlastními preferencemi:',
					},
					{
						title : "Technické cookies",
						description: 'Technické cookies jsou nezbytné pro správné fungování webu a všech funkcí, které nabízí. Nepožadujeme Váš souhlas s využitím technických cookies na našem webu. Z tohoto důvodu technické cookies nemohou být individuálně deaktivovány nebo aktivovány.',
						toggle : {
							value : 'necessary',
							enabled : true,
							readonly: true
						}
					},
					{
						title : "Analytické cookies",
						description: 'Analytické cookies nám umožňují měření výkonu našeho webu a našich reklamních kampaní. Jejich pomocí určujeme počet návštěv a zdroje návštěv našich internetových stránek. Data získaná pomocí těchto cookies zpracováváme souhrnně, bez použití identifikátorů, které ukazují na konkrétní uživatelé našeho webu. Pokud vypnete používání analytických cookies ve vztahu k Vaší návštěvě, ztrácíme možnost analýzy výkonu a optimalizace našich opatření.',
						toggle : {
							value : 'performance',
							enabled : true,
							readonly: false
						}
					},
					{
						title : "Reklamní cookies",
						description: 'Reklamní cookies používáme my nebo naši partneři, abychom Vám mohli zobrazit vhodné obsahy nebo reklamy jak na našich stránkách, tak na stránkách třetích subjektů. Díky tomu můžeme vytvářet profily založené na Vašich zájmech, tak zvané pseudonymizované profily. Na základě těchto informací není zpravidla možná bezprostřední identifikace Vaší osoby, protože jsou používány pouze pseudonymizované údaje. Pokud nevyjádříte souhlas, nebudete příjemcem obsahů a reklam přizpůsobených Vašim zájmům.',
						toggle : {
							value : 'tracking',
							enabled : false,
							readonly: false
						}
					}
				]
			}
		}
	}
});

// Podle nové legislativy přidáno tlačítko "Odmítnout vše"
$('#c-bns').append('<button type="button" id="c-bn-close" class="c-link-decline">Odmítnout vše</button>');
$('#c-bn-close').on('click', function() {
	$('input.c-tgl').prop('checked', false);
	$('#s-sv-bn').click();
});

if(!cookieconsent.validCookie('cc_cookie')){
	var dataLayer = window.dataLayer || [];
		dataLayer.push({
		event:"CookieConsent",
		consentType:"empty"
	});
}*/