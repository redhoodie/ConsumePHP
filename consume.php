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
  protected $requires = array();

  function __construct($recipeXML){
    //set recipe variables
    $this->name = (string) $recipeXML->name;
    $this->version = (string) $recipeXML->version;
    $this->curlKeepAlive = (bool) (0 + $recipeXML->keepCurlAlive);
    
    //Create ingredients
    foreach ($recipeXML->ingredients->ingredient as $ingredient) {
      $this->ingredients[] = new Ingredient($ingredient);
    }
    $requires = (array) $recipeXML->requires;
    if (count($requires) > 0) {
      foreach ($requires as $type => $array) {
        foreach ($array as $item) {
          $item = (array) $item;
          if (!array_key_exists($type, $this->requires) || !is_array($this->requires[$type])) {
            $this->requires[$type] = array();
          }
          $this->requires[$type][] = $item;
        }
      }
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
    $this->clean($this->variables, true);
  }
  
  public function getVariables() {
    return $this->variables;
  }
  
  //Genetates a marked up version of retrieved variables
  public function getResult() {
    $recipe = (array)$this;
    foreach($recipe as $key => $value) {
      $newkey = trim(str_replace('*', '', $key));
      $recipe[$newkey] = $value;
      unset($recipe[$key]);
    }
    
    //needs cleaning up
    $new_variables = array();
    $i = 0;
    foreach($recipe['variables'] as $name => $field) {
      if (count($field) !== 0) {
        $maxfields = 1;
        $new_field = array();
        
        //get max(count(fields))
        foreach($field as $name2 => $field2) {
          if (is_array($field2) && array_key_exists('#value', $field2) && count($field2['#value']) > $maxfields) {
            $maxfields = count($field2['#value']);
          }
        }
        
        //If we have multipul values for any subfield, split out the field
        if ($maxfields > 1) {
          //initalize new fields
          for($k = 0; $k < $maxfields; $k++) {
            $new_field[$k] = array();
          }
          
          //split each field
          foreach($field as $name2 => $field2) {
            if (is_array($field2)) {
              if (is_array($field2['#value'])) {
                foreach($field2['#value'] as $j => $value) {
                  $temp = $field2;
                  $temp['#value'] = $value;
                  $new_field[$j][$name2] = $temp;
                }
              }
              else {
                $new_field[0][$name2] = $field2;
              }
            }
          }
          $new_variables[$name] = $new_field;
        }
        else {
          $new_variables[$name] = $field;
        }
        $i++;
      }
    }
    $recipe['variables'] = $new_variables;
    
    unset($recipe['curlKeepAlive']);
    unset($recipe['ingredients']);
    unset($recipe['requires']);
    $recipe['updated'] = date('c');
    
    
    return ArrayToXML::toXML($recipe);
  }
  
  private function clean($variables, $final = false) {
    foreach ($variables as $name => $data) {
      //Remove #value attributes if there are no label attribute recursively
      if (!is_array($data)) {
        continue;
      }
      if ($data['hidden']) {
        if (array_key_exists('#value', $data)) {
          if (count($data) > 1) {
            unset($variables[$name]['#value']);
          }
          else {
            unset($variables[$name]);
            continue;
          }
        }
      }
      unset($variables[$name]['hidden']);
      $variables[$name] = $this->clean($variables[$name]);
    }
    if ($final) {
      $this->variables = $variables;
    }
    else {
      return $variables;
    }
  }
  
  public function setRequiredVariable($name, $value) {
     $this->variables[$name] = array('#value' => $value, 'hidden' => true);
  }
  
  public function getName() {
    return $this->name;
  }
  
  public function getRequires() {
    return $this->requires;
  }
}

define('CONSUME_INGREDIENT_TYPE_GET', 0);
define('CONSUME_INGREDIENT_TYPE_POST', 1);

define('CONSUME_INGREDIENT_REFERRER_NONE', 0);
define('CONSUME_INGREDIENT_REFERRER_FOLLOW', 1);
//define('CONSUME_INGREDIENT_REFERRER_URL', 'SomeUrl');

