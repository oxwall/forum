/**
 * Forum attachments
 * 
 * @var object settings
 */
ForumAttachments = function(settings)
{
   /**
    * Files list
    * @var array
    */
   var filesList = [];

   /**
    * Attachments settings
    * @var array
    */
   var attachmentsSettings = {
       "attachmentUid"        : "",
       "attachmentsWrapper"   : $([]),
       "attachDefaultTitle"   : $([]),
       "firstAttachName"      : $([]),
       "clearFiles"           : $([]),
       "attachNewFiles"       : $([]),
       "attachNewFile"        : $([]),
       "deleteAttachmentsUrl" : ""
   };

   attachmentsSettings = $.extend(attachmentsSettings, settings);

   //--private functions --//

   /**
    * Get attached files name
    *
    * @return string
    */
   var getAttachedFilesName = function()
   {
       var length = filesList.length;

       if ( length )
       {
           if ( length > 1 )
           {
               return length + " " + OWM.getLanguageText("forum", "attached_files");
           }

           // get the first file info
           var file = filesList[0];
           return file.name + " (" + file.size + "KB)";
       }

       return "";
   } 

   //-- bind events --//

   //  listen to upload a new file or show the files list
   $(attachmentsSettings.attachNewFile).unbind().bind("click", function(e)
   {
       e.preventDefault();

       // allow upload only a first file
       if ( !filesList.length )
       {
           attachmentsSettings.attachmentsWrapper.find(".mlt_file_input").trigger("click");
       }
       else
       {
           // show the manage files list window
           OWM.FloatBox({
               "title" : OWM.getLanguageText("forum", "post_attachment"),
               "content": attachmentsSettings.attachmentsWrapper
           });
       }
   });

   // listen to attach new files event
   attachmentsSettings.attachNewFiles.unbind().bind("click", function()
   {
       $(attachmentsSettings.attachmentsWrapper).find(".mlt_file_input").trigger("click");
   });

   // listen to clear all attached files event
   $(attachmentsSettings.clearFiles).unbind().bind("click", function()
   {
        if ( filesList.length && confirm(OW.getLanguageText('forum', 'confirm_delete_all_attachments')) )
        {
            owFileAttachments[attachmentsSettings.attachmentUid].reset(attachmentsSettings.attachmentUid, function(items){
                // collect list of uploaded files
                 var ids = [];

                 $.each(items, function(index, data) 
                 {
                     if ( typeof data.dbId != "undefined" && typeof data.customDeleteUrl != "undefined")
                     {
                         ids.push(data.dbId);
                     }
                 });

                 if ( ids.length )
                 {
                     $.post(attachmentsSettings.deleteAttachmentsUrl, {'id' : ids});
                 }
            });

            attachmentsSettings.attachDefaultTitle.show();
            attachmentsSettings.firstAttachName.text("").hide();
            filesList = [];
        }
   });

   // listen to upload new files event
   OW.bind("base.attachment_rendered", function(item) 
   {
       // we should listen only our attachment instance
       if (attachmentsSettings.attachmentUid != this.uid)
       {
           return;
       }

       // remember the added file
       var file = item.data;
       filesList.push({ id: file.id, name: file.name, size: file.size });

       // show the single attached name
       if ( !attachmentsSettings.firstAttachName.text() )
       {
           attachmentsSettings.attachDefaultTitle.hide();
           attachmentsSettings.firstAttachName.text(getAttachedFilesName()).show();
           return;
       }

       // update the attach name
       attachmentsSettings.firstAttachName.text(getAttachedFilesName());
   });

   // listen to delete files event
   OW.bind("base.attachment_deleted", function(data) 
   {
       // we should listen only our attachment instance
       if (attachmentsSettings.attachmentUid != this.uid)
       {
           return;
       }

       // delete the file
       filesList = filesList.filter(function (el) 
       {
           return el.id != data.id;
       });

       // display the default attach title
       if ( !filesList.length )
       {
           attachmentsSettings.attachDefaultTitle.show();
           attachmentsSettings.firstAttachName.text("").hide();
           return;
       }

       // update the first attach file name
       attachmentsSettings.firstAttachName.text(getAttachedFilesName()).show();
   });

    //-- public functions --//

    /**
     * Render uploaded attachments
     * 
     * @param object attachedFiles
     * @return void
     */
    this.renderUploadedAttachments = function( attachedFiles )
    {
        owFileAttachments[attachmentsSettings.attachmentUid].renderUploaded(attachedFiles, 
                attachmentsSettings.deleteAttachmentsUrl, OW.getLanguageText('forum', 'confirm_delete_attachment'));
    }
}