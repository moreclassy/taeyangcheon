/* ------------------------------------------------
  Project:   Misto - Factory and Industrial HTML5 Template
  Build:     Bootstrap 4.1.1
  Author:    ThemeHt
------------------------------------------------ */
/* ------------------------
    Table of Contents

  1. Predefined Variables
  3. FullScreen
  4. Slit Slider
  5. Counter
  3. Owl carousel
  7. Audioplayer
  9. Magnific Popup
  10. Isotope
  11. Scroll to top
  12. Banner Section
  13. Fixed Header
  14. Text Color, Background Color And Image
  15. Accordian
  16. Contact Form
  17. Searchbox
  18. ProgressBar
  19. Masonry
  20. Countdown
  21. Mailchimp
  22. jarallax
  23. Particles
  24. HT Window load and functions
  

------------------------ */

"use strict";

/*------------------------------------
  HT Predefined Variables
--------------------------------------*/
var $window = $(window),
  $document = $(document),
  $body = $('body'),
  $counter = $('.counter'),
  $fullScreen = $('.fullscreen-banner') || $('.section-fullscreen'),
  $halfScreen = $('.halfscreen-banner');

//Check if function exists
$.fn.exists = function () {
  return this.length > 0;
};

/*------------------------------------
  HT FullScreen
--------------------------------------*/
function fullScreen() {
  if ($fullScreen.exists()) {
    $fullScreen.each(function () {
      var $elem = $(this),
        elemHeight = $window.height();
      if ($window.width() < 768) $elem.css('height', elemHeight / 1);
      else $elem.css('height', elemHeight);
    });
  }
  if ($halfScreen.exists()) {
    $halfScreen.each(function () {
      var $elem = $(this),
        elemHeight = $window.height();
      $elem.css('height', elemHeight / 2);
    });
  }
};

/*------------------------------------
  HT Slit Slider
--------------------------------------*/
function slitslider() {
  if (!$.fn.slitslider || !$('#slider').length) return;
  var Page = (function () {
    var $navArrows = $('#nav-arrows'),
      $nav = $('#nav-dots > button'),
      slitslider = $('#slider').slitslider({
        // 자동 넘김·방향키 가로채기 없음 (접근성). 사진은 #nav-dots 버튼으로 바꿈
        autoplay: false,
        keyboard: false,
        onBeforeChange: function (slide, pos) {
          $nav.removeClass('nav-dot-current');
          $nav.eq(pos).addClass('nav-dot-current');
        }
      }),
      init = function () {
        initEvents();
      },
      initEvents = function () {
        // add navigation events
        $navArrows.children(':last').on('click', function () {
          slitslider.next();
          return false;
        });
        $navArrows.children(':first').on('click', function () {
          slitslider.previous();
          return false;
        });
        $nav.each(function (i) {
          $(this).on('click', function (event) {
            var $dot = $(this);
            if (!slitslider.isActive()) {
              $nav.removeClass('nav-dot-current');
              $dot.addClass('nav-dot-current');
            }
            slitslider.jump(i + 1);
            return false;
          });
        });
      };
    return {
      init: init
    };
  })();
  Page.init();
};

/*------------------------------------
  HT Counter
--------------------------------------*/
function counter() {
  if ($counter.exists()) {
    $counter.each(function () {
      var $elem = $(this);
      $elem.appear(function () {
        $elem.find('.count-number').countTo();
      });
    });
  }
};

/*------------------------------------
  HT Owl Carousel
--------------------------------------*/
function owlcarousel() {
  if (!$.fn.owlCarousel) return;
  $('.owl-carousel').each(function () {
    var $carousel = $(this);
    // owl 이 이전/다음 버튼에 붙이는 role="presentation" 을 떼어 스크린리더가 버튼으로 읽게 함
    $carousel.on('initialized.owl.carousel', function () {
      $carousel.find('.owl-nav button').removeAttr('role');
    });
    $carousel.owlCarousel({
      items: $carousel.data("items"),
      slideBy: $carousel.data("slideby"),
      center: $carousel.data("center"),
      loop: true,
      margin: $carousel.data("margin"),
      dots: $carousel.data("dots"),
      nav: $carousel.data("nav"),
      autoplay: $carousel.data("autoplay"),
      autoplayTimeout: $carousel.data("autoplay-timeout"),
      navText: ['<svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg><span class="sr-only">이전 사진</span>', '<svg class="icon" aria-hidden="true"><use href="#i-arrow-right"/></svg><span class="sr-only">다음 사진</span>'],
      responsive: {
        0: {
          items: $carousel.data('xs-items') ? $carousel.data('xs-items') : 1
        },
        576: {
          items: $carousel.data('sm-items')
        },
        768: {
          items: $carousel.data('md-items')
        },
        1024: {
          items: $carousel.data('lg-items')
        },
        1200: {
          items: $carousel.data("items")
        }
      },
    });
  });
};

