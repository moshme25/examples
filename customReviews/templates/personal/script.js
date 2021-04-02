var CReviewAreaPersonal = function(productId){
    this.productId = productId;
    this.setEvent();
}

CReviewAreaPersonal.prototype.setEvent = function() {
    $(document).on('click',"#review-form_"+this.productId+" .js-add-review__popup-submit", $.proxy(this.sendReviewForm,this));
}

CReviewAreaPersonal.prototype.sendReviewForm = function(e) {
    e.preventDefault();
    var self = $('#review-form_'+this.productId);
    var form = self.serializeArray();
    var data = {};
    
    form.forEach(function(item,key){
        data[item.name] = item.value;
    });
    data["id"] = self.data('product-id');

    BX.ajax.runComponentAction('demso:reviews',
    "sendReviewForm", { 
      mode: 'class',
      data:{
          "data" : data,
      }
    })
    .then(function(result) {
        if(result.data.STATUS) {
            location.reload();
        } else {
            $('#review-error').text('Что-то пошло не так');
            $('#review-error').css({"display":"block", "color":"red"});
        }
    });
}
