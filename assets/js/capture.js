jQuery( function( $ ) {

	$(document).ready(function () {
		
		$('#cepay_capture_charge_btn').click(function(e){
			e.preventDefault();       

        // get the order_id from the button tag
        var order_id = $(this).data('order_id');


        // send the data via ajax to the sever
        $.ajax({
            type: 'POST',
            url: ajax_object.ajaxurl,
            dataType: 'json',
            data: {
                action: 'cepay_capture_charge',
                order_id: order_id            
				},
            success: function (data, textStatus, XMLHttpRequest) {
				console.log(data);
                if(data.error == 0)
                { 
                  // trigger the "Recalculate" button to recalculate the order price 
                  // and to show the new product in the item list
                  $('.calculate-action').trigger('click'); 
                }

                // show the control message
                alert(data.msg);
				window.location = window.location.href;


            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
								console.log(textStatus);
								console.log(errorThrown);

                alert(errorThrown);
            }
        });

		});

});
});