
$(document).ready(function(){

  $("li").click(function(){
      $(this).css("font-weight", "bold");
  });

  $('#loadFolderTree').fileTree({
      //root: '/',
      script: 'https://data.deic.dk/apps/chooser/jqueryFileTree.php',
      multiFolder: true,
      selectFile: true,
      folder: 'Download',
      file: $('#chosen_file').text()
  }, function(file) {
    $('#chosen_file').text(file);
  }, function(file) {
      if(file.indexOf("/", file.length-1)==-1){
        read_list_file();
        $("#dialog0").dialog("close");
      }
    });
});