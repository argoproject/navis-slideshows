function ensureAllImagesAreLoaded( postID, count ) {
    for ( i = 1; i <= count; i++ ) {
        ensureImageIsLoaded( postID + "-slide" + i );
    }
}

function ensureImageIsLoaded( divID ) {
    var slideDiv = $( "#" + divID );
    // Do nothing if the slide image already exists
    if ( slideDiv.has( "img" ).length ) {
        return;
    }
    var imgData = slideDiv.attr("data-src");
    if ( imgData ) {
        var parts = imgData.split("*");
        var img = $("<img/>")
            .attr("src", parts[0])
            .attr("width", parts[1])
            .attr("height", parts[2]);
        slideDiv.prepend( img );
    }
}

jQuery( document ).ready( function() {
    var $ = jQuery;

    var startSlide = 1;
    var startHash = "#1";
    var slidePerma = "' . $plink . '";
    var totalSlides = ' . $count . ';
    jQuery("#slides-' . $postid . ' a.slide-permalink").attr("href", slidePerma + startHash);

    // Get slide number if it exists
    if (window.location.hash) {
        startSlide = window.location.hash.replace("#","");
        ensureImageIsLoaded("' . $postid . '-slide" + startSlide);
        ensureAllImagesAreLoaded("' . $postid . '", totalSlides);
        jQuery("#slides-'.$postid.' a.slide-permalink").attr("href", slidePerma + "#" + startSlide);
    }

    jQuery("#slides-'.$postid.'").slides({
        generatePagination: true,
        autoHeight: true,
        autoHeightSpeed: 0,
        effect: "fade",
        // Get the starting slide
        start: startSlide,
        animationComplete: function(current) {
            // Set the slide number as a hash
            var curSlide = "#" + current;
            ensureImageIsLoaded("' . $postid . '-slide" + current);
            var nextSlide = current + 1;
            var nextSlideDivID = "' . $postid . '-slide" + nextSlide;
            ensureImageIsLoaded(nextSlideDivID);
            jQuery("#slides-'.$postid.' a.slide-permalink").attr("href", slidePerma + curSlide);
        }
    });

    $(".navis-slideshow .pagination a").click(function(evt) {
        var slideNum = $(this).text();
        var nextSlide = parseInt( slideNum ) + 1;
        ensureImageIsLoaded("' . $postid . '-slide" + slideNum);
        ensureImageIsLoaded("' . $postid . '-slide" + nextSlide);
    });
});
