/*
Plugin Name: 	jQuery.pin
https://github.com/webpop/jquery.pin
Licensed under the terms of the MIT license.
*/
(function ($) {
    "use strict";
    $.fn.pin = function (options) {
        var scrollY = 0, elements = [], disabled = false, $window = $(window);

        options = options || {};

        var recalculateLimits = function () {
            for (var i = 0, len = elements.length; i < len; i++) {
                var $this = elements[i];

                if (options.minWidth && $window.outerWidth() <= options.minWidth) {
                    if ($this.parent().is(".pin-wrapper")) {
                        $this.unwrap();
                    }
                    $this.css({width: "", left: "", top: "", position: ""});
                    if (options.activeClass) {
                        $this.removeClass(options.activeClass);
                    }
                    disabled = true;
                    continue;
                } else {
                    disabled = false;
                }

                var $container = options.containerSelector ? $this.closest(options.containerSelector) : $(document.body);
                var offset = $this.offset();
                var containerOffset = $container.offset();
                var parentOffset = $this.parent().offset();

                if (!$this.parent().is(".pin-wrapper")) {
                    $this.wrap("<div class='pin-wrapper'>");
                }

                var pad = $.extend({
                    top: 0,
                    bottom: 0
                }, options.padding || {});

                $this.data("pin", {
                    pad: pad,
                    from: (options.containerSelector ? containerOffset.top : offset.top) - pad.top,
                    to: containerOffset.top + $container.height() - $this.outerHeight() - pad.bottom,
                    end: containerOffset.top + $container.height(),
                    parentTop: parentOffset.top
                });

                $this.css({width: $this.outerWidth()});
                $this.parent().css("height", $this.outerHeight());
            }
        };

        var onScroll = function () {
            if (disabled) {
                return;
            }

            scrollY = $window.scrollTop();

            var elmts = [];
            for (var i = 0, len = elements.length; i < len; i++) {
                var $this = $(elements[i]),
                    data = $this.data("pin");

                if (!data) { // Removed element
                    continue;
                }

                elmts.push($this);

                var from = data.from - data.pad.bottom,
                    to = data.to - data.pad.top;

                if (from + $this.outerHeight() > data.end) {
                    $this.css('position', '');
                    continue;
                }

                if (from < scrollY && to > scrollY) {
                    !($this.css("position") == "fixed") && $this.css({
                        left: $this.offset().left,
                        top: data.pad.top
                    }).css("position", "fixed");
                    if (options.activeClass) {
                        $this.addClass(options.activeClass);
                    }
                } else if (scrollY >= to) {
                    $this.css({
                        left: "",
                        top: to - data.parentTop + data.pad.top
                    }).css("position", "absolute");
                    if (options.activeClass) {
                        $this.addClass(options.activeClass);
                    }
                } else {
                    $this.css({position: "", top: "", left: ""});
                    if (options.activeClass) {
                        $this.removeClass(options.activeClass);
                    }
                }
            }
            elements = elmts;
        };

        var update = function () {
            recalculateLimits();
            onScroll();
        };

        this.each(function () {
            var $this = $(this),
                data = $(this).data('pin') || {};

            if (data && data.update) {
                return;
            }
            elements.push($this);
            $("img", this).one("load", recalculateLimits);
            data.update = update;
            $(this).data('pin', data);
        });

        $window.scroll(onScroll);
        $window.resize(function () {
            recalculateLimits();
        });
        recalculateLimits();

        $window.on('load', update);

        return this;
    };
})(jQuery);

(function ($) {
    "use strict";

    // Define default settings
    var defaults = {
        action: function () {
        },
        runOnLoad: false,
        duration: 500
    };

    // Define global variables
    var settings = defaults,
        running = false,
        start;

    var methods = {};

    // Initial plugin configuration
    methods.init = function () {

        // Allocate passed arguments to settings based on type
        for (var i = 0; i <= arguments.length; i++) {
            var arg = arguments[i];
            switch (typeof arg) {
                case "function":
                    settings.action = arg;
                    break;
                case "boolean":
                    settings.runOnLoad = arg;
                    break;
                case "number":
                    settings.duration = arg;
                    break;
            }
        }

        // Process each matching jQuery object
        return this.each(function () {

            if (settings.runOnLoad) {
                settings.action();
            }

            $(this).resize(function () {

                methods.timedAction.call(this);

            });

        });
    };

    methods.timedAction = function (code, millisec) {

        var doAction = function () {
            var remaining = settings.duration;

            if (running) {
                var elapse = new Date() - start;
                remaining = settings.duration - elapse;
                if (remaining <= 0) {
                    // Clear timeout and reset running variable
                    clearTimeout(running);
                    running = false;
                    // Perform user defined function
                    settings.action();

                    return;
                }
            }
            wait(remaining);
        };

        var wait = function (time) {
            running = setTimeout(doAction, time);
        };

        // Define new action starting time
        start = new Date();

        // Define runtime settings if function is run directly
        if (typeof millisec === 'number') {
            settings.duration = millisec;
        }
        if (typeof code === 'function') {
            settings.action = code;
        }

        // Only run timed loop if not already running
        if (!running) {
            doAction();
        }

    };


    $.fn.afterResize = function (method) {

        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else {
            return methods.init.apply(this, arguments);
        }

    };

})(jQuery);


/* ====================================================
 * jQuery is in viewport.
 *
 * https://github.com/frontid/jQueryIsInViewport
 * Marcelo Iván Tosco (capynet)
 * Inspired on https://stackoverflow.com/a/40658647/1413049
 * ==================================================== */
!function ($) {
    'use strict';

    const IsInViewport = function (el, cb, offset) {
        this.$el = $(el);
        this.cb = cb;
        this.offset = offset;
        this.previousIsInState = false;

        // Make the first check
        this.check();

        // Start watching.
        this.watch();

        return this;
    };

    IsInViewport.prototype = {

        /**
         * Checks if the element is in.
         *
         * @returns {boolean}
         */
        isIn: function () {
            const _self = this;
            const $win = $(window);
            const elementTop = _self.$el.offset().top - _self.offset;
            const elementBottom = elementTop + _self.$el.outerHeight();
            const viewportTop = $win.scrollTop();
            const viewportBottom = viewportTop + $win.height();
            return elementBottom > viewportTop && elementTop < viewportBottom;
        },

        /**
         * Launch a callback indicating when the element is in and when is out.
         */
        watch: function () {
            const self = this;
            $(window).on('resize scroll', self.check.bind(self));
        },

        /**
         * Checks if the element is on in the viewport.
         */
        check: function () {
            const self = this;

            if (self.isIn() && self.previousIsInState === false) {
                self.cb.call(self.$el, 'entered');
                self.previousIsInState = true;
            }

            if (self.previousIsInState === true && !self.isIn()) {
                self.cb.call(self.$el, 'leaved');
                self.previousIsInState = false;
            }
        }
    };

    // jQuery plugin.
    //-----------------------------------------------------------
    $.fn.isInViewport = function (cb, offset) {
        offset || (offset = 0);
        return this.each(function () {
            const $element = $(this);
            const data = $element.data('isInViewport');

            if (!data) {
                $element.data('isInViewport', (new IsInViewport(this, cb, offset)));
            }
        })
    }

}(window.jQuery);