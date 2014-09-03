(function($) {

	jQuery(document).ready(function($) {
		
		//Disable the formidable form submit button
		$(".frm-show-form input[type='submit']").prop("disabled",true);
		
		//Listen for when user scrolls down the entire tos field
		//Then enable the submit button
		$(".frm-show-form textarea.frm_tos").scroll(function(){
			if($(this).scrollTop()+$(this).height() >= $(this)[0].scrollHeight-10){
				$(".frm-show-form input[type='submit']").prop("disabled",false);
			}
		});
		
	});

})(jQuery);