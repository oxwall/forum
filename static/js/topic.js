var ForumTopic = {
	construct: function()
	{
		var self = this;
		this.$add_post_input = $('#'+this.add_post_input_id);
		
		$(".sticky_topic").bind("click", function() {
			self.confirmAction(self.stickyTopicUrl, "Are you sure you want to stick this topic?");
		});
		
		$(".lock_topic").bind("click", function() {
			self.confirmAction(self.lockTopicUrl, "Are you sure you want to lock this topic?");
		});
		
		$(".delete_topic").bind("click", function() {
			self.confirmAction(self.deleteTopicUrl, "Are you sure you want to delete this topic?");
		});		
	
		$(".delete_post").bind("click", function() {
			var postId = $(this).attr("id");
			self.confirmAction(self.deletePostUrl, "Are you sure you want to delete the post?", postId);
		});
		
		$(".quote_post").bind("click", function() {
			var postId = $(this).attr("id");
			self.quotePost(postId);
		});
		
		$(".edit_post").bind("click", function() {
			var postId = $(this).attr("id");
			self.editPost(postId);
		});
		
		this.prepareEditPostForm();
	},
	
	confirmAction: function(url, confirmText, postId)
	{
		var result = window.confirm(confirmText);
		if ( result ) {
			url = (postId) ? url.replace('postId', postId) : url;
			window.location.href = url;
		}		
	},
	
	quotePost: function(postId)
	{
		var self = this;
		var url = this.getPostUrl.replace('postId', postId);
		this.ajaxCall(url, function(post) {
			var text = '<blockquote from="'+post.from+'">\n'+post.text+'\n</blockquote>';
			self.$add_post_input.val(text).focus();
		});		
	},
	
	editPost: function(postId)
	{
		var self = this;
		var url = this.getPostUrl.replace('postId', postId);
		this.ajaxCall(url, function(post) {
			if( post ) {
				self.editPostFormFields.$postId.val(postId);
				self.editPostFormFields.$text.val(post.text).focus();
			}
		});	
	},
	
	prepareEditPostForm: function()
	{
		this.editPostForm = window.owForms['edit-post-form'];
		this.editPostFormFields = {
				$postId: $(this.editPostForm.elements.postId.input),
				$text: $(this.editPostForm.elements.text.input)
		};		
		
		this.editPostForm.bindEvent('success', function(result){
			if (result) {
				window.location.reload();
			}
		});
	},
	
	ajaxCall: function(url, callback) 
	{
		var self = this;
		$.ajax({
				url: url,
				type: "get",
				dataType: "json",
				success: function(result){
					if ( callback != undefined ) {
						callback(result, self);	
					}
					new Function(result.script)();
				}
			});
	}
};