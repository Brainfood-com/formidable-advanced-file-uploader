(function($) {
  
  $(document).ready(function(){
	  
	  //Pluing's js object storing any vars from php
	  //console.log( prso_frm_api_upload_vars );
	  
	  //Cache current form's id
	  var formId = prso_frm_api_upload_vars.frm_form_id;
	  
	  //Intercept form submit
	  $("form#" + formId).submit(function(e){
		  
		  //Hide submit button
		  $("form#" + formId + " input[type=submit]").hide();
		  
		  //Show ajax loading image
		  $(".frm-api-uploader").show();
		  
		  //Return true to allow submit to continue
		  return true;		  
	  });
	  
  });
  
})(jQuery);