/*------------------------------------
  HT Audio Player
--------------------------------------*/
function lightgallery() {
  if (!$.fn.audioPlayer) return;
  $('audio').audioPlayer();
};

/*------------------------------------
  HT Magnific Popup
--------------------------------------*/
function magnificpopup() {
  if (!$.fn.magnificPopup) return;
  $('.popup-gallery').magnificPopup({
    delegate: 'a.popup-img',
    type: 'image',
    tLoading: '사진을 불러오는 중...',
    tClose: '닫기 (Esc)',
    mainClass: 'mfp-img-mobile',
    gallery: {
      enabled: true,
      navigateByImgClick: true,
      preload: [0, 1], // Will preload 0 - before current, and 1 after the current image
      tPrev: '이전 사진 (왼쪽 방향키)',
      tNext: '다음 사진 (오른쪽 방향키)',
      tCounter: '%curr% / %total%'
    },
    image: {
      tError: '<a href="%url%">사진</a>을 불러오지 못했습니다.',
      titleSrc: function (item) {
        return item.el.attr('title') + '<small>by 태양천 그루빙</small>';
      }
    }
  });
  if ($(".popup-youtube, .popup-vimeo, .popup-gmaps").exists()) {
    $('.popup-youtube, .popup-vimeo, .popup-gmaps').magnificPopup({
      type: 'iframe',
      mainClass: 'mfp-fade',
      removalDelay: 160,
      preloader: false,
      fixedContentPos: false
    });
  }
};

/*------------------------------------
  HT Isotope
--------------------------------------*/
function isotope() {
  if (!$.fn.isotope || !$('.grid').length) return;
  // init Isotope
  var $grid = $('.grid').isotope({
    itemSelector: '.grid-item',
    layoutMode: 'fitRows',
  });
  // filter functions
  var filterFns = {
    // show if number is greater than 50
    numberGreaterThan50: function () {
      var number = $(this).find('.number').text();
      return parseInt(number, 10) > 50;
    },
    // show if name ends with -ium
    ium: function () {
      var name = $(this).find('.name').text();
      return name.match(/ium$/);
    }
  };
  // bind filter button click
  $('.portfolio-filter').on('click', 'button', function () {
    var filterValue = $(this).attr('data-filter');
    // use filterFn if matches value
    filterValue = filterFns[filterValue] || filterValue;
    $grid.isotope({
      filter: filterValue
    });
  });
  // change is-checked class on buttons
  $('.portfolio-filter').each(function (i, buttonGroup) {
    var $buttonGroup = $(buttonGroup);
    $buttonGroup.on('click', 'button', function () {
      $buttonGroup.find('.is-checked').removeClass('is-checked');
      $(this).addClass('is-checked');
    });
  });
};

/*------------------------------------
  HT Scroll to top
--------------------------------------*/
function scrolltop() {
  var $goToTop = $('#scroll-top');
  $goToTop.hide();
  $window.on('scroll', function () {
    if ($window.scrollTop() > 100) $goToTop.fadeIn();
    else $goToTop.fadeOut();
  });
  $goToTop.on("click", function () {
    $('body,html').animate({
      scrollTop: 0
    }, 1000);
    return false;
  });
};

/* HT Banner Section: 히어로 글자를 헤더 높이만큼 내리던 headerheight() 는 CSS(.fullscreen-banner .align-center padding-top)로 대체 (로드 후 글자가 밀리는 CLS 원인) */

/*------------------------------------
  HT Fixed Header
--------------------------------------*/
function fxheader() {
  $(window).on('scroll', function () {
    if ($(window).scrollTop() >= 100) {
      $('#header-wrap').addClass('fixed-header');
    } else {
      $('#header-wrap').removeClass('fixed-header');
    }
  });
};

/*------------------------------------------
  HT Text Color, Background Color And Image
---------------------------------------------*/
function databgcolor() {
  $('[data-bg-color]').each(function (index, el) {
    $(el).css('background-color', $(el).data('bg-color'));
  });
  $('[data-text-color]').each(function (index, el) {
    $(el).css('color', $(el).data('text-color'));
  });
  $('[data-bg-img]').each(function () {
    $(this).css('background-image', 'url(' + $(this).data("bg-img") + ')');
  });
};

/*------------------------------------
  HT Accordian
--------------------------------------*/
function accordian() {
  $(".card").on("show.bs.collapse hide.bs.collapse", function (e) {
    if (e.type == 'show') {
      $(this).addClass('active');
    } else {
      $(this).removeClass('active');
    }
  });
  $('.accordion .card-header a').prepend('<span aria-hidden="true"></span>');
};

