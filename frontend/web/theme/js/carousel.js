$(window).on("load", function () {
    if ($(".tf-swiper").length > 0) {
        $(".tf-swiper").each(function (index) {
            var swiperEl = this;
            var $swiper = $(swiperEl);

            if (swiperEl.swiper) return;

            var getData = function (name, fallback) {
                var value = $swiper.data(name);
                return value !== undefined && value !== "" ? value : fallback;
            };

            var getBool = function (name, fallback) {
                var value = $swiper.data(name);

                if (value === undefined || value === "") return fallback;
                if (value === true || value === "true") return true;
                if (value === false || value === "false") return false;

                return Boolean(value);
            };

            var getNumber = function (name, fallback) {
                var value = Number($swiper.data(name));
                return !isNaN(value) ? value : fallback;
            };

            var mobile = getNumber("mobile", 1);
            var mobileSm = getNumber("mobile-sm", mobile);
            var tablet = getNumber("tablet", 1);
            var desktop = getNumber("preview", 1);
            var laptop = getNumber("laptop", desktop);

            var spacing = getNumber("space", 0);
            var spacingMd = getNumber("space-md", spacing);
            var spacingLg = getNumber("space-lg", spacingMd);
            var spacingXxl = getNumber("space-xxl", spacingLg);

            var perGroup = getNumber("group", 1);
            var perGroupSm = getNumber("group-sm", perGroup);
            var perGroupMd = getNumber("group-md", perGroupSm);
            var perGroupLg = getNumber("group-lg", perGroupMd);

            var gridRows = getNumber("grid", 1);
            var speed = getNumber("speed", 800);
            var delay = getNumber("delay", 3000);
            var initSlide = getNumber("init", 0);

            var cursorType = getBool("cursor", false);
            var loop = getBool("loop", false);
            var autoplayEnabled = getBool("auto", false);
            var centered = getBool("center", false);

            var effect = getData("effect", "slide");
            var directionDesktop = getData("direction", "horizontal");
            var typePag = getData("type-pagination", "bullets");

            var $parentPag = $swiper.closest(".tf-pag-swiper");
            var paginationEl = $swiper.find(".tf-sw-pagination")[0] || $parentPag.find(".tf-sw-pagination")[0] || null;

            var $btnWrap = $swiper.closest(".tf-btn-swiper-main");
            var $container = $swiper.closest(".container");

            var nextEl = $btnWrap.find(".nav-next-swiper")[0] || $container.find(".swiper-action-group .nav-next-swiper")[0] || null;

            var prevEl = $btnWrap.find(".nav-prev-swiper")[0] || $container.find(".swiper-action-group .nav-prev-swiper")[0] || null;

            var swiperOptions = {
                direction: "horizontal",
                speed: speed,
                centeredSlides: centered,
                slidesPerView: mobile,
                spaceBetween: spacing,
                slidesPerGroup: perGroup,
                grabCursor: cursorType,
                loop: loop,
                effect: effect,
                initialSlide: initSlide,
                observer: true,
                observeParents: true,

                autoplay: autoplayEnabled
                    ? {
                          delay: delay,
                          disableOnInteraction: false,
                          pauseOnMouseEnter: true
                      }
                    : false,

                breakpoints: {
                    575: {
                        direction: "horizontal",
                        slidesPerView: mobileSm,
                        spaceBetween: spacing,
                        slidesPerGroup: perGroupSm
                    },
                    768: {
                        direction: "horizontal",
                        slidesPerView: tablet,
                        spaceBetween: spacingMd,
                        slidesPerGroup: perGroupMd
                    },
                    1200: {
                        direction: directionDesktop,
                        slidesPerView: desktop,
                        spaceBetween: spacingLg,
                        slidesPerGroup: perGroupLg
                    },
                    1600: {
                        direction: directionDesktop,
                        slidesPerView: laptop,
                        spaceBetween: spacingXxl,
                        slidesPerGroup: perGroupLg
                    }
                }
            };

            if (paginationEl) {
                swiperOptions.pagination = {
                    el: paginationEl,
                    clickable: true,
                    type: typePag
                };
            }

            if (nextEl || prevEl) {
                swiperOptions.navigation = {
                    nextEl: nextEl,
                    prevEl: prevEl
                };
            }

            if (gridRows > 1) {
                swiperOptions.grid = {
                    rows: gridRows,
                    fill: "row"
                };
            }

            var swiperT = new Swiper(swiperEl, swiperOptions);

            var $wrap = $swiper.closest(".swiper-action-wrap");

            $wrap.find(".swiper-button").off("mousemove.tfSwiper").on("mousemove.tfSwiper", function () {
                var slideIndex = $(this).data("slide");

                if (slideIndex !== undefined) {
                    swiperT.slideTo(Number(slideIndex), 500);
                }
            });

            if ($swiper.hasClass("swiper-scroll")) {
                var scrolling = false;
                var wheelEventName = "wheel.tfSwiperScroll" + index;

                var isInViewport = function (el) {
                    var rect = el.getBoundingClientRect();

                    return rect.top < window.innerHeight * 0.8 && rect.bottom > window.innerHeight * 0.2;
                };

                $(window).off(wheelEventName).on(wheelEventName, function (e) {
                    if (scrolling) return;
                    if (!isInViewport(swiperEl)) return;

                    scrolling = true;

                    if (e.originalEvent.deltaY > 0) {
                        swiperT.slideNext();
                    } else {
                        swiperT.slidePrev();
                    }

                    setTimeout(function () {
                        scrolling = false;
                    }, 400);
                });
            }
        });
    }
}); 

