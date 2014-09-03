jQuery(document).ready(function(){
					
	var file_checkbox	= null;
	var trash_onclick 	= null;
	var checkbox_value	= null;
	var publish_action	= null;
	
	file_checkbox = prso_frm_pluploader.input_field_html;
	
	//Check if we are on the trash edit page
	publish_action = jQuery('div#publishing-action .button-primary');
	
	//console.log(prso_frm_pluploader);
	
	//If NOT trash edit view
	if( publish_action.length !== 0 ) {
		
		//Prepend checkbox html above trash link
		jQuery('div#major-publishing-actions').prepend(file_checkbox);
		
		//Remove the default onclick
		jQuery('a.submitdelete').removeAttr('onclick');
		
		//Intercept trash click
		jQuery('a.submitdelete').click(function(event){
			//Warn user that not deleting files unless checbox is checked
			if( !jQuery('#prso_pluploader_delete_uploads').is(':checked') ) {
				
				if( !confirm(prso_frm_pluploader.file_delete_message) ) {
					event.preventDefault();
				} else {
					//Carry out formidable default actions
					prsoFrmTrashActions();
				}
				
			} else {
				//Carry out formidable default actions
				prsoFrmTrashActions();
			}
			
		})
		
		//Get delete file meta for this entry and set the delete checkbox
		if( prso_frm_pluploader.file_delete_meta === 'checked' ) {
			jQuery('#prso_pluploader_delete_uploads').attr('checked', 'checked');
		} else {
			jQuery('#prso_pluploader_delete_uploads').removeAttr('checked');
		}
		
	}
	
	//Carry out formidable default actions
	function prsoFrmTrashActions() {
		
		jQuery('#action').val('trash'); 
		jQuery('#entry_form').submit();
		
	}
	
})