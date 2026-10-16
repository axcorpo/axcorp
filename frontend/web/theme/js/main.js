/**
 * Header Sticky
 * Handle Mobile Menu
 * Footer
 * Handle Sidebar Filter
 * Counter
 * Video
 * Infinite Slide
 * Change Image By Accordion
 * Parallax Image
 * Horizontal Accordion
 * Init PricingTabs
 * Go Top
 * Preloader
 * 
 **/

(function ($) {
    ("use strict");

    /* Header Sticky
    -------------------------------------------------------------------------*/

    var headerSticky = function () {
        $(window).on("scroll", function () {
            let st = $(window).scrollTop();
            let navbarHeight = $("header").outerHeight();

            if (st >= 250) {
                $("header").css("top", "0").addClass("header-sticky");
                $(".sticky-top").css("top", `${15 + navbarHeight}px`);
            } else {
                $("header").css("top", `-${navbarHeight}px`).removeClass("header-sticky");
                $(".sticky-top").css("top", "15px");
            }
        });
    };

    /* Handle Mobile Menu
   -------------------------------------------------------------------------*/
    var handleMobileMenu = function () {
        const $desktopMenu = $(".box-nav-menu:not(.not-append)").clone();
        $desktopMenu.find(".list-ver, .list-hor,.mn-none").remove();

        const $mobileMenu = $('<ul class="nav-ul-mb"></ul>');

        $desktopMenu.find("> li.menu-item").each(function (i, menuItem) {
            const $item = $(menuItem);
            const iconArrow = "icon-chevron-down";
            const text = $item
                .find("> a.item-link > span")
                .clone()
                .children()
                .remove()
                .end()
                .text()
                .trim();
            const submenu = $item.find("> .sub-menu");
            const id = "dropdown-menu-" + i;
            const isActive = $item.find("> a.item-link").hasClass("active");

            const activeClass = isActive ? "active" : "";
            const showClass = isActive ? "show" : "";
            const expanded = isActive ? "true" : "false";

            if (submenu.length > 0) {
                const $li = $(`
                    <li class="nav-mb-item">
                        <a href="#${id}" 
                           class="collapsed mb-menu-link ${activeClass}" 
                           data-bs-toggle="collapse" 
                           aria-expanded="${expanded}" 
                           aria-controls="${id}">
                            <span>${text}</span>
                            <span class="icon ${iconArrow}"></span>
                        </a>
                        <div id="${id}" class="collapse ${showClass}"></div>
                    </li>
                `);

                const $subNav = $('<ul class="sub-nav-menu"></ul>');

                submenu.find(".mega-menu-item").each(function (j) {
                    const heading = $(this).find(".menu-heading").text().trim();
                    const subId = `${id}-group-${j}`;
                    const $group = $(`
                        <li>
                            <a href="#${subId}" class="collapsed sub-nav-link" 
                               data-bs-toggle="collapse" aria-expanded="false" 
                               aria-controls="${subId}">
                                <span>${heading}</span>
                                <span class="icon ${iconArrow}"></span>
                            </a>
                            <div id="${subId}" class="collapse">
                                <ul class="sub-nav-menu sub-menu-level-2"></ul>
                            </div>
                        </li>
                    `);

                    $(this)
                        .find(".sub-menu_list a")
                        .each(function () {
                            const $link = $(this);
                            const linkHref = $link.attr("href") || "#";
                            const title = $link.text().trim();
                            const isActive = $link.hasClass("active");

                            if (title !== "") {
                                const activeClass = isActive ? "active" : "";
                                $group
                                    .find(".sub-menu-level-2")
                                    .append(
                                        `<li><a href="${linkHref}" class="sub-nav-link ${activeClass}">${title}</a></li>`
                                    );
                            }
                        });

                    $subNav.append($group);
                });

                if ($subNav.children().length === 0) {
                    submenu.find("a").each(function () {
                        const link = $(this).attr("href") || "#";
                        const title = $(this).text().trim();
                        const isActive = $(this).hasClass("active");
                        const activeClass = isActive ? "active" : "";

                        if (title !== "") {
                            $subNav.append(
                                `<li><a href="${link}" class="sub-nav-link ${activeClass}">${title}</a></li>`
                            );
                        }
                    });
                }

                $li.find(`#${id}`).append($subNav);
                $mobileMenu.append($li);
            } else {
                const activeClass = isActive ? "active" : "";
                $mobileMenu.append(
                    `<li class="nav-mb-item"><a href="${$item
                        .find("a")
                        .attr(
                            "href"
                        )}" class="mb-menu-link ${activeClass}"><span>${text}</span></a></li>`
                );
            }
        });

        $("#wrapper-menu-navigation").empty().append($mobileMenu.html());
    };

    /* Handle Footer
   -------------------------------------------------------------------------*/
    var handleFooter = function () {
        var footerAccordion = function () {
            var args = { duration: 250 };
            $(".title-mobile").on("click", function () {
                var $parent = $(this).parent(".footer-col-block");
                var $content = $(this).next();

                $parent.toggleClass("open");

                if (!$parent.hasClass("open")) {
                    $content.slideUp(args);
                } else {
                    $content.slideDown(args);
                }
            });
        };

        function handleAccordion() {
            if (window.matchMedia("only screen and (max-width: 575px)").matches) {
                if (!$(".title-mobile").data("accordion-initialized")) {
                    footerAccordion();
                    $(".title-mobile").data("accordion-initialized", true);
                }
            } else {
                $(".title-mobile")
                    .off("click")
                    .removeData("accordion-initialized")
                    .each(function () {
                        $(this).parent(".footer-col-block").removeClass("open").end().next().removeAttr("style");
                    });
            }
        }

        handleAccordion();
        $(window).on("resize", handleAccordion);
    };

    /* Handle Sidebar Filter
  -------------------------------------------------------------------------*/
    var handleSidebarFilter = function () {
        $("#filterShop,.sidebar-btn").on("click", function () {
            if ($(window).width() <= 1200) {
                $(".sidebar-filter,.overlay-filter").addClass("show");
            }
        });
        $(".close-filter,.overlay-filter").on("click", function () {
            $(".sidebar-filter,.overlay-filter").removeClass("show");
        });
    };

    /* Counter
   -------------------------------------------------------------------------*/
    var counter = function () {
        $(".view-counter").each(function () {
            $(this).data('counted', false);
        });

        var checkCounters = function () {
            $(".view-counter").each(function () {
                var $counter = $(this);

                if ($counter.data('counted')) {
                    return;
                }

                var counterTop = $counter.offset().top;
                var counterBottom = counterTop + $counter.outerHeight();
                var viewportTop = $(window).scrollTop();
                var viewportBottom = viewportTop + $(window).height();
                var isInViewport = counterTop < viewportBottom && counterBottom > viewportTop;

                if (isInViewport) {
                    if ($().countTo) {
                        $counter.find(".number").each(function () {
                            var to = $(this).data("to"),
                                speed = $(this).data("speed");
                            $(this).countTo({
                                to: to,
                                speed: speed,
                            });
                        });
                    }
                    $counter.data('counted', true);
                }
            });
        };

        checkCounters();

        $(window).scroll(checkCounters);
    };

    /* Video
   -------------------------------------------------------------------------*/
    var video = function () {
        if (
            $("div").hasClass("wg-video") ||
            $("div").hasClass("post-format-video")
        ) {
            $(".popup-youtube, .wg-curve-text-video").magnificPopup({
                type: "iframe",
            });
        }
    };

    /* Infinite Slide 
    -------------------------------------------------------------------------*/
    var infiniteSlide = function () {
        if ($(".infiniteSlide").length > 0) {
            $(".infiniteSlide").each(function () {
                var $this = $(this);
                var style = $this.data("style") || "left";
                var clone = $this.data("clone") || 2;
                var speed = $this.data("speed") || 50;
                var pauseonhover = $this.data("pause") || true;
                $this.infiniteslide({
                    speed: speed,
                    direction: style,
                    clone: clone,
                    pauseonhover: pauseonhover,
                });
            });
        }
    };

    /* Parallax Image
       -------------------------------------------------------------------------------------*/
    var parallaxImage = function () {
        if ($(".parallax-img").length > 0) {
            $(".parallax-img").each(function () {
                new SimpleParallax(this, {
                    delay: 0.6,
                    orientation: "up",
                    scale: 1.3,
                    transition: "cubic-bezier(0,0,0,1)",
                    customContainer: "",
                    customWrapper: "",
                });
            });
        }
    };

    /* Horizontal Accordion
      -------------------------------------------------------------------------------------*/
    var horizontalAccordion = function () {
        var settings = {
            selector: '.horizontal-accordion',
            wrapper: '.horizontal-accordion-wrap',
            item: '.accordion-horizontal-item',
            prev: '.horizontal-accordion-prev',
            next: '.horizontal-accordion-next',
            pagination: '.horizontal-accordion-pagination',
            activeClass: 'active',
            initializedClass: 'horizontal-accordion-initialized',
            navInitializedClass: 'horizontal-accordion-nav-initialized',
            paginationInitializedClass: 'horizontal-accordion-pagination-initialized',
            mobileClass: 'is-mobile-slider',
            skipAutoScrollAttr: 'data-skip-auto-scroll',
            breakpoint: 1199,
            defaultInactiveWidth: 0
        };

        var isMobile = function () {
            return window.innerWidth <= settings.breakpoint;
        };

        var getAccordions = function () {
            return document.querySelectorAll(settings.selector);
        };

        var getItems = function (accordion) {
            return accordion.querySelectorAll(settings.item);
        };

        var getWrap = function (accordion) {
            return accordion.closest(settings.wrapper);
        };

        var getInactiveWidth = function (accordion) {
            var inactiveWidth = parseFloat(
                accordion.getAttribute('data-inactive-width')
            );

            if (isNaN(inactiveWidth)) {
                inactiveWidth = settings.defaultInactiveWidth;
            }

            return inactiveWidth;
        };

        var getActiveIndex = function (items) {
            var activeIndex = 0;

            items.forEach(function (item, index) {
                if (item.classList.contains(settings.activeClass)) {
                    activeIndex = index;
                }
            });

            return activeIndex;
        };

        var setItemSize = function (item, width) {
            item.style.width = width;
            item.style.flexBasis = width;
            item.style.flexGrow = '0';
            item.style.flexShrink = '0';
        };

        var setActiveItem = function (accordion, index) {
            var items = getItems(accordion);

            if (!items.length) {
                return;
            }

            if (index < 0) {
                index = 0;
            }

            if (index > items.length - 1) {
                index = items.length - 1;
            }

            items.forEach(function (item) {
                item.classList.remove(settings.activeClass);
            });

            items[index].classList.add(settings.activeClass);
        };

        var updateNavState = function (accordion) {
            var wrap = getWrap(accordion);

            if (!wrap) {
                return;
            }

            var prevButton = wrap.querySelector(settings.prev);
            var nextButton = wrap.querySelector(settings.next);
            var items = getItems(accordion);

            if (!prevButton || !nextButton || !items.length) {
                return;
            }

            var activeIndex = getActiveIndex(items);

            prevButton.disabled = activeIndex === 0;
            nextButton.disabled = activeIndex === items.length - 1;
        };

        var updatePagination = function (accordion) {
            var wrap = getWrap(accordion);

            if (!wrap) {
                return;
            }

            var pagination = wrap.querySelector(settings.pagination);
            var items = getItems(accordion);

            if (!pagination || !items.length) {
                return;
            }

            var bullets = pagination.querySelectorAll('.swiper-pagination-bullet');
            var activeIndex = getActiveIndex(items);

            bullets.forEach(function (bullet, index) {
                bullet.classList.toggle(
                    'swiper-pagination-bullet-active',
                    index === activeIndex
                );
            });
        };

        var updateControls = function (accordion) {
            updateNavState(accordion);
            updatePagination(accordion);
        };

        var scrollToActiveItem = function (accordion, behavior) {
            var items = getItems(accordion);
            var activeIndex = getActiveIndex(items);

            if (!items.length || !items[activeIndex]) {
                return;
            }

            if (!isMobile()) {
                return;
            }

            accordion.scrollTo({
                left: items[activeIndex].offsetLeft,
                behavior: behavior || 'smooth'
            });
        };

        var updateActiveByScroll = function (accordion) {
            if (!isMobile()) {
                return;
            }

            var items = getItems(accordion);

            if (!items.length) {
                return;
            }

            var accordionLeft = accordion.scrollLeft;
            var accordionWidth = accordion.clientWidth;
            var accordionCenter = accordionLeft + accordionWidth / 2;

            var closestIndex = 0;
            var closestDistance = Infinity;

            items.forEach(function (item, index) {
                var itemCenter = item.offsetLeft + item.offsetWidth / 2;
                var distance = Math.abs(accordionCenter - itemCenter);

                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestIndex = index;
                }
            });

            setActiveItem(accordion, closestIndex);
            updateControls(accordion);
        };

        var updateAccordionWidth = function (accordion) {
            var items = getItems(accordion);

            if (!items.length) {
                return;
            }

            if (!accordion.querySelector(settings.item + '.' + settings.activeClass)) {
                items[0].classList.add(settings.activeClass);
            }

            if (isMobile()) {
                accordion.classList.add(settings.mobileClass);

                items.forEach(function (item) {
                    setItemSize(item, '100%');
                });

                updateControls(accordion);

                if (!accordion.hasAttribute(settings.skipAutoScrollAttr)) {
                    setTimeout(function () {
                        scrollToActiveItem(accordion, 'auto');
                    }, 50);
                }

                return;
            }

            accordion.classList.remove(settings.mobileClass);

            var inactiveWidth = getInactiveWidth(accordion);
            var accordionWidth = accordion.clientWidth;

            var accordionStyle = window.getComputedStyle(accordion);
            var gap = parseFloat(accordionStyle.columnGap || accordionStyle.gap) || 0;

            var totalGap = gap * (items.length - 1);
            var inactiveItemsCount = items.length - 1;

            var activeWidth =
                accordionWidth -
                totalGap -
                inactiveWidth * inactiveItemsCount;

            if (activeWidth < 0) {
                activeWidth = 0;
            }

            items.forEach(function (item) {
                if (item.classList.contains(settings.activeClass)) {
                    setItemSize(item, activeWidth + 'px');
                } else {
                    setItemSize(item, inactiveWidth + 'px');
                }
            });

            updateControls(accordion);
        };

        var goToSlide = function (accordion, index) {
            accordion.setAttribute(settings.skipAutoScrollAttr, 'true');

            setActiveItem(accordion, index);
            updateAccordionWidth(accordion);

            accordion.removeAttribute(settings.skipAutoScrollAttr);

            if (isMobile()) {
                scrollToActiveItem(accordion, 'smooth');
            }
        };

        var initNav = function (accordion) {
            var wrap = getWrap(accordion);

            if (!wrap) {
                return;
            }

            if (wrap.classList.contains(settings.navInitializedClass)) {
                return;
            }

            var prevButton = wrap.querySelector(settings.prev);
            var nextButton = wrap.querySelector(settings.next);

            if (!prevButton || !nextButton) {
                return;
            }

            wrap.classList.add(settings.navInitializedClass);

            prevButton.addEventListener('click', function () {
                var items = getItems(accordion);
                var activeIndex = getActiveIndex(items);

                goToSlide(accordion, activeIndex - 1);
            });

            nextButton.addEventListener('click', function () {
                var items = getItems(accordion);
                var activeIndex = getActiveIndex(items);

                goToSlide(accordion, activeIndex + 1);
            });
        };

        var initPagination = function (accordion) {
            var wrap = getWrap(accordion);

            if (!wrap) {
                return;
            }

            var pagination = wrap.querySelector(settings.pagination);
            var items = getItems(accordion);

            if (!pagination || !items.length) {
                return;
            }

            if (pagination.classList.contains(settings.paginationInitializedClass)) {
                updatePagination(accordion);
                return;
            }

            pagination.classList.add(settings.paginationInitializedClass);
            pagination.innerHTML = '';

            items.forEach(function (item, index) {
                var bullet = document.createElement('span');

                bullet.className = 'swiper-pagination-bullet';
                bullet.setAttribute('role', 'button');
                bullet.setAttribute('aria-label', 'Go to slide ' + (index + 1));

                bullet.addEventListener('click', function () {
                    goToSlide(accordion, index);
                });

                pagination.appendChild(bullet);
            });

            updatePagination(accordion);
        };

        var initScrollEvent = function (accordion) {
            if (accordion.hasAttribute('data-scroll-event-init')) {
                return;
            }

            accordion.setAttribute('data-scroll-event-init', 'true');

            var scrollTimer = null;

            accordion.addEventListener('scroll', function () {
                if (!isMobile()) {
                    return;
                }

                clearTimeout(scrollTimer);

                scrollTimer = setTimeout(function () {
                    updateActiveByScroll(accordion);
                }, 80);
            });
        };

        var initAccordion = function (accordion) {
            var items = getItems(accordion);

            if (!items.length) {
                return;
            }

            if (accordion.classList.contains(settings.initializedClass)) {
                updateAccordionWidth(accordion);
                initPagination(accordion);
                return;
            }

            accordion.classList.add(settings.initializedClass);

            if (!accordion.querySelector(settings.item + '.' + settings.activeClass)) {
                items[0].classList.add(settings.activeClass);
            }

            items.forEach(function (item, index) {
                item.addEventListener('click', function () {
                    goToSlide(accordion, index);
                });
            });

            initNav(accordion);
            initPagination(accordion);
            initScrollEvent(accordion);
            updateAccordionWidth(accordion);
        };

        var init = function () {
            var accordions = getAccordions();

            if (!accordions.length) {
                return;
            }

            accordions.forEach(function (accordion) {
                initAccordion(accordion);
            });
        };

        var bindEvents = function () {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    init();
                });
            } else {
                init();
            }

            window.addEventListener('resize', function () {
                init();
            });
        };

        bindEvents();
    };

    /* Init PricingTabs
     -------------------------------------------------------------------------------------*/
    var initPricingTabs = function () {
        var run = function () {
            var tabGroups = document.querySelectorAll('[data-pricing-tabs]');

            if (!tabGroups.length) {
                return;
            }

            tabGroups.forEach(function (group) {
                if (group.classList.contains('pricing-tabs-initialized')) {
                    return;
                }

                group.classList.add('pricing-tabs-initialized');

                var tabButtons = group.querySelectorAll('[data-tab-target]');
                var tabContents = group.querySelectorAll('[data-tab-content]');
                var toggleButton = group.querySelector('.pricing-toggle');

                if (!tabButtons.length || !tabContents.length || !toggleButton) {
                    return;
                }

                var activateTab = function (tabName) {
                    tabButtons.forEach(function (button) {
                        button.classList.toggle(
                            'active',
                            button.dataset.tabTarget === tabName
                        );
                    });

                    tabContents.forEach(function (content) {
                        content.classList.toggle(
                            'active',
                            content.dataset.tabContent === tabName
                        );
                    });

                    group.classList.toggle('is-yearly', tabName === 'yearly');
                };

                tabButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        activateTab(this.dataset.tabTarget);
                    });
                });

                toggleButton.addEventListener('click', function () {
                    var currentActive = group.querySelector('[data-tab-target].active');

                    var currentTab = currentActive
                        ? currentActive.dataset.tabTarget
                        : 'monthly';

                    var nextTab = currentTab === 'monthly' ? 'yearly' : 'monthly';

                    activateTab(nextTab);
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
    };

    /* Go Top
      -------------------------------------------------------------------------------------*/
    var goTop = function () {
        if ($("div").hasClass("progress-wrap")) {
            var progressPath = document.querySelector(".progress-wrap path");
            var pathLength = progressPath.getTotalLength();
            progressPath.style.transition = progressPath.style.WebkitTransition =
                "none";
            progressPath.style.strokeDasharray = pathLength + " " + pathLength;
            progressPath.style.strokeDashoffset = pathLength;
            progressPath.getBoundingClientRect();
            progressPath.style.transition = progressPath.style.WebkitTransition =
                "stroke-dashoffset 10ms linear";
            var updateprogress = function () {
                var scroll = $(window).scrollTop();
                var height = $(document).height() - $(window).height();
                var progress = pathLength - (scroll * pathLength) / height;
                progressPath.style.strokeDashoffset = progress;
            };
            updateprogress();
            $(window).scroll(updateprogress);
            var offset = 0;
            var duration = 0;
            jQuery(window).on("scroll", function () {
                if (jQuery(this).scrollTop() > offset) {
                    jQuery(".progress-wrap").addClass("active-progress");
                } else {
                    jQuery(".progress-wrap").removeClass("active-progress");
                }
            });
            jQuery(".progress-wrap").on("click", function (event) {
                event.preventDefault();
                jQuery("html, body").animate({ scrollTop: 0 }, duration);
                return false;
            });
        }
    };

    /* Preloader 
    ------------------------------------------------------------------------------------- */
    var preloader = function () {
        setTimeout(function () {
            $(".preload").fadeOut("slow", function () {
                $(this).remove();
            });
        }, 200);
    };


    // Dom Ready
    $(function () {
        headerSticky();
        handleMobileMenu();
        handleFooter();
        handleSidebarFilter();
        counter();
        video();
        infiniteSlide();
        parallaxImage();
        horizontalAccordion();
        initPricingTabs();
        goTop();
        preloader();

    });
})(jQuery);
