<?php

class Consume {
  
	/*
	Copyright (C) 2010 Redhoodie

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; version 2
	of the License.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
	http://www.gnu.org/licenses/gpl.txt
  
  KNOWN BUGS:
    
  
  VERSION:
    2.x
  */
  
  //Default settings
  public static $curlOptions = array(
    CURLOPT_COOKIEFILE => '/tmp/cookie',
    CURLOPT_COOKIEJAR => '/tmp/cookie',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.4) Gecko/20030624 Netscape/7.1 (ax)',
  );
  
  //Variables
  
  private static $errors = array();
  
  /*
    FUNCTION parseRecipeFromURL($url)
    
    Parses a recipe and returns a Recipe object from a url.
    The retrieved file is expected to be in the format of ExampleXMLRecipe
    
    ARG $url
      String : the path to retreve the Recipe from.
      
    RETURN
      Object<Recipe> : a Recipe object.
      FALSE : if the XML is invalid
  */
  public function parseRecipeFromURL($url) {
    $string = file_get_contents($url);
    $recipeXML = new SimpleXMLElement($string);
    $recipes = array();
    foreach ($recipeXML->recipe as $recipe){
      $recipes[] = new Recipe($recipe);
    }
    return $recipes;    
  }

  /*
    FUNCTION parseRecipeFromString($string)
    
    Parses a recipe and returns a Recipe object from a string.
    The string is expected to be in the format of ExampleXMLRecipe
    
    ARG $string
      String : a String containing a valid Recipe in XML.
      
    RETURN
      Object<Recipe> : a Recipe object.
      FALSE : if the XML is invalid
  */
  public function parseRecipeFromString($string) {
    $recipeXML = new SimpleXMLElement($string);
    $recipes = array();
    foreach ($recipeXML->recipe as $recipe){
      $recipes[] = new Recipe($recipe);
    }
    return $recipes;
  }
  
  public static function error($message, $function = '', $class = '', $line = -1) {
    self::$errors[] = array($message, $function, $class, $line);
  }
  
  public static function printErrors(){
    print '<ul class="errors">';
    foreach (self::$errors as $error) {
      print '<li>'.$error[2].'->'.$error[1].' ('.$error[3].'):<br/>'.$error[0].'</li>';
    }
    print '</ul>';
  }
}

class Recipe {
  protected $name = '';
  protected $version = '';
  protected $ingredients = array();
  protected $curlKeepAlive = false;
  protected $variables = array();

  function __construct($recipeXML){
    //print '<pre>'.htmlentities(var_export($recipeXML, true)).'</pre>';
    
    //set recipe variables
    $this->name = (string) $recipeXML->name;
    $this->version = (string) $recipeXML->version;
    $this->curlKeepAlive = (bool) (0 + $recipeXML->keepCurlAlive);
    
    //Create ingredients
    foreach ($recipeXML->ingredients->ingredient as $ingredient) {
      $this->ingredients[] = new Ingredient($ingredient);
    }
  }
  
  public function bake() {
    $curlOptions = Consume::$curlOptions;
    $curlHandler = null;

    foreach ($this->ingredients as $ingredient) {
      $newVars = $ingredient->get($curlOptions, $curlHandler, $this->variables);
      $this->variables = array_merge($this->variables, $newVars);
      if ($this->curlKeepAlive) {
         $curlHandler = $ingredient->getCurlHandler();
      }
    }
  }
  
  public function getVariables() {
    return $this->variables;
  }
  
  public function setVariable($name, $value) {
     $this->variables[$name] = $value;
  }
}

define('CONSUME_INGREDIENT_TYPE_GET', 0);
define('CONSUME_INGREDIENT_TYPE_POST', 1);

define('CONSUME_INGREDIENT_REFERRER_NONE', 0);
define('CONSUME_INGREDIENT_REFERRER_FOLLOW', 1);
//define('CONSUME_INGREDIENT_REFERRER_URL', 'SomeUrl');

define('CONSUME_INGREDIENT_TYPE_POST', 1);


class Ingredient {
  protected $referrer = CONSUME_INGREDIENT_REFERRER_NONE;
  protected $curlOptions = array();
  protected $variables = array();
  protected $postFields = '';
  protected $curlHandler = null;
  
  /*
  function __construct($url, $referrer, $curlOptions, $requestType, $variables, $postFields = '') {
    $this->curlOptions = $curlOptions;
    $this->variables = $variables;
    
    
    $this->curlOptions[CURLOPT_URL] = $url;
    if ($requestType == CONSUME_INGREDIENT_TYPE_POST) {
      $this->curlOptions[CURLOPT_POST] = true;
      if ($postFields != null) {
        $this->curlOptions[CURLOPT_POSTFIELDS] = $postFields;
        $this->postFields = $postFields;
      }
    }    
  }
  */
  
  function __construct($IngredientXML) {
    //set ingredient variables
    $this->referrer = (string) $IngredientXML->referrer;
    if (is_numeric($this->referrer)) {
      $this->referrer = (integer) $this->referrer;
    }
    $this->curlOptions[CURLOPT_URL] = (string) $IngredientXML->url;
    $requestType = (string)$IngredientXML->requestType;
    //Handle POST type ingredients
    if ($requestType == CONSUME_INGREDIENT_TYPE_POST) {
      $this->curlOptions[CURLOPT_POST] = true;
      if ($IngredientXML->postFields && strlen((string) $IngredientXML->postFields) != 0) {
        $this->curlOptions[CURLOPT_POSTFIELDS] = html_entity_decode((string) $IngredientXML->postFields);
        $this->postFields = html_entity_decode((string) $IngredientXML->postFields);
      }
    }    
    //set curlOptions
    foreach ($IngredientXML->curlOptions as $curlOption) {
      $curlOption = $curlOption->curlOption;
      //Lookup the option's (defined string) numerical value using constant()
      $curlOptionValue = @constant((string) $curlOption->option);
      if ($curlOptionValue != null) {
        if (is_numeric((string)$curlOption->value)) {
          $this->curlOptions[$curlOptionValue] = (0 + $curlOption->value);
        }
        else {
          $this->curlOptions[$curlOptionValue] = (string) $curlOption->value;
        }
      }
      else {
        Consume::error('Invalid curlOption: '.$curlOption->option, 'Ingredient()', 'Ingredient', $line = __LINE__);
      }
    }
    
    //Create Variables
    foreach ($IngredientXML->variables as $variable) {
      $this->variables[] = new Variable($variable->variable);
    }
    
  }
  
