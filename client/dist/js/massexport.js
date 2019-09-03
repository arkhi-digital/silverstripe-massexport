var massExportApp={
    Init: function() {
        if (jQuery('.massexport-form').length==0 || jQuery('.massexport-form.initialized').length>0) {
            return;
        }
        jQuery('.massexport-form .massexport-email-button').on('click', function() {
           jQuery('#massexport_email').dialog({
                   modal: true
               }
           );
        });
        jQuery('.massexport-form').on('submit', function() {
            jQuery('#MassExportForm_ExportForm_error').hide();
            jQuery(this).children('.alert').hide();
        });
        jQuery('.massexport-form').addClass('initialized');
    }
};

jQuery(function(){
    jQuery('html').on('ajaxComplete', function(){
        massExportApp.Init();
    });
    setTimeout(massExportApp.Init, 250);
});
