$(document).ready(function(){
  $("select[name=recipeURL]").change(function() {
    var recipe = $("select[name=recipeURL]").val();
    var recipeBasename = basename(recipe, '.xml');
    var wasSelected = '';
    var done = false;
    $('select[name=stylesheetURL] option').each(function() {
      var stylesheet = $(this).val();
      var stylesheetBasename = basename(stylesheet, '.xsl');
      
      if ($(this).attr('selected') == 'selected') {
        wasSelected = stylesheetBasename;
      }
      $(this).attr('selected', null);
      
      if (recipeBasename == stylesheetBasename) {
        $(this).attr('selected', 'selected');
        done = true;
      }
      if (done == false && stylesheetBasename == 'generic') {
        $(this).attr('selected', 'selected');
      }
    });
    if (!done && wasSelected != '') {
      $('select[name=stylesheetURL] option').each(function() {
        if (wasSelected == stylesheetBasename) {
          $(this).attr('selected', 'selected');
          wasSelected = '';
        }
      });
    }
  });
});

function basename (path, suffix) {
    // Returns the filename component of the path  
    // 
    // version: 1008.1718
    // discuss at: http://phpjs.org/functions/basename    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Ash Searle (http://hexmen.com/blog/)
    // +   improved by: Lincoln Ramsay
    // +   improved by: djmix
    // *     example 1: basename('/www/site/home.htm', '.htm');    // *     returns 1: 'home'
    // *     example 2: basename('ecra.php?p=1');
    // *     returns 2: 'ecra.php?p=1'
    var b = path.replace(/^.*[\/\\]/g, '');
        if (typeof(suffix) == 'string' && b.substr(b.length-suffix.length) == suffix) {
        b = b.substr(0, b.length-suffix.length);
    }
    
    return b;}
