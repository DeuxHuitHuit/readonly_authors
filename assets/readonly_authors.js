/*
 * Copyrights: Deux Huit Huit 2019
 * LICENCE: MIT https://deuxhuithuit.mit-license.org
 */
(function ($) {
	
	var removeButtons = function (stack) {
		stack.find('.create.button, .delete.button, [data-create], [data-link], [data-unlink], [data-replace]').remove();
		stack.find('[type="submit"], [name="with-selected"]').attr('disabled', 'disabled').addClass('disabled');
	};
	
	var disableForms = function (stack) {
		stack.find('input, select, textarea, .CodeMirror, [contenteditable]').attr('readonly', 'readonly');
		stack.find('select').attr('disabled', 'disabled');
	};
	
	var init = function () {
		$('body').addClass('readonly');
		var stack = Symphony.Elements.contents.add(
			Symphony.Elements.context
		);
		removeButtons(stack);
		disableForms(Symphony.Elements.contents);
		setTimeout(function () {
			removeButtons(stack);
		}, 2000);
	};
	
	$(init);
	
})(jQuery);
