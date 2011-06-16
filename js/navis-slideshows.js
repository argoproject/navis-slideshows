function ensureAllImagesAreLoaded( postID, count ) {
    for ( i = 1; i <= count; i++ ) {
        ensureImageIsLoaded( postID + "-slide" + i );
    }
}

function ensureImageIsLoaded( divID ) {
    var slideDiv = jQuery( "#" + divID );
    // Do nothing if the slide image already exists
    if ( slideDiv.has( "img" ).length ) {
        return;
    }
    var imgData = slideDiv.attr("data-src");
    if ( imgData ) {
        var parts = imgData.split("*");
        var img = jQuery("<img/>")
            .attr( "src", parts[0] )
            .attr( "width", parts[1] )
            .attr( "height", parts[2] );
        slideDiv.prepend( img );
    }
}

function getSlideElement( postID, slideNum ) {
    return postID + '-slide' + slideNum;
}

function loadSlideshow( postID, permalink, totalSlides ) {
    var startSlide = 1;
    var startHash = "#1";
    var slideContainerDiv = '#slides-' + postID;
    var slidePermalinkElement = slideContainerDiv + ' a.slide-permalink';

    jQuery( slidePermalinkElement ).attr( "href", permalink + startHash );

    // Get slide number if it exists
    if ( window.location.hash ) {
        startSlide = window.location.hash.replace( "#","" );
        if ( parseInt( startSlide ) ) {
            ensureImageIsLoaded( getSlideElement( postID, startSlide ) );
            ensureAllImagesAreLoaded( postID, totalSlides );
            jQuery( slidePermalinkElement ).attr( "href", permalink + "#" + startSlide );
        }
    }

    jQuery( slideContainerDiv ).slides({
        generatePagination: true,
        autoHeight: true,
        autoHeightSpeed: 0,
        effect: "fade",
        // Get the starting slide
        start: startSlide,
        animationComplete: function( current ) {
            // Set the slide number as a hash
            var curSlide = "#" + current;

            ensureImageIsLoaded( getSlideElement( postID, current ) );
            var nextSlide = current + 1;
            ensureImageIsLoaded( getSlideElement( postID, nextSlide ) );
            jQuery( slidePermalinkElement ).attr("href", permalink + curSlide);
        }
    });

    jQuery( ".navis-slideshow .pagination a" ).click( function( evt ) {
        var slideNum = jQuery(this).text();
        var nextSlide = parseInt( slideNum ) + 1;
        ensureImageIsLoaded( getSlideElement( postID, slideNum ) );
        ensureImageIsLoaded( getSlideElement( postID, nextSlide ) );
    });
}
