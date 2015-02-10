var ForumCustomize = {
	request: [],
	
	construct: function()
	{
		var self = this;
		
		this.setSectionSortable();
		
		this.setGroupSortable();
		
		this.prepareAddForum();
		this.prepareEditSection();
		this.prepareEditGroup();
		
		$('#add_forum_btn').bind('click', function(){
			$('#add_forum_form').show();
		});
		
		$('.forum_section').each(function(){
			var $node = $(this);
			var sectionId = $node.attr('id').substr(8);
			
			$node.find('.section_delete').bind('click', function(){
				self.deleteSection($node, sectionId);
			});
			$node.find('.section_edit').bind('click', function(){
				self.editSection(sectionId);
			});
		});
		
		$('.forum_group').each(function(){
			var $node = $(this);
			var groupId = $node.attr('id').substr(6);
			
			$node.find('.group_delete').bind('click', function(){
				self.deleteGroup($node, groupId);
			});
			$node.find('.group_edit').bind('click', function(){
				self.editGroup(groupId);
			});
		});
				
	},
	
	setSectionSortable: function()
	{
		var self = this;
		
		$('.forum_sections').sortable({
			items: '.forum_section',
			handle: '.forum_section_tr',
			cursor: 'crosshair',
			helper: 'clone',
			placeholder: 'forum_placeholder',
			forcePlaceholderSize: true,
			start: function(event, ui){
				$(ui.placeholder).append('<tr><td colspan="4"></td></tr>');
			},
			update: function(){
				var section = $('.forum_sections').sortable('serialize');
				self.ajaxCall(self.sortSectionOrderUrl, {data: section});
			}
		});		
	},
	
	setGroupSortable: function()
	{
		var self = this;
		
		$('.forum_section').sortable({
			items: '.forum_group',
			cancel: '.no_forum_group',
			cursor: 'crosshair',
			helper: 'clone',
			placeholder: 'forum_placeholder',
			forcePlaceholderSize: true,
			connectWith: '.forum_section',
			stop: function(event, ui){
				if ( self.request.length ) {
					self.ajaxCall(self.sortGroupOrderUrl, {data: JSON.stringify( self.request )});
				}
				self.request = [];
				
				var cycle = $(ui.item).hasClass('ow_even');
				
				$('.forum_group:visible').each(function(index, group){ 
					$(group).removeClass('ow_even');
					
					if ( index%2 ) {
						$(group).addClass('ow_even');
					}
				});
				
				if (cycle!=$(ui.item).hasClass('ow_even')) {
					$('.forum_group').toggleClass('ow_even');
				}
			},
			start: function(event, ui){
				$(ui.placeholder).append('<td colspan="4"></td>');
			},			
			update: function(e, ui){
				if (!ui.sender) {
					self.request = [];

					self.request.push({
						sectionId: $(this).attr('id').substr(8),
						order: $(this).sortable('serialize')
					});
				}
				else {
					self.request = [];
					self.request.push({
						sectionId: $(this).attr('id').substr(8),
						order: $(this).sortable('serialize')
					});
					
					self.request.push({
						sectionId: $(ui.sender).attr('id').substr(8),
						order: $(ui.sender).sortable('serialize')
					});
				}
			},
			receive: function(event, ui){
				var sender_length = $(ui.sender).find('tr').length;

				if ( sender_length==2 ) {
					$(ui.sender).find('.no_forum_group').show();
				}
				else {
					$(ui.item).parent().find('.no_forum_group').hide();
				}
				var receiver_length = $(this).find('tr').length;

				if ( receiver_length==2 ) {
					$(this).find('.no_forum_group').show();
				}
				else {
					$(this).find('.no_forum_group').hide();
				}
			}
		});		
	},
	
	deleteSection: function($node, sectionId)
	{
		var self = this;
		var result = window.confirm("Are you sure you want delete forum section?");
		
		if ( !result ) {
			return false;
		}
		
		self.ajaxCall(this.deleteSectionUrl, {sectionId:sectionId}, function(data){
			if ( data ) {
				$node.remove();
			}
		});
	},
	
	editSection: function(sectionId)
	{
		var self = this;
		this.ajaxCall(this.getSectionUrl, {sectionId:sectionId}, function(section){
			if ( section ) {
				self.editSectionFormFields.$sectionName.val(section.name).focus();
				self.editSectionFormFields.$sectionId.val(section.id);
			}
		});		
	},
	
	deleteGroup: function($node, groupId)
	{
		var result = window.confirm("Are you sure you want delete forum group?");
		
		if ( !result ) {
			return false;
		}
		
		this.ajaxCall(this.deleteGroupUrl, {groupId:groupId}, function(data){
			if ( data ) {
				$node.remove();
			}
		});
	},
	
	editGroup: function(groupId)
	{
		var self = this;
		this.ajaxCall(this.getGroupUrl, {groupId:groupId}, function(group){
			if ( group ) {
				self.editGroupFormFields.$groupId.val(group.id);
				self.editGroupFormFields.$groupName.val(group.name).focus();
				self.editGroupFormFields.$description.val(group.description);
			}
		});	
	},
	
	prepareAddForum: function()
	{
		this.addGroupForm = window.owForms['add-forum-form'];
		
		this.addGroupFormFields = {
			$section: $(this.addGroupForm.elements.section.input),
			$sectionId: $(this.addGroupForm.elements.sectionId.input)
		};
		
		this.addGroupFormFields.$section.autocomplete(this.suggestSectionUrl, {
			minChars:0, 
			matchSubset:1, 
			matchContains:1, 
			cacheLength:0, 
			selectOnly:1, 
			onItemSelect: this.selectSection
		});
		
		this.addGroupFormFields.$section.one("focus", function(){
			$input = $(this); 
			$input.val(' ').keydown();
			window.setTimeout(function(){
				$input.val('');
			}, 401);
		});
		
		this.addGroupForm.bindEvent('success', function(result){
			if (result) {
				window.location.reload();
			}
		});		
	},
	
	prepareEditSection: function()
	{
		this.editSectionForm = window.owForms['edit-section-form'];
		this.editSectionFormFields = {
				$sectionId: $(this.editSectionForm.elements.sectionId.input),
				$sectionName: $(this.editSectionForm.elements.sectionName.input)
		};		
		
		this.editSectionForm.bindEvent('success', function(result){
			if (result) {
				window.location.reload();
			}
		});		
	},
	
	prepareEditGroup: function()
	{
		this.editGroupForm = window.owForms['edit-group-form'];
		this.editGroupFormFields = {
				$groupId: $(this.editGroupForm.elements.groupId.input),
				$groupName: $(this.editGroupForm.elements.groupName.input),
				$description: $(this.editGroupForm.elements.description.input)
		};		
		
		this.editGroupForm.bindEvent('success', function(result){
			if (result) {
				window.location.reload();
			}
		});		
	},	
	
	selectSection: function(sectionName)
	{	
		ForumCustomize.addGroupFormFields.$section.val( $(sectionName).text() ).focus();
		ForumCustomize.addGroupFormFields.$sectionId.val(sectionName.extra[0]);
	},
	
	ajaxCall: function(url, params, callback) 
	{
		var self = this;
		$.ajax({
				url: url,
				type: 'post',
				dataType: 'json',
				data: params,
				success: function(result){
					if ( callback != undefined ) {
						callback(result, self);	
					}
					if ( result != undefined )
					{
						new Function(result.script)();
					}
				}
			});
	}	
};