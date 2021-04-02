var CLikeArea = function(){
    this.setEvent();
}

CLikeArea.prototype.setEvent = function() {
    $(document).on('click',".js-like",$.proxy(this.doLike,this));
    $(document).on('click',".js-show-more-button",$.proxy(this.showMore,this));
}

CLikeArea.prototype.doLike = function(e) {
    e.preventDefault();

    var self = $(e.target);
    var like = self.data('like');
  
    BX.ajax.runComponentAction('demso:reviews',
    "addLike", { 
      mode: 'class',
      data:{
        data:{
            "like" : like,
            "review" : self.data('review')
        },
      }
    })
    .then(function(result) {
        if(result.data.STATUS) {
            var count = self.siblings('.productpage-good-votes').text();

            if (like == 4) {
                self.siblings('.productpage-good-votes').text(count+1);
            } else {
                count = self.siblings('.productpage-bad-votes').text();
                self.siblings('.productpage-bad-votes').text(count+1);
            }
           
        }
    });
}

CLikeArea.prototype.showMore = function(e) {
    e.preventDefault();
    var self = $(e.target);
  
    BX.ajax.runComponentAction('demso:reviews',
    "showMore", { 
      mode: 'class',
      data:{
        data:{
            "id" : self.data('show')
        },
      }
    })
    .then(function(result) {
        if(result.data.STATUS) {    
            $(".productpage-reviews").find('.slider-title').text(result.data.COUNT + ' отзывов');

            $('.productpage-reviews__item').remove();

            $('#first-column').append(result.data.HTML_FIRST);
            $('#second-column').append(result.data.HTML_SECOND);

            self.remove();
        }
    });
}
