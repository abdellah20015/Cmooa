(function($) {
    var currentItem;
    var app;
    app = {
        init: function() {
            this._menu();
            this._slick();
            this._hasFnd();
            this._select();
            this._tabs();
            this._popup();
            this._accordion();
            this._popupusers();
            this._detailgallery();
        },

        _menu: function(){
            $('.btn-menu').on('click',function(){
                if($(this).hasClass('open')){
                    $(this).removeClass('open');
                    $('.inner-menu').removeClass('active');
                } else {
                    $(this).addClass('open');
                    $(".inner-menu").addClass('active');
                }
            });
            $( ".nav li:has(ul)" ).mouseenter(function() {
                $(this).find('.sub-menu').show();
              }).mouseleave(function() {
                $(this).find('.sub-menu').hide();
              });


        },
        _hasFnd: function(){
            $('.hasfnd').each(function(){
                var src = $(this).find('img').attr('src');
                $(this).css('backgroundImage','url('+ src +')');
            });
        },
        _slick: function(){
            // slide home
            var time = 4;
            var $bar,
                $slick,
                isPause,
                tick,
                percentTime;
            $slick = $('.slider-home');
            $slick.slick({
                slidesToShow: 1,
                cssEase: 'cubic-bezier(.17,.67,.83,.67)',
                swipe: false,
                speed: 1000,
            });
            setTimeout(function(){
                $('#block-slide .slick-current.slick-active').addClass('active');
            }, 1000);
            $(".slider-home").on('beforeChange', function(event, slick, currentSlide, nextSlide){
                // $('#block-slide .slick-current.slick-active').addClass('z-index-p');
                $('#block-slide .item-slide').removeClass('active');
            });

            $(".slider-home").on('afterChange', function(event, slick, currentSlide, nextSlide){
                // $('#block-slide .item-slide').removeClass('z-index-p');
                $('#block-slide .slick-current.slick-active').addClass('active');
                setTimeout(function(){

                }, 1000);

            });
            // progress bare
            $bar = $('.slider-progress .progress');
            $('.slick-arrow').on('click', function(){
                percentTime = 0;
                $bar.css({
                    width: 0+'%'
                    });
              });

            // $('.item-slide.hasfnd').on({
            //     mouseenter: function() {
            //     isPause = true;
            //     },
            //     mouseleave: function() {
            //     isPause = false;
            //     }
            // })

            function startProgressbar() {
                resetProgressbar();
                percentTime = 0;
                isPause = false;
                tick = setInterval(interval, 10);
            }

            function interval() {
                if(isPause === false) {
                percentTime += 1 / (time+0.1);
                $bar.css({
                    width: percentTime+"%"
                });
                if(percentTime >= 100)
                    {
                    $slick.slick('slickNext');
                    startProgressbar();
                    clearInterval(tick);
                    setTimeout(function(){ tick = setInterval(interval, 10); }, 1000);
                    }
                }
            }


            function resetProgressbar() {
                $bar.css({
                width: 0+'%'
                });
                clearTimeout(tick);
            }

            startProgressbar();
            // fin slide home

            // slide oeuvres majeurs

            $('.slide-majeurs').slick({
                infinite: false,
                speed: 300,
                variableWidth: true,
                swipeToSlide:true,
                slidesToScroll: 4,
                slidesToShow:4 ,
                nextArrow : $(".next-01"),
                prevArrow: $(".prev-01"),
                responsive: [
                    {
                      breakpoint: 767,
                      settings: {
                        slidesToShow: 2,
                        slidesToScroll: 2,
                      }
                    }
                  ]
              });
            //   slider calendrier
            $('.slider-calendrier').slick({
                infinite: false,
                speed: 300,
                variableWidth: true,
                swipeToSlide:true,
                slidesToScroll: 4,
                slidesToShow:4 ,
                nextArrow : $(".next-02"),
                prevArrow: $(".prev-02"),
                responsive: [
                    {
                      breakpoint: 767,
                      settings: {
                        slidesToShow: 2,
                        slidesToScroll: 2,
                      }
                    }
                  ]
              });
            //   slider encheres
            $('.slider-encheres').slick({
                infinite: false,
                speed: 300,
                variableWidth: true,
                swipeToSlide:true,
                slidesToScroll: 4,
                slidesToShow:4 ,
                nextArrow : $(".next-03"),
                prevArrow: $(".prev-03"),
                responsive: [
                    {
                      breakpoint: 767,
                      settings: {
                        slidesToShow: 2,
                        slidesToScroll: 2,
                      }
                    }
                  ]
              });
            // slide home galerie
            $('.slide-galerie-home').slick({
                infinite: false,
                speed: 300,
                variableWidth: true,
                swipeToSlide:true,
                arrows: false
              });

            var isSliding = false;

            $('.slide-galerie-home').on('beforeChange', function() {
                isSliding = true;
            });

            $('.slide-galerie-home').on('afterChange', function() {
                isSliding = false;
            });

            $('.slide-galerie-home').find(".item-galerie-home").click(function(e) {
                if (isSliding) {
                    e.preventDefault();
                }
            });
            // slide sup vente
            $('.c-slide-up').slick({
                infinite: false,
                speed: 300,
                variableWidth: true,
                arrows: false,
                swipeToSlide:true
              });
            var slidingUP = false;

            $('.c-slide-up').on('beforeChange', function() {
                slidingUP = true;
            });

            $('.c-slide-up').on('afterChange', function() {
                slidingUP = false;
            });

            $('.c-slide-up').find(".item-slide-sup").click(function(e) {
                if (slidingUP) {
                    e.preventDefault();
                }
            });
            // slide detail galerie
            $('.items-detail').slick({
                infinite: false,
                speed: 300,
                variableWidth: true,
                arrows: false
              });

            $('.items-detail').on('beforeChange', function() {
                slidingUP = true;
            });

            $('.items-detail').on('afterChange', function() {
                slidingUP = false;
            });

            $('.items-detail').find(".item-detail-galerie").click(function(e) {
                if (slidingUP) {
                    e.preventDefault();
                }
            });
            //   slide live image

            $('.slide-ilus').slick({
                slidesToShow: 1,
                slidesToScroll: 1,
                arrows: false,
                fade: true
                // asNavFor: '.pager-slide-ilus'
              });
              $('.pager-slide-ilus').slick({
                // asNavFor: '.slide-ilus',
                dots: false,
                arrows: false,
                infinite: false,
                variableWidth: true
              });
              $('.item-pager').on('click',function(e){
                var index = $(this).data("slick-index");
                $('.slide-ilus').slick('slickGoTo', index);
              });

              $('.slide-calender').slick({
                dots: false,
                arrows: false,
                infinite: false,
                variableWidth: true
              });
              function mobileOnlySlider() {
                $('.col-visu-lot').slick({
                    autoplay: false,
                    speed: 1000,
                    autoplaySpeed: 5000,
                    variableWidth: true,
                    dots: false,
                    arrows: false,
                });
                $('.menu-client').slick({
                    infinite: false,
                    autoplay: false,
                    speed: 500,
                    variableWidth: true,
                    centerMode: true,
                    dots: false,
                    arrows: false,
                });
                $('.ntbr-item-lot .visu-item-other-lot').slick({
                    infinite: false,
                    autoplay: false,
                    speed: 500,
                    variableWidth: true,
                    dots: false,
                    arrows: false,
                });
                $('.menu-client').on('click', 'li', function(e)
                {
                    e.preventDefault();
                    e.stopPropagation();
                    var index = $(this).data("slick-index");
                    if ($('.slick-slider').slick('slickCurrentSlide') !== index) {
                    $('.slick-slider').slick('slickGoTo', index);
                    }
                });
            }
                if(window.innerWidth < 767) {
                    mobileOnlySlider();
                }

                $(window).resize(function(e){
                    if(window.innerWidth < 767) {
                        if(!$('.col-visu-lot').hasClass('slick-initialized')){
                            mobileOnlySlider();
                        }
                        if(!$('.menu-client').hasClass('slick-initialized')){
                            mobileOnlySlider();
                        }
                        if(!$('.ntbr-item-lot .visu-item-other-lot').hasClass('slick-initialized')){
                            mobileOnlySlider();
                        }

                    }else{
                        if($('.col-visu-lot').hasClass('slick-initialized')){
                            $('.col-visu-lot').slick('unslick');
                        }
                        if($('.menu-client').hasClass('slick-initialized')){
                            $('.menu-client').slick('unslick');
                        }
                        if($('.ntbr-item-lot .visu-item-other-lot').hasClass('slick-initialized')){
                            $('.ntbr-item-lot .visu-item-other-lot').slick('unslick');
                        }
                    }
                });

        },
        _customScrollBar:function(){

        },
        _select: function(){
            $('.form-select').selectric({
                nativeOnMobile: false,
            });
        },
        _tabs: function(){
            $('.tabs-listes a').on('click',function(e){
                e.preventDefault();
                addressValue = $(this).attr("href");
                if(!$(this).hasClass("active")){
                    $(this).parent().siblings().find('a').removeClass("active");
                    $('.inner-bosy-liste').hide();
                    $(this).addClass("active");
                    $(addressValue).show();
                }

            });
            $('.tabs-calender a').on('click',function(e){
                e.preventDefault();
                addressValue = $(this).attr("href");
                if(!$(this).hasClass("active")){
                    $(this).parent().siblings().find('a').removeClass("active");
                    $('.slide-calender').hide();
                    $(this).addClass("active");
                    $(addressValue).show();
                }

            });
            $('.menu-client a').on('click',function(e){
                e.preventDefault();
                addressValue = $(this).attr("href");
                if(!$(this).hasClass("active")){
                    $(this).parent().siblings().find('a').removeClass("active");
                    $('.inner-tabs').hide();
                    $(this).addClass("active");
                    $(addressValue).show();
                }
            });
            $('.hide-show-slide').on('click',function(e){
                e.preventDefault();
                $(this).toggleClass("active");
                $(".wrrap-arrow").toggleClass("hidden");
                $('.slider-encheres').slideToggle();
            });
            $('.icon-propo').on('click',function(e){
                e.preventDefault();
                $(this).next().slideToggle();
            });
            $('.item-propo').on('click',function(e){
                e.preventDefault();
                $(this).parent().slideUp();
            });

        },
        _popup: function(){
            $(".groupup").fancybox();
            $(".item-galerie-home").fancybox({
                type: "iframe"
            });
            $(".item-slide-sup").fancybox({
                type: "iframe"
            });
            $(".col-visu-lot a").fancybox();
            $(".item-detail-galerie").fancybox();
        },
        _accordion: function(){
            let accTitle = document.getElementsByClassName("acc-heading");
            let accContent = document.getElementsByClassName("acc-content");
            let singleMode = true;

            for( let j=0; j<accContent.length; j++ ){
                let realHeight = accContent[j].offsetHeight;
                accContent[j].setAttribute("data-height", realHeight + "px");
                accContent[j].style.height = 0;
            }

            for( let i=0; i<accTitle.length; i++ ){
                accTitle[i].onclick = function(){
                    let openedAcc = this.getAttribute('href').replace('#', '');

                    if( this.classList.contains("active") ){
                        this.classList.remove("active");
                        document.getElementById(openedAcc).style.height = 0;

                        return false;
                    }

                    if( singleMode ){
                        for(let k=0; k<accTitle.length; k++) {
                            accTitle[k].classList.remove("active");
                        }

                        for(let j=0; j<accContent.length; j++) {
                            accContent[j].style.height = 0;
                        }
                    }

                    this.classList.add("active");

                    document.getElementById(openedAcc).style.height = accContent[i].getAttribute("data-height");

                    return false;
                }
            }
            // accTitle[0].click();
            // let acctitle2 = document.getElementsByClassName("current-item-lot");
            // let acccontent2 = document.getElementsByClassName("ntbr-item-lot");
            // let singleMode2 = true;

            // for( let j=0; j<acccontent2.length; j++ ){
            //     let realHeight = acccontent2[j].offsetHeight;
            //     acccontent2[j].setAttribute("data-height", realHeight + "px");
            //     acccontent2[j].style.height = 0;
            // }

            // for( let i=0; i<acctitle2.length; i++ ){
            //     acctitle2[i].onclick = function(){
            //         let openedAcc = this.getAttribute('href').replace('#', '');

            //         if( this.classList.contains("active") ){
            //             this.classList.remove("active");
            //             document.getElementById(openedAcc).style.height = 0;

            //             return false;
            //         }

            //         if( singleMode2 ){
            //             for(let k=0; k<acctitle2.length; k++) {
            //                 acctitle2[k].classList.remove("active");
            //             }

            //             for(let j=0; j<acccontent2.length; j++) {
            //                 acccontent2[j].style.height = 0;
            //             }
            //         }

            //         this.classList.add("active");

            //         document.getElementById(openedAcc).style.height = acccontent2[i].getAttribute("data-height");

            //         return false;
            //     }
            // }
            // accTitle[0].click();
            $("a.item-other-lot").on('click',function(e){
                e.preventDefault();
                $(this).hide();
                $(this).next().show();
            });

        },
        _popupusers:function(){
            $('.show-detail').on('click',function(e){
                e.preventDefault();
                if(!$(this).next().hasClass("active")){
                    $('.detail-inner-users').removeClass('active');
                    $(this).next().addClass("active")
                 }
            });
            $('.hide-detail').on('click',function(e){
                e.preventDefault();
                $(this).parent().removeClass('active');
            });
            $('.infos-users a').on('click',function(e){
                e.preventDefault();
                $('.cover-liste').addClass('active');
                $('.content-liste').addClass('active');

            });
            $('.cover-liste').on('click',function(e){
                e.preventDefault();
                $(this).removeClass('active');
                $('.content-liste').removeClass('active');

            });
        },
        _detailgallery:function(){

            $('.type-liste').on('click',function(e){
                e.preventDefault();
                $(this).addClass('active');
                $(this).prev().removeClass("active")
                $(this).parents('.inner-detail').find('.items-detail').addClass("items-detail-liste");
                $(this).parents('.inner-detail').find('.items-detail').slick('unslick');
            });
            $('.type-slide').on('click',function(e){
                e.preventDefault();
                $(this).addClass('active');
                $(this).next().removeClass("active")
                $(this).parents('.inner-detail').find('.items-detail').removeClass("items-detail-liste");
                $(this).parents('.inner-detail').find('.items-detail').slick({
                    infinite: false,
                    speed: 300,
                    variableWidth: true,
                    arrows: false
                  });
            });
        }
    };

    $( document ).ready(function() {
        app.init();

    });
    $(window).on('load',function(){});
    $(window).resize(function(){
    });
    $(window).scroll(function(){
    });


}(jQuery));

function initMap() {
    map = new google.maps.Map(document.getElementById("CarteMaps"), {
        center: { lat: 33.57780022, lng: -7.69415259 },
        zoom: 15,
    });
}
