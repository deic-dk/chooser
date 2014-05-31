// jQuery File Tree Plugin
//
// Version 1.02
//
// Cory S.N. LaViska
// A Beautiful Site (http://abeautifulsite.net/)
// 24 March 2008
//
// Visit http://abeautifulsite.net/notebook.php?article=58 for more information
//
// Usage: $('.fileTreeDemo').fileTree( options, callback )
//
// Options:  root           - root folder to display; default = '/'
//           folder         - top-level folder to expand on load; default = ''
//           script         - location of the serverside AJAX file to use; default = jqueryFileTree.php
//           folderEvent    - event to trigger expand/collapse; default = click
//           expandSpeed    - default = 500 (ms); use -1 for no animation
//           collapseSpeed  - default = 500 (ms); use -1 for no animation
//           expandEasing   - easing function to use on expand (optional)
//           collapseEasing - easing function to use on collapse (optional)
//           multiFolder    - whether or not to limit the browser to one subfolder at a time
//           selectFile     - whether or not to highlight a clicked file
//           selectFolder   - whether or not to highlight a clicked folder
//           loadMessage    - Message to display while initial tree loads (can be HTML)
//
// History:
//
// 1.03 - added expansion of a top-level folder on load. Frederik Orellana, October 2013
// 1.02 - added boldfacing clicked items and support for double-click function. Frederik Orellana, October 2013
// 1.01 - updated to work with foreign characters in directory/file names (12 April 2008)
// 1.00 - released (24 March 2008)
//
// TERMS OF USE
// 
// This plugin is dual-licensed under the GNU General Public License and the MIT License and
// is copyright 2008 A Beautiful Site, LLC. 
//
if(jQuery) (function($){
	
	$.extend($.fn, {
		fileTree: function(o, h, hh) {
			// Defaults
			if( !o ) var o = {};
			if( o.root == undefined ) o.root = '/';
			if( o.folder == undefined ) o.folder = '';
			if( o.file == undefined ) o.file = '';
			if( o.script == undefined ) o.script = 'jqueryFileTree.php';
			if( o.folderEvent == undefined ) o.folderEvent = 'click';
			if( o.expandSpeed == undefined ) o.expandSpeed= 500;
			if( o.collapseSpeed == undefined ) o.collapseSpeed= 500;
			if( o.expandEasing == undefined ) o.expandEasing = null;
			if( o.collapseEasing == undefined ) o.collapseEasing = null;
			if( o.multiFolder == undefined ) o.multiFolder = true;
			if( o.selectFile == undefined ) o.selectFile = false;
			if( o.selectFolder == undefined ) o.selectFolder = false;
			if( o.loadMessage == undefined ) o.loadMessage = 'Loading...';

			hh = hh || function(file) {
				//alert("doubleclick");
			};

			var old = null;

			function treeAction(t){
				if( t.parent().hasClass('directory') ) {
					if( t.parent().hasClass('collapsed') ) {
						// Expand
						if( !o.multiFolder ) {
							t.parent().parent().find('UL').slideUp({ duration: o.collapseSpeed, easing: o.collapseEasing });
							t.parent().parent().find('LI.directory').removeClass('expanded').addClass('collapsed');
						}
						t.parent().find('UL').remove(); // cleanup
						showTree( t.parent(), escape(t.attr('rel').match( /.*\// )) );
						t.parent().removeClass('collapsed').addClass('expanded');
					} else {
						// Collapse
						t.parent().find('UL').slideUp({ duration: o.collapseSpeed, easing: o.collapseEasing });
						t.parent().removeClass('expanded').addClass('collapsed');
					}
				}

				if( t.parent().hasClass('directory') && o.selectFolder==true ||
					!t.parent().hasClass('directory') && o.selectFile==true ) {
					if(old!=null){
						old.css("font-weight", "normal");
					}
					old = t;
					t.css("font-weight", "bold");
					h(t.attr('rel'));
				}

				return false;
			}

			function showTree(c, t) {
				$(c).addClass('wait');
				$(".jqueryFileTree.start").remove();
				$.post(o.script, { dir: t }, function(data) {
					$(c).find('.start').html('');
					$(c).removeClass('wait').append(data);
					if( o.root == t ) $(c).find('UL:hidden').show(); else $(c).find('UL:hidden').slideDown({ duration: o.expandSpeed, easing: o.expandEasing });
					bindTree(c);
				});
			}

			function bindTree(t) {
				// Bind single-click
				$(t).find('LI A').bind(o.folderEvent, function() {
					treeAction($(this));
				});

				// Prevent A from triggering the # on non-click events
				if( o.folderEvent.toLowerCase != 'click' ) $(t).find('LI A').bind('click', function() { return false; });

				// Bind double-click
				$(t).find('LI A').bind('dblclick', function() {
					hh($(this).attr('rel'));
				});

				var foundFolder = false;
				// Expand o.folder
				$(t).find('LI A').each(function() {
					if(!foundFolder && o.folder != '' && $(this).attr('rel') === o.folder+'/') {
						treeAction($(this));
						foundFolder = true;
						// Scoll to folder
						if(o.file==''){
							$('#dialog0').animate({
							   scrollTop: $(this).offset().top - $('#dialog0').offset().top
							}, 2000);
						}
					}
					else if(o.file!='' && $(this).attr('rel') === o.file){
						$('#dialog0').animate({
						   scrollTop: $(this).offset().top - $('#dialog0').offset().top
						}, 2000);
					}
				});
				// Highlight o.file
				if(o.file!=''){
					$('a[rel="'+o.file+'"]').parent().css("font-weight", "bold");
					treeAction($('a[rel="'+o.file+'"]').parent());
				}
			}

			$(this).each( function(index) {		
				// Loading message
				$(this).html('<ul class="jqueryFileTree start"><li class="wait">' + o.loadMessage + '<li></ul>');
				// Get the initial file list
				showTree( $(this), escape(o.root) );
			});

		}
	});
	
})(jQuery);