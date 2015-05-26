/**
 * Forum pagination
 * 
 * @var object settings
 */
ForumPagination = function(settings)
{
    /**
     * Request processed
     * @var boolean
     */    
    var requestProcessed = true;

    /**
     * Page number
     * @var integer
     */
    var page = 1;

    /**
     * Allow paginate
     * @var boolean
     */
    var allowPaginate = true;

   /**
    * Paginate settings
    * @var object
    */
    var paginateSettings = {
        "paginateUrl"        : "",
        "paginateWrapper"    : $([]),
        "preloader"          : $([]),
        "unitCssClass"       : ""
    };

    paginateSettings = $.extend(paginateSettings, settings);

    //-- bind events --//

    //  listen to scroll window
    $(window).scroll(function()
    {
        if ( allowPaginate && requestProcessed ) 
        {                                                      
            if( $(window).scrollTop() + $(window).height() == $(document).height() ) 
            {
                page += 1;
                var excludeIds = [];

                // get already rendered units list
                if ( paginateSettings.unitCssClass )
                {
                    $.each(paginateSettings.paginateWrapper.find(paginateSettings.unitCssClass), function( index, unit ) 
                    {
                       excludeIds.push($(unit).attr('data-id'));
                    });
                }

                // show preloader
                paginateSettings.preloader.show();
                requestProcessed = false;

                $.ajax({
                    "method": paginateSettings.unitCssClass ? "POST" : "GET",
                    "url": paginateSettings.paginateUrl,
                    "cache": false,
                    "data" : paginateSettings.unitCssClass ? { "page" : page, "excludeIds" : excludeIds } : { "page" : page },
                    "success": function(data)
                    {
                        // hide preloader
                        paginateSettings.preloader.hide();

                        if ( !$.trim(data) )
                        {
                            allowPaginate = false;
                            return;
                        }

                        requestProcessed = true;
                        paginateSettings.paginateWrapper.append(data);
                    }
                });
            }
        }
    });
}