  public function get($curlOptions = array(), $curlHandler = null, $variables = array()) {
    //$this->curlOptions take precedence over parsed $curlOptions
    
    //setup $curlHandler
    if ($curlHandler == null) {
      $curlHandler = curl_init();
    }
    else {
      /* if referrer = CONSUME_INGREDIENT_REFERRER_FOLLOW then
          extract referrer from parsed curlHandler
      */
      if ($this->referrer == CONSUME_INGREDIENT_REFERRER_FOLLOW) {
        //echo 'current path:' . curl_getinfo($curlHandler, CURLINFO_EFFECTIVE_URL);
        $this->curlOptions[CURLOPT_REFERER] = curl_getinfo($curlHandler, CURLINFO_EFFECTIVE_URL);
      }
    }
    //echo $this->curlOptions[CURLOPT_REFERER];
    
    //Setup Curl Options
    if (is_array($curlOptions)) {
      $curlOptions = $curlOptions + $this->curlOptions;
    }
    else {
      $curlOptions = $this->curlOptions;
    }
    
    if (count($variables) > 0) {
      foreach($variables as $postFieldVariableName => $postFieldVariableValue) {
        $this->postFields = str_replace('@'.$postFieldVariableName, $postFieldVariableValue, $this->postFields);
        $curlOptions[CURLOPT_URL] = str_replace('@'.$postFieldVariableName, $postFieldVariableValue, $curlOptions[CURLOPT_URL]);
      }
      $curlOptions[CURLOPT_POSTFIELDS] = $this->postFields;
    }
    
    foreach ($curlOptions as $option => $value) {
      curl_setopt($curlHandler, $option, $value);
    }
    
    $result = curl_exec($curlHandler);
    $results = array();
    if ($result === false) {
      Consume::error('curl_exec failed (' . curl_errno($curlHandler) . ')', 'get', 'Ingredient', __LINE__);
    }
    else {
      foreach ($this->variables as $variable) {
         $results = array_merge($results, $variable->process($result));
      }
      $this->curlHandler = $curlHandler;
    }
    
    return $results;
  }
  
  public function getCurlHandler() {
    return $this->curlHandler;
  }
}

class Variable {
  protected $assertions = array();
  protected $transformations = array();
  protected $name = '';
  protected $value = null;
  protected $pattern = '';
  protected $variables = array();

  
  function __construct($VariableXML) {
    //print '<pre>'.htmlentities(var_export($VariableXML, true)).'</pre>';
    
    $this->name = (string) $VariableXML->name;
    $this->pattern = html_entity_decode((string) $VariableXML->pattern);
    
    if ($VariableXML->assertions) {
      foreach ($VariableXML->assertions->assertion as $assertion) {
        $this->assertions[] = (string)$assertion;
      }
    }
    
    if ($VariableXML->transformations){
      foreach ($VariableXML->transformations->transformation as $transformation) {
        $this->transformations[(string) $transformation->search] = (string) $transformation->replace;
      }
    }
    
    if ($VariableXML->variables){
      foreach ($VariableXML->variables->variable as $variable) {
        $this->variables[] = new Variable($variable);
      }
    }
  }
  
  protected function setValue($newValue) {
    //Check that newValue matches each assertion
    $failedAssertions = false;
    foreach ($this->assertions as $assertion) {
      if (preg_match($assertion, $newValue) <= 0) {
         $failedAssertions = true;
         Consume::error('Failed Assertion: ' . $assertion, 'setValue', 'Variable', __LINE__);
         return;
      }
    }
    
    //Run any transformations
    foreach ($this->transformations as $pattern => $replacement) {
      $newValue = preg_replace($pattern, $replacement, $newValue);
    }
    
    if (is_array($this->value)) {
      $this->value[] = $newValue;
    }
    else if($this->value === null) {
      $this->value = $newValue;
    }
    else {
      $this->value = array($this->value, $newValue);
    }
  }
  
  public function process($string) {
    $parsed_value = $this->parse($string);
    $value = array($this->name => $parsed_value);
    
    //recursively process each child-variable
    //This may need work too
    if (count($this->variables) > 0) {
      $values = array();
      foreach ($this->variables as $variable) {
        $values = array_merge($values, $variable->process($parsed_value));
      }
      $value = array_merge($value, $values);
    }
    return $value;
  }
  
  protected function parse($string) {
    $matches = array();
    preg_match_all($this->pattern, $string, $matches, PREG_SET_ORDER);
    //This may need work
    //print 'match:<pre>'.htmlentities($this->pattern).'</pre>:<pre>'.htmlentities(var_export($matches, true)).'</pre>:<pre>'.htmlentities(var_export($string, true)).'</pre>';
    foreach ($matches as $value) {
      if (array_key_exists(1, $value)) {
        $this->setValue($value[1]);
      }
      else {
        $this->setValue($value[0]);
      }
    }
    //print 'match:<pre>'.htmlentities(var_export($this->value, true)).'</pre>';    
    return $this->value;
  }
  
  public function getName() {
    return $this->name;
  }  
}