class Ingredient {
  protected $referrer = CONSUME_INGREDIENT_REFERRER_NONE;
  protected $curlOptions = array();
  protected $variables = array();
  protected $postFields = '';
  protected $curlHandler = null;
  protected $debug = false;
  protected $tidyOutput = false;
  
  function __construct($IngredientXML) {
    //set ingredient variables
    
    if (array_key_exists('debug', (array)$IngredientXML)) {
      $this->debug = true;
    }
    if (array_key_exists('tidy', (array)$IngredientXML)) {
      $this->tidyOutput = true;
    }    
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
    //try and devise whether or not variables is an array
    $temp = (array)$IngredientXML;
    if (array_key_exists('variables', $temp)) {
      $temp = (array)$temp['variables'];
      if ($temp['variable'] && array_key_exists(0, $temp['variable'])) {
        foreach ($IngredientXML->variables->variable as $variable) {
          $this->variables[] = new Variable($variable);
        }
      }
      elseif ($temp['variable']) {
        $data = $IngredientXML->variables->variable;
        $temp = @new Variable($data);
        $this->variables[] = $temp;
      }
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
      foreach($variables as $postFieldName => $postFieldVariable) {
        $this->postFields = str_replace('@'.$postFieldName, $postFieldVariable['#value'], $this->postFields);
        $curlOptions[CURLOPT_URL] = str_replace('@'.$postFieldName, $postFieldVariable['#value'], $curlOptions[CURLOPT_URL]);
      }
      $curlOptions[CURLOPT_POSTFIELDS] = $this->postFields;
    }
    
    foreach ($curlOptions as $option => $value) {
      curl_setopt($curlHandler, $option, $value);
    }
    
    $result = curl_exec($curlHandler);
    
    if ($this->tidyOutput) {
      $config = array(
        'indent' => false,
        'output-xhtml' => true,
        'show-body-only' => true,
        'wrap' => 0,
        'clean' => true,
      );
      $tidy = new tidy;
      $tidy->parseString($result, $config, 'utf8');
      $tidy->cleanRepair();
      $result = (string) $tidy;
    }
    
    $results = array();
    if ($result === false) {
      Consume::error('curl_exec failed (' . curl_errno($curlHandler) . ')', 'get', 'Ingredient', __LINE__);
    }
    else {
      foreach ($this->variables as $variable) {
        $results = array_merge($results, $variable->process($result));
        if ($this->debug) {
          print 'Ingredient variables:<pre>'.htmlentities(var_export($variable, true)).'</pre><hr/>';    
        }
      }
      $this->curlHandler = $curlHandler;
    }
    if ($this->debug) {
      print 'Ingredient settings:<pre>'.htmlentities(var_export($curlOptions, true)).'</pre><hr/>';    
      print 'Ingredient result:<pre>'.htmlentities(var_export($result, true)).'</pre><hr/>';    
      print 'Ingredient results:<pre>'.htmlentities(var_export($results, true)).'</pre><hr/>';    
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
  protected $label = "";
  protected $hidden = boolean;

  
  function __construct($VariableXML) {
    
    $this->name = (string) $VariableXML->name;
    $this->pattern = html_entity_decode((string) $VariableXML->pattern);
    
    if ($VariableXML->assertions) {
      foreach ($VariableXML->assertions->assertion as $assertion) {
        $this->assertions[] = (string)$assertion;
      }
    }
    
    if ($VariableXML->transformations){
      foreach ($VariableXML->transformations->transformation as $transformation) {
        $this->transformations[html_entity_decode((string) $transformation->search)] = html_entity_decode((string) $transformation->replace);
      }
    }
    
    if ($VariableXML->variables){
      foreach ($VariableXML->variables->variable as $variable) {
        $this->variables[] = new Variable($variable);
      }
    }
    
    $this->hidden = ($VariableXML->hidden)?1:0;
    if ($VariableXML->label){
       $this->label = (string)$VariableXML->label;
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
    $attributes = array('#value' => $parsed_value);
    
    if ($this->label) {
      $attributes['label'] = $this->label;
    }
    $attributes['hidden'] = $this->hidden;

    $value = array($this->name => $attributes);
    
    //recursively process each child-variable
    //This may need work too
    if (count($this->variables) > 0) {
      $values = array();
      foreach ($this->variables as $variable) {
        if (is_array($parsed_value)) {
          foreach($parsed_value as $parsed_valuex) {
            $values = array_merge($values, $variable->process($parsed_valuex));
          }
        }
        else {
          $values = array_merge($values, $variable->process($parsed_value));
        }
      }
      
      $value[$this->name] = array_merge($value[$this->name], $values);
    }
    return $value;
  }
  
  protected function parse($string) {
    $matches = array();
    //var_export($this->pattern);
    preg_match_all($this->pattern, $string, $matches, PREG_SET_ORDER);
    //This may need work
    //print 'match:<pre>'.htmlentities($this->pattern).'</pre>:<pre>'.htmlentities(var_export($matches, true)).'</pre>';
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


/*
  Class taken from http://snipplr.com/view/3491/convert-php-array-to-xml-or-simple-xml-object-if-you-wish/
*/

class ArrayToXML
{
    /**
     * The main function for converting to an XML document.
     * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
     *
     * @param array $data
     * @param string $rootNodeName - what you want the root node to be - defaultsto data.
     * @param SimpleXMLElement $xml - should only be used recursively
     * @return string XML
     */
    public static function toXML( $data, $rootNodeName = 'ResultSet', &$xml=null ) {
      // turn off compatibility mode as simple xml throws a wobbly if you don't.
      if ( ini_get('zend.ze1_compatibility_mode') == 1 ) ini_set ( 'zend.ze1_compatibility_mode', 0 );
      if ( is_null( $xml ) ) $xml = new SimpleXMLElement('<consume></consume>');

      // loop through the data passed in.
      foreach( $data as $key => $value ) {
          $numeric = 0;
          // no numeric keys in our xml please!
          if ( is_numeric( $key ) ) {
              $numeric = 1;
              $key = $rootNodeName;
          }

          // delete any char not allowed in XML element names
          $key = preg_replace('/[^a-z0-9\-\_\.\:]/i', '', $key);

          if( is_object( $value ) ) {
              $value = get_object_vars( $value );         
          }

          // if there is another array found recrusively call this function
          if ( is_array( $value ) ) {
              $node = ArrayToXML::is_assoc( $value ) || $numeric ? $xml->addChild( $key ) : $xml;

              // recrusive call.
              if ( $numeric ) $key = 'anon';
              ArrayToXML::toXml( $value, $key, $node );
          } else {

              // add single node.
              $value = htmlentities( $value );
              $xml->addChild( $key, $value );
          }
      }

      // pass back as XML
      //return $xml->asXML();

  // if you want the XML to be formatted, use the below instead to return the XML
      $doc = new DOMDocument('1.0');
      $doc->preserveWhiteSpace = false;
      $doc->loadXML( $xml->asXML() );
      $doc->formatOutput = true;
      return $doc->saveXML();
  }


  /**
   * Convert an XML document to a multi dimensional array
   * Pass in an XML document (or SimpleXMLElement object) and this recrusively loops through and builds a representative array
   *
   * @param string $xml - XML document - can optionally be a SimpleXMLElement object
   * @return array ARRAY
   */
  public static function toArray( $xml ) {
      if ( is_string( $xml ) ) $xml = new SimpleXMLElement( $xml );
      $children = $xml->children();
      if ( !$children ) return (string) $xml;
      $arr = array();
      foreach ( $children as $key => $node ) {
          $node = ArrayToXML::toArray( $node );

          // support for 'anon' non-associative arrays
          if ( $key == 'anon' ) $key = count( $arr );

          // if the node is already set, put it into an array
          if ( isset( $arr[$key] ) ) {
              if ( !is_array( $arr[$key] ) || $arr[$key][0] == null ) $arr[$key] = array( $arr[$key] );
              $arr[$key][] = $node;
          } else {
              $arr[$key] = $node;
          }
      }
      return $arr;
  }

  // determine if a variable is an associative array
  public static function is_assoc( $array ) {
      return (is_array($array) && 0 !== count(array_diff_key($array, array_keys(array_keys($array)))));
  }

}

