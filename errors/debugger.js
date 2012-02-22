$(document).ready(function() {
	$("#debugger").
		resizable({
			ghost: true,
			handles: 'n, e, s, w, se, sw, ne, nw',
			resize: function(event, ui) {
				
			}
		}).
		draggable().
		dblclick(function(){
			$(this).toggleClass('minimized');
		}
	);
});