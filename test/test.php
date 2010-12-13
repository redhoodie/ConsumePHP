<?php

  if (array_key_exists('recipeURL', $_POST)) {
    require_once('../consume.php');
    
    $consume = new Consume();
    $gotAllRequirements = array();
    $recipes = $consume->parseRecipeFromURL($_POST['recipeURL']);
    foreach ($recipes as $i => $recipe) {
      $requires = $recipe->getRequires();
      $good = true;
      foreach ($requires as $type => $fields) {
        foreach ($fields as $field) {
          if (!array_key_exists('field'.$field['name'], $_POST)) {
            $good = false;
          }
        }
      }
      $gotAllRequirements[$i] = $good;
    }
  }
  //generate a list of available recipies
  $basePath = pathinfo($_SERVER['REQUEST_URI']);
  $basePath = ($_SERVER['HTTPS']?'https://':'http://') . $_SERVER['HTTP_HOST'] . $basePath['dirname'];
  $recipies = array();
  $stylesheets = array();
  if ($handle = opendir('../recipes/')) {
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            $info = pathinfo("../recipes/$file");
            switch($info['extension']) {
              case 'xml':
                $recipies[$info['basename']] = $basePath . '/../recipes/'.$file;
                break;
              case 'xsl':
                $stylesheets[$info['basename']] = $basePath . '/../recipes/'.$file;
                break;
            }
        }
    }
    closedir($handle);
  }
  
  $optionsRecipies = '';
  foreach ($recipies as $name => $path) {
    $optionsRecipies .= '<option value="'.$path.'">'.$name.'</option>';
  }
  
  $optionsStylesheets = '';
  foreach ($stylesheets as $name => $path) {
    if ($name == 'generic.xsl') {
      $selected = ' selected="selected"';
    }
    $optionsStylesheets .= '<option value="'.$path.'"'.$selected.'>'.$name.'</option>';;
  }
  
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset=utf-8 />
  <title>ConsumePHP Test</title>
  <link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<section id="wrapper">
  <header>
    <h1>ConsumePHP Test</h1>
    <form action="" method="post">
      <label for="recipeURL">Recipe</label>
      <select name="recipeURL">
        <?php print $optionsRecipies ?>
      </select>
      <label for="stylesheetURL">Stylesheet</label>
      <select name="stylesheetURL">
        <?php print $optionsStylesheets ?>
      </select>
      <input type="submit" value="Consume!" />
    </form>
  </header>
  <?php if (array_key_exists('recipeURL', $_POST)): ?>
  <article>
    <?php
      foreach($recipes as $i => $recipe) {
      ?>
    <section>
      <h2>Consuming <?php print $recipe->getName(); ?>...</h2>
      <?php
        $requires = $recipe->getRequires();
        if (count($requires) > 0 && (!array_key_exists($i, $gotAllRequirements) || $gotAllRequirements[$i] == false)) {
          $recipeURL = $_POST['recipeURL'];
          $stylesheetURL = $_POST['stylesheetURL'];
          print <<< EOF
      <p>Woah, This recipe needs some stuff:</p>
      <form action="" method="post">
        <input type="hidden" name="recipeURL" value="$recipeURL" />
        <input type="hidden" name="stylesheetURL" value="$stylesheetURL" />
EOF;
          foreach ($requires as $type => $fields) {
            foreach ($fields as $field) {
              print '<label for="'.$field['name'].'" />'.ucfirst($field['name']).':</label> <input type="'.$field['type'].'" id="'.$field['name'].'" name="field'.$field['name'].'" /><br />';
            }
          }
          print <<< EOF
        <input type="submit" value="Consume!" />
      </form>
EOF;
        }
        else {
          foreach ($_POST as $name => $value) {
            if (substr($name, 0, 5) == 'field') {
              $recipe->setRequiredVariable(substr($name, 5), $value);
            }
          }
          $recipe->bake();
          $resultXML = $recipe->getResult();
          print '<pre>' . htmlentities(var_export($resultXML, true)). '</pre>';          
          if (array_key_exists('stylesheetURL', $_POST) && $_POST['stylesheetURL']) {
            $xslDoc = new DOMDocument();
            $xslDoc->load($_POST['stylesheetURL']);

            $xmlDoc = DOMDocument::loadXML($resultXML);
            
            $proc = new XSLTProcessor();
            $proc->importStylesheet($xslDoc);
            $proc->registerPHPFunctions();
            $resultXML = $proc->transformToXML($xmlDoc);
          }
          print $resultXML;
        }
      ?>
    </section>
      <?php
        }
      Consume::printErrors();
    ?>
  </article>
  <?php endif; ?>
</section>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.3/jquery.min.js"></script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.5/jquery-ui.min.js"></script>
<script type="text/javascript" src="script.js"></script>
</body>
</html>