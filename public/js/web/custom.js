$(function() {

	/*var offset = $(".skyscraper").offset();
	var topPadding = 15;
	$(window).scroll(function() {
		if ($(window).scrollTop() > offset.top) {
			$(".skyscraper").stop().animate({
				marginTop: $(window).scrollTop() - offset.top + topPadding
			});
		} else {
			$(".skyscraper").stop().animate({
				marginTop: 0
			});
		};
	});

	$('[data-toggle="tooltip"]').tooltip();*/

	/*$(window).resize(function(){
		if ($(window).width() < 768) {
			$("html").removeClass("boxed");
		}else{
			$("html").addClass("boxed");
		}
	});
	if ($(window).width() < 768) {
		$("html").removeClass("boxed");
	}else{
		$("html").addClass("boxed");
	}*/

    // Hamburger
    $(document).on('click', function(event) {
        // Zkontrolujte, zda uživatel klikl na prvek s třídou 'sidenav-1' nebo na jeho potomky
        if (!$(event.target).closest('.sidenav-1').length) {
            // Pokud uživatel klikl na jiný prvek než na sidenav-1, zavřete menu
            $('button.sidenav-1').removeClass('open');
        }
    });
    $('button.sidenav-1').on('click', function () {
        $(this).toggleClass('open');
    });

    // Mobily - posuvné menu regionů
    var $header = $('#header');
    var $window = $(window);

    if ($window.width() < 992) {
        $header.find('#mainNav.mobile-menu a.dropdown-toggle > i.fas.fa-chevron-down').remove();

        // Zde zkopírujeme položku regionu (např. Ostravsko) z posuvného menu, a vložíme ji mezi města do rozbalovacího menu
        var regions = $header.find('#mainNav.mobile-menu li.dropdown a.dropdown-toggle');
        regions.each(function() {
            var tag_a = $(this);
            $(this).parent().find('ul.dropdown-menu').prepend('<li class="">' + $(this).prop('outerHTML') + '</li>');
        });
        var regions2 = $header.find('#mainNav.mobile-menu li.dropdown ul.dropdown-menu li:first-child');
        regions2.each(function() {
            $(this).find('a').removeClass().addClass('dropdown-item region-in-dropdown text-2');
        });

        // Add Open Class
        $header.find('.mobile_menu .dropdown-toggle[href!="#"]').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            $header.find('li a.active').removeClass('active');

            if( $(this).prop('tagName') == 'I' ) {
                $(this).parent().addClass('active');
            } else {
                $(this).addClass('active');
            }

            if (!$(this).closest('li').hasClass('open')) {

                var $li = $(this).closest('li'),
                    isSub = false;

                if( $(this).prop('tagName') == 'I' ) {
                    $('#header .dropdown.open').removeClass('open');
                    $('#header .dropdown-menu .dropdown-submenu.open').removeClass('open');
                }

                if ( $(this).parent().hasClass('dropdown-submenu') ) {
                    isSub = true;
                }

                $(this).closest('.dropdown-menu').find('.dropdown-submenu.open').removeClass('open');
                $(this).parent('.dropdown').parent().find('.dropdown.open').removeClass('open');

                if (!isSub) {
                    $(this).parent().find('.dropdown-submenu.open').removeClass('open');
                }

                $li.addClass('open');

                $(document).off('click.nav-click-to-open').on('click.nav-click-to-open', function (e) {
                    if (!$li.is(e.target) && $li.has(e.target).length === 0) {
                        $li.removeClass('open');
                        $li.parents('.open').removeClass('open');
                        $header.find('li a.active').removeClass('active');
                        $header.find('li a.current-page-active').addClass('active');
                    }
                });

            } else {
                $(this).closest('li').removeClass('open');
                $header.find('li a.active').removeClass('active');
                $header.find('li a.current-page-active').addClass('active');
            }

            $window.trigger({
                type: 'resize',
                from: 'header-nav-click-to-open'
            });
        });
    }

    // Mobily - horizontální menu regionů - scrollTo na aktivní položku
    if ($window.width() < 992) {
        var active_item = $header.find('#mainNav.mobile-menu li a.active');
        if (active_item.length) {
            var elemPosition = active_item.offset().left;
            $('#mainNav.mobile-menu').animate({scrollLeft: Math.round(elemPosition)}, 0);
        }
    }

    // Volby - Mobily - horizontální menu voleb - scrollTo na aktivní položku
    if ($window.width() < 992) {
        var active_item_volby = $('.elections_menu nav ul li a.active');
        if (active_item_volby.length) {
            var elemPositionVolby= active_item_volby.offset().left - $('.elections_menu nav ul').offset().left;
            $('.elections_menu nav ul').animate({scrollLeft: Math.round(elemPositionVolby)}, 0);
        }
    }

    /* Přístupnost - hlavní navigační menu — správa tabindex a klávesnice */
    var $nav = $('#mainNav');
    if ($nav.length) {

        function openDropdown($toggle, $menu) {
            $toggle.attr('aria-expanded', 'true');
            $menu.find('a').removeAttr('tabindex');
        }

        function closeDropdown($toggle, $menu) {
            $toggle.attr('aria-expanded', 'false');
            $menu.find('a').attr('tabindex', '-1');
        }

        $nav.find('li.dropdown').each(function () {
            var $li     = $(this);
            var $toggle = $li.children('a[aria-haspopup]');
            var $menu   = $li.children('ul.dropdown-menu');
            if (!$toggle.length || !$menu.length) { return; }

            // Otevření Enterem/Mezerou na toggle
            $toggle.on('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if ($toggle.attr('aria-expanded') === 'true') {
                        closeDropdown($toggle, $menu);
                        $li.removeClass('open');
                    } else {
                        openDropdown($toggle, $menu);
                        $li.addClass('open');
                        $menu.find('a').first().focus();
                    }
                }
            });

            // Escape zavře dropdown
            $li.on('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeDropdown($toggle, $menu);
                    $li.removeClass('open');
                    $toggle.focus();
                }
            });

            // Šipky pro navigaci uvnitř submenu
            $menu.on('keydown', function (e) {
                var $items = $menu.find('a');
                var idx    = $items.index($(document.activeElement));
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (idx < $items.length - 1) { $items.eq(idx + 1).focus(); }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (idx > 0) { $items.eq(idx - 1).focus(); } else { $toggle.focus(); }
                }
            });

            // Zavření při přechodu focusu mimo dropdown
            $li.on('focusout', function (e) {
                if (!$li.is(e.relatedTarget) && $li.has(e.relatedTarget).length === 0) {
                    closeDropdown($toggle, $menu);
                    $li.removeClass('open');
                }
            });

            // Hover — sync aria-expanded s vizuálním stavem
            $li.on('mouseenter', function () { openDropdown($toggle, $menu); });
            $li.on('mouseleave', function () { closeDropdown($toggle, $menu); });
        });
    }

    /* PLAYER živé vysílání - přepínání kvality SD v okýnku, HD na fullscreen */
    // Zkontrolujeme, zda existuje element s ID "player"
    const playerElement = $('#playerLiveSDHD');
    if (playerElement.length && $(window).width() > 992) {
        let playerLiveSDHD = videojs("playerLiveSDHD");
        let isHD = false;

        // Funkce pro změnu na HD kvalitu
        function switchToHD() {
            if (!isHD) {
                playerLiveSDHD.pause();
                playerLiveSDHD.src({
                    src: "https://stream.polar.cz:443/polar/polarlive-1/playlist.m3u8",
                    type: "application/x-mpegURL"
                });
                playerLiveSDHD.load();
                playerLiveSDHD.play();
                isHD = true;
            }
        }

        // Funkce pro změnu na SD kvalitu
        function switchToSD() {
            if (isHD) {
                playerLiveSDHD.pause();
                playerLiveSDHD.src({
                    src: "https://stream.polar.cz:443/polar/polarlive-2/playlist.m3u8",
                    type: "application/x-mpegURL"
                });
                playerLiveSDHD.load();
                playerLiveSDHD.play();
                isHD = false;
            }
        }

        // Event listener pro fullscreen změnu
        $(document).on("fullscreenchange", function() {
            if (document.fullscreenElement) {
                switchToHD(); 	// Přepnutí na HD při fullscreen
            } else {
                switchToSD(); 	// Zpět na SD při ukončení fullscreen
            }
        });
    }


});

function bannerCountClick(type, id) {
    $.post("/banner/json-write/set-clicked",
        {
            type: type,
            id: id
        },
        function(json) {
        },
        "json"
    );
}

