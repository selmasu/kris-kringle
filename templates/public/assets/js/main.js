$(document).ready(function() {
    
    
    /* ======= Scrollspy ======= */
   $('body').scrollspy({ target: '#page-nav-wrapper', offset: 100});
    
    /* ======= ScrollTo ======= */
    $('.scrollto').on('click', function(e){
        
        //store hash
        var target = this.hash;
                
        e.preventDefault();
        
		$('body').scrollTo(target, 800, {offset: -62, 'axis':'y'});
		
	});
	
	$('.scrollto-no-offset').on('click', function(e){
        
        //store hash
        var target = this.hash;
                
        e.preventDefault();
        
		$('body').scrollTo(target, 800, {offset: 0, 'axis':'y'});
		
	});
	
	/* ======= Fixed page nav when scrolled ======= */    
    $(window).on('scroll', function() {
        
        $('#page-nav-wrapper').removeClass('fixed');
         
         var scrollTop = $(this).scrollTop();
                  
         // Check fixednav contains any element before get the offset - https://stackoverflow.com/questions/20175094/uncaught-typeerror-cannot-read-property-top-of-undefined
         var fixednav = $('#page-nav-wrapper');
         if  (fixednav.length) {
	        var topDistance = $('#page-nav-wrapper').offset().top;
         }
         
         if ( (topDistance) > scrollTop ) {
            $('#page-nav-wrapper').removeClass('fixed');
            $('body').removeClass('sticky-page-nav');
         }
         else {
            $('#page-nav-wrapper').addClass('fixed');
            $('body').addClass('sticky-page-nav');
         }

    });
	
	/* ======= Stop Video Playing When Close the Modal Window ====== */
    $("#modal-video .close").on("click", function() {
        $("#modal-video iframe").attr("src", $("#modal-video iframe").attr("src"));        
    });
    
    /* ======= FAQ accordion ======= */
    $('.card-toggle').on('click', function () {
      if ($(this).find('svg').attr('data-icon') == 'plus-square' ) {
        $(this).find('svg').attr('data-icon', 'minus-square');
      } else {
        $(this).find('svg').attr('data-icon', 'plus-square');
      };
    });
    
    /* ======= Toggle between Signup & Login & ResetPass Modals ======= */ 
    $('#signup-link').on('click', function(e) {
        $('#login-modal').modal('toggle');
        $('#signup-modal').modal();
        
        e.preventDefault();
    });
    
    $('#login-link').on('click', function(e) {
        $('#signup-modal').modal('toggle');
        $('#login-modal').modal();
        
        e.preventDefault();
    });
    
    $('#back-to-login-link').on('click', function(e) {
        $('#resetpass-modal').modal('toggle');
        $('#login-modal').modal();
        
        e.preventDefault();
    });
    
    $('#resetpass-link').on('click', function(e) {
        $('#login-modal').modal('hide');
        e.preventDefault();
    });
     
    
     /* ======= Testimonial Bootstrap Carousel ======= */
     /* Ref: http://getbootstrap.com/javascript/#carousel */
    $('#testimonials-carousel').carousel({
      interval: 8000 
    });
    
    /* ======= Play/Stop YouTube/Vimeo Video in Bootstrpa Modal ======= */
    

    $('.play-trigger').on('click', function() {
        
        var theModal = $(this).data("target");
        var theVideo = $(theModal + ' iframe').attr('src');
        var theVideoAuto = theVideo + "&amp;autoplay=1";
        
        $(theModal).on('shown.bs.modal', function () {
            $(theModal + ' iframe').attr('src', theVideoAuto);
        });
        
        $(theModal).on('hide.bs.modal', function () {
            $(theModal + ' iframe').attr('src', '');
        });
        
        $(theModal).on('hidden.bs.modal', function () {
            $(theModal + ' iframe').attr('src', theVideo);
        });
 
    });
    

});