/*------------------------------------
  HT Contact Form
--------------------------------------*/
function contactform() {
  if (!$.fn.validator) return;
  $('#contact-form, #queto-form').validator();
  // when the form is submitted
  $('#contact-form, #queto-form').on('submit', function (e) {
    // if the validator does not prevent form submit
    if (!e.isDefaultPrevented()) {
      var url = "php/contact.php";
      // POST values in the background the the script URL
      var $form = $(this);
      var $btn = $form.find('button[type="submit"]').prop('disabled', true);
      var siteType = $form.find('[name="site_type"]').val() || '';
      var purpose = $form.find('[name="purpose"]').val() || '';
      var showAlert = function (type, text) {
        $form.find('.messages').html('<div class="alert alert-' + type + ' alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' + text + '</div>');
      };
      $.ajax({
        type: "POST",
        url: url,
        data: $form.serialize(),
        dataType: "json",
        complete: function () { $btn.prop('disabled', false); },
        error: function (xhr) {
          var msg = (xhr.responseJSON && xhr.responseJSON.message) || '전송에 실패했습니다. 잠시 후 다시 시도하시거나 전화로 문의해주세요.';
          showAlert('danger', msg);
        },
        success: function (data) {
          // data = JSON object that contact.php returns
          // we recieve the type of the message: success x danger and apply it to the 
          var messageAlert = 'alert-' + data.type;
          var messageText = data.message;
          // let's compose Bootstrap alert box HTML
          var alertBox = '<div class="alert ' + messageAlert + ' alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' + messageText + '</div>';
          // If we have messageAlert and messageText
          if (messageAlert && messageText) {
            // inject the alert to .messages div in our form
            $('#contact-form, #queto-form').find('.messages').html(alertBox);
            // empty the form
            $('#contact-form, #queto-form')[0].reset();
            // GA4 전환 이벤트 (js/analytics.js 에 측정 ID가 설정된 경우에만 동작)
            if (data.type === 'success' && typeof window.gtag === 'function') {
              window.gtag('event', 'generate_lead', { site_type: siteType, purpose: purpose });
            }
          }
        }
      });
      return false;
    }
  })
  $('.contact-btn').bind('click', function () {
    if ($(this).hasClass('active')) {
      $(this).removeClass('active');
      $('.contact-form').animate({
        right: '-450px'
      });
    } else {
      $('.contact-form').animate({
        right: '0'
      });
      $(this).addClass('active');
    }
  });
  $('.close-btn').bind('click', function () {
    $('.contact-form').animate({
      right: '-450px'
    });
  });
};

/*------------------------------------
  HT ProgressBar
--------------------------------------*/
function progressbar() {
  var progressBar = $('.progress');
  if (progressBar.length) {
    progressBar.each(function () {
      var Self = $(this);
      Self.appear(function () {
        var progressValue = Self.data('value');
        Self.find('.progress-bar').animate({
          width: progressValue + '%'
        }, 1000);
      });
    })
  }
};

/*------------------------------------
  HT Masonry
--------------------------------------*/
function masonry() {
  var $masonry = $('.masonry'),
    $itemElement = '.masonry-brick',
    $filters = $('.portfolio-filter');
  if ($masonry.exists() && $.fn.isotope) {
    $masonry.isotope({
      resizable: true,
      itemSelector: $itemElement,
    });
    // bind filter button click
    $filters.on('click', 'button', function () {
      var filterValue = $(this).attr('data-filter');
      $masonry.isotope({
        filter: filterValue
      });
    });
  }
};

/*------------------------------------
  HT Countdown
--------------------------------------*/
function countdown() {
  if (!$.fn.countdown) return;
  $(".countdown").countdown('2018/09/23 00:00', function (event) {
    $(this).html(event.strftime('<li><span>%-D</span><p>Days</p></li>' + '<li><span>%-H</span><p>Hours</p></li>' + '<li><span>%-M</span><p>Minutes</p></li>' + '<li><span>%S</span><p>Seconds</p></li>'));
  });
};

/*------------------------------------
  HT jarallax
--------------------------------------*/
function jarallax() {
  if (!$.fn.jarallax) return;
  // 모바일은 패럴랙스 없이 배경을 바로 표시 (배경 이미지는 HTML inline style 로 먼저 칠해 둠)
  $('.jarallax').jarallax({ disableParallax: /iPad|iPhone|iPod|Android/ });
};

/*------------------------------------
  HT Particles
--------------------------------------*/
function particles() {
  if (!$.fn.particleground || !$('#particles').length) return;
  $('#particles').particleground({
    dotColor: '#555',
    lineColor: 'rgba(255,255,255,0.1)'
  });
};

/*------------------------------------
  HT Window load and functions
--------------------------------------*/
$(document).ready(function () {
  owlcarousel(),
  fullScreen(),
  slitslider(),
  counter(),
  lightgallery(),
  magnificpopup(),
  scrolltop(),
  fxheader(),
  databgcolor(),
  accordian(),
  contactform(),
  progressbar(),
  countdown(),
  jarallax(),
  particles();
});

$window.resize(function () {
  fullScreen();
});

$(window).on('load', function () {
  isotope(),
  masonry();
});