if ($(".tf-sw-slideshow").length > 0) {
    var tfSwSlideshow = $(".tf-sw-slideshow");
    var preview = tfSwSlideshow.data("preview");
    var tablet = tfSwSlideshow.data("tablet");
    var mobile = tfSwSlideshow.data("mobile");
    var spacing = tfSwSlideshow.data("space");
    var spacingMb = tfSwSlideshow.data("space-mb");
    var loop = tfSwSlideshow.data("loop");
    var play = tfSwSlideshow.data("auto-play");
    var centered = tfSwSlideshow.data("centered");
    var effect = tfSwSlideshow.data("effect");
    var speed =
        tfSwSlideshow.data("speed") !== undefined
            ? tfSwSlideshow.data("speed")
            : 1000;
    var swiperSlider = {
        autoplay: play
            ? {
                delay: 5000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            }
            : false,
        slidesPerView: mobile,
        loop: loop,
        spaceBetween: spacingMb,
        speed: speed,
        observer: true,
        observeParents: true,
        pagination: {
            el: ".sw-pagination-slider",
            clickable: true,
        },
        navigation: {
            clickable: true,
            nextEl: ".navigation-next-slider",
            prevEl: ".navigation-prev-slider",
        },
        centeredSlides: false,
        breakpoints: {
            768: {
                slidesPerView: tablet,
                spaceBetween: spacing,
                centeredSlides: false,
            },
            1200: {
                slidesPerView: preview,
                spaceBetween: spacing,
                centeredSlides: centered,
            },
        },
    };
    if (effect === "fade") {
        swiperSlider.effect = "fade";
        swiperSlider.fadeEffect = {
            crossFade: true,
        };
    }
    var swiper = new Swiper(".tf-sw-slideshow", swiperSlider);
}

if ($(".tf-sw-thumbs").length > 0) {
    var thumbSwiper = new Swiper(".sw-thumb", {
        slidesPerView: 1,
        watchSlidesProgress: true,
        watchSlidesVisibility: true,
        speed: 800,
        spaceBetween: 15,
        centeredSlides: true,
    });

    var mainSwiper = new Swiper(".sw-main-thumb", {
        grabCursor: true,
        spaceBetween: 15,
        speed: 800,
        navigation: {
            nextEl: ".tf-sw-thumbs .nav-next-swiper",
            prevEl: ".tf-sw-thumbs .nav-prev-swiper",
        },
        pagination: {
            el: ".sw-pg-thumb",
            clickable: true,
        },
    });
    thumbSwiper.controller.control = mainSwiper;
    mainSwiper.controller.control = thumbSwiper;
}



