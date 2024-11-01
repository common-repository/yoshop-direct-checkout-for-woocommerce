jQuery(document).ready(function(){
        //code to add validation on "Add to Cart" button
        jQuery('.single_add_to_fast_checkout').click(function(event){
        	event.preventDefault();
        	
        	var quantity = jQuery( "form.cart input[name='quantity']" ).val();
    		var variation = jQuery( "form.cart input[name='variation']" ).val();
    		var variationId = jQuery( "form.cart input[name='variation_id']" ).val();
    		
    		jQuery("form.quick_checkout_form input[name='quantity']" ).val(quantity);
    		jQuery("form.quick_checkout_form input[name='variation']" ).val(variation);
    		jQuery("form.quick_checkout_form input[name='variation_id']" ).val(variationId);
    		
    		var original_form = jQuery('table.variations');
    		var cloned_form = original_form.clone()
    		original_form.find('select').each(function(i) {
    		    cloned_form.find('select').eq(i).val(jQuery(this).val())
    		})
    		cloned_form.appendTo('.quick_checkout_form');
    		jQuery("form.quick_checkout_form table.variations").hide();
    		
    		jQuery( "form.quick_checkout_form" ).submit();
    		
    	});
        
        jQuery('.variations_button').each(function(){
        	jQuery('.single_add_to_cart_button').after(jQuery('.single_add_to_fast_checkout'));
        });
        
 });
