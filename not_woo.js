// plugin admin script

jQuery(function($) {
	const fields = [
		'shop_active',
		'shop_image',
		'paypal_address',
		'theme_css',
		'theme_js'
	];
	var _plugin = not_woo;
	$.ajax({
		method: 'GET',
		url: _plugin.api.url,
		beforeSend: function(xhr) {
			xhr.setRequestHeader('X-WP-Nonce', _plugin.api.nonce);
		}
	})
	.then(function(r) {
		fields.forEach(function(item, index) {
			if (r.hasOwnProperty(item)) {
				$('#' + item).val(r[item]);
				if (r[item][0] == '#') {
					$('#' + item).parent().find('[data-id="' + item + '"]').val(r[item]);
				}
			}
		});
	});
	var enabled = true;
	$('textarea.tabs').keydown(function(e) {
		if (e.keyCode==27) {
			enabled = !enabled;
			return false;
		}
		if (e.keyCode === 13 && enabled) {
			if (this.selectionStart == this.selectionEnd) {
				var sel = this.selectionStart;
				var text = $(this).val();
				while (sel > 0 && text[sel-1] != '\n')
				sel--;
				var lineStart = sel;
				while (text[sel] == ' ' || text[sel]=='\t')
				sel++;
				if (sel > lineStart) {
					document.execCommand('insertText', false, "\n" + text.substr(lineStart, sel-lineStart));
					this.blur();
					this.focus();
					return false;
				}
			}
		}
		if (e.keyCode === 9 && enabled) {
			if (this.selectionStart == this.selectionEnd) {
				if (!e.shiftKey) {
					document.execCommand('insertText', false, "\t");
				}
				else {
					var text = this.value;
					if (this.selectionStart > 0 && text[this.selectionStart-1]=='\t') {
						document.execCommand('delete');
					}
				}
			}
			else {
				var selStart = this.selectionStart;
				var selEnd = this.selectionEnd;
				var text = $(this).val();
				while (selStart > 0 && text[selStart-1] != '\n')
					selStart--;
				while (selEnd > 0 && text[selEnd-1]!='\n' && selEnd < text.length)
					selEnd++;
				var lines = text.substr(selStart, selEnd - selStart).split('\n');
				for (var i=0; i<lines.length; i++) {
					if (i==lines.length-1 && lines[i].length==0)
						continue;
					if (e.shiftKey) {
						if (lines[i].startsWith('\t'))
							lines[i] = lines[i].substr(1);
						else if (lines[i].startsWith("    "))
							lines[i] = lines[i].substr(4);
					}
					else
						lines[i] = "\t" + lines[i];
				}
				lines = lines.join('\n');
				this.value = text.substr(0, selStart) + lines + text.substr(selEnd);
				this.selectionStart = selStart;
				this.selectionEnd = selStart + lines.length; 
			}
			return false;
		}
		enabled = true;
		return true;
	});
	$('#not_woo-form').on('submit', function(e) {
		e.preventDefault();
		$('#submit').text('...').attr('disabled', 'disabled');
		var data = {};
		fields.forEach(function(item, index) {
			data[item] = $('#' + item).val();
		});
		$.ajax({
			method: 'POST',
			url: _plugin.api.url,
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', _plugin.api.nonce);
			},
			data: data
		})
		.then(function(r) {
			$('#feedback').html('<p>' + _plugin.strings.saved + '</p>');
			$('#submit').removeAttr('disabled');
			fields.forEach(function(item, index) {
				if (r.hasOwnProperty(item)) {
					$('#' + item).val(r[item]);
				}
			});
		})
		.fail(function(r) {
			var message = _plugin.strings.error;
			if (r.hasOwnProperty('message')) {
				message = r.message;
			}
			$('#submit').removeAttr('disabled');
			$('#feedback').html('<p>' + message + '</p>');
		});
	});
	var mediaUploader, id;
	$('.choose-file-button').on('click', function(e) {
		id = '#' + $(this).data('id');
		e.preventDefault();
		if (mediaUploader) {
			mediaUploader.open();
			return;
		}
		mediaUploader = wp.media.frames.file_frame = wp.media({
			title: 'Choose File',
			button: {
				text: 'Choose File'
			}, multiple: false
		});
		wp.media.frame.on('open', function() {
			if (wp.media.frame.content.get() !== null) {          
				wp.media.frame.content.get().collection._requery(true);
				wp.media.frame.content.get().options.selection.reset();
			}
		}, this);
		mediaUploader.on('select', function() {
			var attachment = mediaUploader.state().get('selection').first().toJSON();
			$(id).val(attachment.url.split('/').pop());
		});
		mediaUploader.open();
	});
	$('.choose-colour-button').on('change', function() {
		id = '#' + $(this).data('id');
		$(id).val($(this).val());
	});
});

// EOF