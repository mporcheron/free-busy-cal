<?php
/**
 * A Class for handling vCalendar & vCard data.
 *
 * When parsed the underlying structure is roughly as follows:
 *
 *   vComponent( array(vComponent), array(vProperty) )
 *
 * @package awl
 * @subpackage vComponent
 * @author Milan Medlik <milan@morphoss.com>
 * @copyright Morphoss Ltd <http://www.morphoss.com/>
 * @license   http://gnu.org/copyleft/lgpl.html GNU LGPL v2 or later
 *
 */

    include_once('vObject.php');
    //include_once('HeapLines.php');
    include_once('vProperty.php');

    class vComponent extends vObject{

        private $components;
        private $properties;
        private $type;
        private $iterator;
        private $seekBegin;
        private $seekEnd;
        private $propertyLocation;

        const KEYBEGIN = 'BEGIN:';
        const KEYBEGINLENGTH = 6;
        const KEYEND = "END:";
        const KEYENDLENGTH = 4;
        const VEOL = "\r\n";

        public static $PREPARSED = false;

        function __construct($propstring=null, &$refData=null){
            parent::__construct($master);

            unset($this->type);

            if(isset($propstring) && gettype($propstring) == 'string'){
                $this->initFromText($propstring);
            } else if(isset($refData)){
                if(gettype($refData) == 'string'){
                    $this->initFromText($refData);
                } else if(gettype($refData) == 'object') {
                    $this->initFromIterator($refData);
                }
            } else {
                //$text = '';
                //$this->initFromText($text);
            }


//            if(isset($this->iterator)){
//                $this->parseFrom($this->iterator);
//            }


        }

        function initFromIterator(&$iterator, $begin = -1){
            $this->iterator = &$iterator;

            //$this->seekBegin = $this->iterator->key();



            $iterator = $this->iterator;
            do {
                $line = $iterator->current();
                $seek = $iterator->key();

                $posStart = strpos(strtoupper($line), vComponent::KEYBEGIN);
                if($posStart !== false && $posStart == 0){
                    if(!isset($this->type)){
                        $this->seekBegin = $seek;

                        $this->type = strtoupper(substr($line, vComponent::KEYBEGINLENGTH));
                    }
                } else {

                    $posEnd = strpos(strtoupper($line), vComponent::KEYEND);
                    if($posEnd !== false && $posEnd == 0){
                        $thisEnd = substr($line, vComponent::KEYENDLENGTH);
                        if($thisEnd == $this->type){
                            $this->seekEnd = $seek;
                            //$iterator->next();
                            $len = strlen($this->type);
                            $last = $this->type[$len-1];
                            if($last == "\r"){
                                $this->type = strtoupper(substr($this->type, 0, $len-1));
                            }
                            break;
                        }

                    } else {
                        //$this->properties[] = new vProperty(null, $iterator, $seek);
                    }
                }




                $iterator->next();
            } while($iterator->valid());
            //$this->parseFrom($iterator);

        }

        public function getIterator(){
            return $this->iterator;
        }

        function initFromText(&$plainText){
            $plain2 = $this->UnwrapComponent($plainText);

            //$file = fopen('data.out.tmp', 'w');
            //$plain3 = preg_replace('{\r?\n}', '\r\n', $plain2 );
            //fwrite($file, $plain2);
            //fclose($file);
            //$lines = &explode(PHP_EOL, $plain2);
//            $arrayData = new ArrayObject($lines);
//            $this->iterator = &$arrayData->getIterator();
//            $this->initFromIterator($this->iterator, 0);
//            unset($plain);
//            unset($iterator);
//            unset($arrayData);
//            unset($lines);

            // Note that we can't use PHP_EOL here, since the line splitting should handle *either* of CR, CRLF or LF line endings.
            $arrayOfLines = new ArrayObject(preg_split('{\r?\n}', $plain2));
            $this->iterator = $arrayOfLines->getIterator();
            unset($plain2);
            //$this->initFromIterator($this->iterator);
            //$this->iterator = new HeapLines($plain);

            //$this->initFromIterator(new HeapLines($plain), 0);
            $this->parseFrom($this->iterator);

        }

        function rewind(){
            if(isset($this->iterator) && isset($this->seekBegin)){
                $this->iterator->seek($this->seekBegin);
            }
        }


        /**
         * fill arrays with components and properties if they are empty.
         *
         * basicaly the object are just pointer to memory with input data
         * (iterator with seek address on start and end)
         * but when you want get information of any components
         * or properties is necessary call this function first
         *
         * @see GetComponents(), ComponentsCount(), GetProperties(), PropertiesCount()
         */
        function explode(){
            if(!isset($this->properties) && !isset($this->components) && $this->isValid()){
                unset($this->properties);
                unset($this->components);
                unset($this->type);
                $this->rewind();
                $this->parseFrom($this->iterator);
            }
        }

        function close(){

            if(isset($this->components)){
                foreach($this->components as $comp){
                    $comp->close();
                }
            }

            if($this->isValid()){
                unset($this->properties);
                unset($this->components);
            }



        }

        function parseFrom(&$iterator){


            $begin = $iterator->key();
            $typelen = 0;
            //$count = $lines->count();

            do{
                $line = $iterator->current();
                //$line = substr($current, 0, strlen($current) -1);
                $end = $iterator->key();

                $pos = strpos(strtoupper($line), vComponent::KEYBEGIN);
                $callnext = true;
                if($pos !== false && $pos == 0) {
                    $type = strtoupper(substr($line, vComponent::KEYBEGINLENGTH));

                    if($typelen !== 0 && strncmp($this->type, $type, $typelen) !== 0){
                        $this->components[] = new vComponent(null, $iterator);
                        $callnext = false;
                    } else {
                        // in special cases when is "\r" on end remove it
                        // We should probably throw an error if we get here, because the
                        // thing that splits stuff should not be giving us this.
                        $typelen = strlen($type);
                        if($type[$typelen-1] == "\r"){
                            $typelen--;
                            $this->type = substr($type, 0, $typelen);
                        } else {
                            $this->type = $type;
                        }


                        //$iterator->offsetUnset($end);
                        //$iterator->seek($begin);
                        //$callnext = false;
                    }

                } else {
                    $pos = strpos(strtoupper($line), vComponent::KEYEND);

                    if($pos !== false && $pos == 0) {
                        $this->seekBegin = $begin;
                        $this->seekEnd = $end;
                        //$iterator->offsetUnset($end);
                        //$iterator->seek($end-2);
                        //$line2 = $iterator->current();
                        //$this->seekEnd = $iterator->key();

                        //$callnext = false;
                        //$newheap = $lines->createLineHeapFrom($start, $end);
                        //$testfistline = $newheap->substr(0);
                        //echo "end:" . $this->key . "[$start, $end]<br>";
                        //$lines->nextLine();
                        //$iterator->offsetUnset($end);
                        return;
                    } else {
//                    $prstart = $lines->getSwheretartLineOnHeap();
//                    $prend =
//$this->properties[] = new vProperty("AHOJ");
                        $parameters = preg_split( '(:|;)', $line);
                        $possiblename = strtoupper(array_shift( $parameters ));
                        $this->properties[] = new vProperty($possiblename, $this->master, $iterator, $end);
                        //echo $this->key . ' property line' . "[$prstart,$prend]<br>";

                    }
                }

//                if($callnext){
//                    $iterator->next();
//                }
                //if($callnext)
                //    $iterator->offsetUnset($end);
                if($iterator->valid())
                    $iterator->next();

            } while($iterator->valid() && ( !isset($this->seekEnd) || $this->seekEnd > $end ) );
            //$lines->getEndLineOnHeap();


        }



        /**
         * count of component
         * @return int
         */
        public function ComponentCount(){
            $this->explode();
            return isset($this->components) ? count($this->components) : 0;
        }

        /**
         * count of component
         * @return int
         */
        public function propertiesCount(){
            $this->explode();
            return isset($this->properties) ? count($this->properties) : 0;
        }

        /**
         * @param $position
         * @return null - whet is position out of range
         */
        public function getComponentAt($position){
            $this->explode();
            if($this->ComponentCount() > $position){
                return $this->components[$position];
            } else {
                return null;
            }
        }

        function getPropertyAt($position){
            $this->explode();
            if($this->propertiesCount() > $position){
                return $this->properties[$position];
            } else {
                return null;
            }

        }

        function clearPropertyAt($position) {
            $this->explode();
            if($this->isValid()){
                $this->invalidate();
            }

            $i=0;  // property index/position is relative to current vComponent
            foreach( $this->properties AS $k => $v ) {
                if ( $i == $position ) {
                    unset($this->properties[$k]);
                }
                $i++;
            }
            $this->properties = array_values($this->properties);
        }


        /**
         * Return the type of component which this is
         */
        function GetType() {
            return $this->type;
        }


        /**
         * Set the type of component which this is
         */
        function SetType( $type ) {
            if ( $this->isValid() ) {
                $this->invalidate();
            };
            $this->type = strtoupper($type);
            return $this->type;
        }


        /**
         * Collect an array of all parameters of our properties which are the specified type
         * Mainly used for collecting the full variety of references TZIDs
         */
        function CollectParameterValues( $parameter_name ) {
            $this->explode();
            $values = array();
            if(isset($this->components)){
                foreach( $this->components AS $k => $v ) {
                    $also = $v->CollectParameterValues($parameter_name);
                    $values = array_merge( $values, $also );
                }
            }
            if(isset($this->properties)){
                foreach( $this->properties AS $k => $v ) {
                    $also = $v->GetParameterValue($parameter_name);
                    if ( isset($also) && $also != "" ) {
//        dbg_error_log( 'vComponent', "::CollectParameterValues(%s) : Found '%s'", $parameter_name, $also);
                        $values[$also] = 1;
                    }
                }
            }

            return $values;
        }


        /**
         * Return the first instance of a property of this name
         */
        function GetProperty( $type ) {
            $this->explode();
            foreach( $this->properties AS $k => $v ) {
                if ( is_object($v) && $v->Name() == $type ) {
                    return $v;
                }
                else if ( !is_object($v) ) {
                    dbg_error_log("ERROR", 'vComponent::GetProperty(): Trying to get %s on %s which is not an object!', $type, $v );
                }
            }
            /** So we can call methods on the result of this, make sure we always return a vProperty of some kind */
            return null;
        }

        /**
         * Return the value of the first instance of a property of this name, or null
         */
        function GetPValue( $type ) {
            $this->explode();
            $p = $this->GetProperty($type);
            if ( isset($p) ) return $p->Value();
            return null;
        }


        /**
         * Get all properties, or the properties matching a particular type, or matching an
         * array associating property names with true values: array( 'PROPERTY' => true, 'PROPERTY2' => true )
         */
        function GetProperties( $type = null ) {
            // the properties in base are with name
            // it was setted in parseFrom(&interator)
            if(!isset($this->properties)){
                $this->explode();
            }
            $properties = array();
            $testtypes = (gettype($type) == 'string' ? array( $type => true ) : $type );
            foreach( $this->properties AS $k => $v ) {
                $name = $v->Name(); //get Property name (string)
                $name = preg_replace( '/^.*[.]/', '', $name ); //for grouped properties we remove prefix itemX.
                if ( $type == null || (isset($testtypes[$name]) && $testtypes[$name])) {
                    $properties[] = $v;
                }
            }
            return $properties;
        }

        /**
         * Return an array of properties matching the specified path
         *
         * @return array An array of vProperty within the tree which match the path given, in the form
         *  [/]COMPONENT[/...]/PROPERTY in a syntax kind of similar to our poor man's XML queries. We
         *  also allow COMPONENT and PROPERTY to be !COMPONENT and !PROPERTY for ++fun.
         *
         * @note At some point post PHP4 this could be re-done with an iterator, which should be more efficient for common use cases.
         */
        function GetPropertiesByPath( $path ) {
            $properties = array();
            dbg_error_log( 'vComponent', "GetPropertiesByPath: Querying within '%s' for path '%s'", $this->type, $path );
            if ( !preg_match( '#(/?)(!?)([^/]+)(/?.*)$#', $path, $matches ) ) return $properties;

            $anchored = ($matches[1] == '/');
            $inverted = ($matches[2] == '!');
            $ourtest = $matches[3];
            $therest = $matches[4];
            dbg_error_log( 'vComponent', "GetPropertiesByPath: Matches: %s -- %s -- %s -- %s\n", $matches[1], $matches[2], $matches[3], $matches[4] );
            if ( $ourtest == '*' || (($ourtest == $this->type) !== $inverted) && $therest != '' ) {
                if ( preg_match( '#^/(!?)([^/]+)$#', $therest, $matches ) ) {
                    $normmatch = ($matches[1] =='');
                    $proptest  = $matches[2];

                    $thisproperties = $this->GetProperties();
                    if(isset($thisproperties) && count($thisproperties) > 0){
                        foreach( $thisproperties AS $k => $v ) {
                            if ( $proptest == '*' || (($v->Name() == $proptest) === $normmatch ) ) {
                                $properties[] = $v;
                            }
                        }
                    }

                }
                else {
                    /**
                     * There is more to the path, so we recurse into that sub-part
                     */
                    foreach( $this->GetComponents() AS $k => $v ) {
                        $properties = array_merge( $properties, $v->GetPropertiesByPath($therest) );
                    }
                }
            }

            if ( ! $anchored ) {
                /**
                 * Our input $path was not rooted, so we recurse further
                 */
                foreach( $this->GetComponents() AS $k => $v ) {
                    $properties = array_merge( $properties, $v->GetPropertiesByPath($path) );
                }
            }
            dbg_error_log('vComponent', "GetPropertiesByPath: Found %d within '%s' for path '%s'\n", count($properties), $this->type, $path );
            return $properties;
        }

        /**
         * Clear all properties, or the properties matching a particular type
         * @param string|array $type The type of property - omit for all properties - or an
         * array associating property names with true values: array( 'PROPERTY' => true, 'PROPERTY2' => true )
         */
        function ClearProperties( $type = null ) {
            $this->explode();
            if($this->isValid()){
                $this->invalidate();
            }

            if ( $type != null ) {
                $testtypes = (gettype($type) == 'string' ? array( $type => true ) : $type );
                // First remove all the existing ones of that type
                foreach( $this->properties AS $k => $v ) {
                    if ( isset($testtypes[$v->Name()]) && $testtypes[$v->Name()] ) {
                        unset($this->properties[$k]);

                    }
                }
                $this->properties = array_values($this->properties);
            }
            else {

                $this->properties = array();
            }
        }

        /**
         * Set all properties, or the ones matching a particular type
         */
        function SetProperties( $new_properties, $type = null ) {
            $this->explode();
            $this->ClearProperties($type);
            foreach( $new_properties AS $k => $v ) {
                $this->properties[] = $v;
            }
        }

        /**
         * Adds a new property
         *
         * @param vProperty $new_property The new property to append to the set, or a string with the name
         * @param string $value The value of the new property (default: param 1 is an vProperty with everything
         * @param array $parameters The key/value parameter pairs (default: none, or param 1 is an vProperty with everything)
         */
        function AddProperty( $new_property, $value = null, $parameters = null ) {
            $this->explode();
            if ( isset($value) && gettype($new_property) == 'string' ) {
                $new_prop = new vProperty('', $this->master);
                $new_prop->Name($new_property);
                $new_prop->Value($value);
                if ( $parameters != null ) {
                    $new_prop->Parameters($parameters);
                }
//      dbg_error_log('vComponent'," Adding new property '%s'", $new_prop->Render() );
                $this->properties[] = $new_prop;
            }
            else if ( $new_property instanceof vProperty ) {
                $this->properties[] = $new_property;
                $new_property->setMaster($this->master);
            }

            if($this->isValid()){
                $this->invalidate();
            }
        }

        /**
         * Get all sub-components, or at least get those matching a type, or failling to match,
         * should the second parameter be set to false. Component types may be a string or an array
         * associating property names with true values: array( 'TYPE' => true, 'TYPE2' => true )
         *
         * @param mixed $type The type(s) to match (default: All)
         * @param boolean $normal_match Set to false to invert the match (default: true)
         * @return array an array of the sub-components
         */
        function GetComponents( $type = null, $normal_match = true ) {
            $this->explode();
            $components = isset($this->components) ? $this->components : array();

            if ( $type != null ) {
                //$components = $this->components;
                $testtypes = (gettype($type) == 'string' ? array( $type => true ) : $type );
                foreach( $components AS $k => $v ) {
//        printf( "Type: %s, %s, %s\n", $v->GetType(),
//                 ($normal_match && isset($testtypes[$v->GetType()]) && $testtypes[$v->GetType()] ? 'true':'false'),
//                 ( !$normal_match && (!isset($testtypes[$v->GetType()]) || !$testtypes[$v->GetType()]) ? 'true':'false')
//               );
                    if ( !($normal_match && isset($testtypes[$v->GetType()]) && $testtypes[$v->GetType()] )
                        && !( !$normal_match && (!isset($testtypes[$v->GetType()]) || !$testtypes[$v->GetType()])) ) {
                        unset($components[$k]);
                    }
                }
                $components = array_values($components);
            }
//    print_r($components);
            return $components;
        }


        /**
         * Clear all components, or the components matching a particular type
         * @param string $type The type of component - omit for all components
         */
        function ClearComponents( $type = null ) {
            if($this->isValid()){
                $this->explode();
            }


            if ( $type != null && !empty($this->components)) {
                $testtypes = (gettype($type) == 'string' ? array( $type => true ) : $type );
                // First remove all the existing ones of that type
                foreach( $this->components AS $k => $v ) {
                    $this->components[$k]->ClearComponents($testtypes);
                    if ( isset($testtypes[$v->GetType()]) && $testtypes[$v->GetType()] ) {
                        unset($this->components[$k]);
                        if ( $this->isValid()) {
                            $this->invalidate();
                        }
                    }

                }
            }
            else {
                $this->components = array();
                if ( $this->isValid()) {
                    $this->invalidate();
                }
            }

            return $this->isValid();
        }

        /**
         * Sets some or all sub-components of the component to the supplied new components
         *
         * @param array of vComponent $new_components The new components to replace the existing ones
         * @param string $type The type of components to be replaced.  Defaults to null, which means all components will be replaced.
         */
        function SetComponents( $new_component, $type = null ) {
            $this->explode();
            if ( $this->isValid()) {
                $this->invalidate();
            }
            if ( empty($type) ) {
                $this->components = $new_component;
                return;
            }

            $this->ClearComponents($type);
            foreach( $new_component AS $k => $v ) {
                $this->components[] = $v;
            }
        }

        /**
         * Adds a new subcomponent
         *
         * @param vComponent $new_component The new component to append to the set
         */
        public function AddComponent( $new_component ) {
            $this->explode();
            if ( is_array($new_component) && count($new_component) == 0 ) return;

            if ( $this->isValid()) {
                $this->invalidate();
            }

            try {
	            if ( is_array($new_component) ) {
	                foreach( $new_component AS $k => $v ) {
	                    $this->components[] = $v;
	                    if ( !method_exists($v,'setMaster') ) fatal('Component to be added must be a vComponent');
	                    $v->setMaster($this->master);
	                }
	            }
	            else {
	                if ( !method_exists($new_component,'setMaster') ) fatal('Component to be added must be a vComponent');
	            	$new_component->setMaster($this->master);
	            	$this->components[] = $new_component;
	            }
            }
            catch( Exception $e ) {
            	fatal();
            }
        }


        /**
         * Mask components, removing any that are not of the types in the list
         * @param array $keep An array of component types to be kept
         * @param boolean $recursive (default true) Whether to recursively MaskComponents on the ones we find
         */
        function MaskComponents( $keep, $recursive = true ) {
            $this->explode();
            if(!isset($this->components)){
                return ;
            }

            foreach( $this->components AS $k => $v ) {
                if ( !isset($keep[$v->GetType()]) ) {
                    unset($this->components[$k]);
                    if ( $this->isValid()) {
                        $this->invalidate();
                    }
                }
                else if ( $recursive ) {
                    $v->MaskComponents($keep);
                }
            }
        }

        /**
         * Mask properties, removing any that are not in the list
         * @param array $keep An array of property names to be kept
         * @param array $component_list An array of component types to check within
         */
        function MaskProperties( $keep, $component_list=null ) {
            $this->explode();
            if ( !isset($component_list) || isset($component_list[$this->type]) ) {
                foreach( $this->properties AS $k => $v ) {
                    if ( !isset($keep[$v->Name()]) || !$keep[$v->Name()] ) {
                        unset($this->properties[$k]);
                        if ( $this->isValid()) {
                            $this->invalidate();
                        }
                    }
                }
            }
            if(isset($this->components)){
                foreach( $this->components AS $k => $v ) {
                    $v->MaskProperties($keep, $component_list);
                }
            }

        }

        /**
         * This imposes the (CRLF + linear space) wrapping specified in RFC2445. According
         * to RFC2445 we should always end with CRLF but the CalDAV spec says that normalising
         * XML parsers often muck with it and may remove the CR.  We output RFC2445 compliance.
         *
         * In order to preserve pre-existing wrapping in the component, we split the incoming
         * string on line breaks before running wordwrap over each component of that.
         */
        function WrapComponent( $content ) {
            $strs = preg_split( "/\r?\n/", $content );
            $wrapped = "";
            foreach ($strs as $str) {
//                print "Before: >>$str<<, len(".strlen($str).")\n";
                $wrapped_bit = (strlen($str) == 72 ? $str : preg_replace( '/(.{72})/u', '$1'."\r\n ", $str )) .self::VEOL;
//                print "After: >>$wrapped_bit<<\n";
                $wrapped .= $wrapped_bit;
            }
            return $wrapped;
        }

        /**
         * This unescapes the (CRLF + linear space) wrapping specified in RFC2445. According
         * to RFC2445 we should always end with CRLF but the CalDAV spec says that normalising
         * XML parsers often muck with it and may remove the CR.  We accept either case.
         */
        function UnwrapComponent( &$content ) {
            return preg_replace('/\r?\n[ \t]/', '', $content );
        }


        /**
         * Render vComponent without wrap lines
         * @param null $restricted_properties
         * @param bool $force_rendering
         * @return string
         */
        protected function RenderWithoutWrap($restricted_properties = null, $force_rendering = false){
            $unrolledComponents = isset($this->components);
            $rendered = vComponent::KEYBEGIN . $this->type . self::VEOL;


            if($this->isValid()){
                $rendered .= $this->RenderWithoutWrapFromIterator($unrolledComponents);
            } else {
                $rendered .= $this->RenderWithoutWrapFromObjects();
            }

            if($unrolledComponents){
                //$count = 0;
                foreach($this->components as $component){
                    //$component->explode();
                    //$count++;
                    $component_render = $component->RenderWithoutWrap( null, $force_rendering );
                    if(strlen($component_render) > 0){
                        $rendered .= $component_render . self::VEOL;
                    }

                    //$component->close();

                }
            }

            return $rendered . vComponent::KEYEND . $this->type;
        }

        /**
         * Let render property by property
         * @return string
         */
        protected function RenderWithoutWrapFromObjects(){
            $rendered = '';
            if(isset($this->properties)){
                foreach( $this->properties AS $k => $v ) {
                    if ( method_exists($v, 'Render') ) {
                        $forebug = $v->Render() . self::VEOL;
                        $rendered .= $forebug;
                    }
                }
            }

            return $rendered;
        }

        /**
         * take source data in Iterator and recreate to string
         * @param boolean $unroledComponents - have any components
         * @return string - rendered object
         */
        protected function RenderWithoutWrapFromIterator($unrolledComponents){
            $this->rewind();
            $rendered = '';
            $lentype = 0;

            if(isset($this->type)){
                $lentype = strlen($this->type);
            }

            $iterator = $this->iterator;
            $inInnerObject = 0;
            do {
                $line = $iterator->current() . self::VEOL;
                $seek = $iterator->key();

                $posStart = strpos($line, vComponent::KEYBEGIN);
                if($posStart !== false && $posStart == 0){
                    $type = substr($line, vComponent::KEYBEGINLENGTH);
                    if(!isset($this->type)){
                        //$this->seekBegin = $seek;
                        $this->type = $type;
                        $lentype = strlen($this->type);
                    } else if(strncmp($type, $this->type, $lentype) != 0){
                        // dont render line which is owned
                        // by inner commponent -> inner component *BEGIN*
                        if($unrolledComponents){
                            $inInnerObject++;
                        } else {
                            $rendered .= $line ;
                        }
                    }
                } else {

                    $posEnd = strpos($line, vComponent::KEYEND);
                    if($posEnd !== false && $posEnd == 0){
                        $thisEnd = substr($line, vComponent::KEYENDLENGTH);
                        if(strncmp($thisEnd, $this->type, $lentype) == 0){
                            // Current object end
                            $this->seekEnd = $seek;
                            //$iterator->next();
                            break;
                        }else if($unrolledComponents){
                            // dont render line which is owned
                            // by inner commponent -> inner component *END*
                            $inInnerObject--;
                        } else {
                            $rendered .= $line;
                        }

                    } else if($inInnerObject === 0 || !$unrolledComponents){
                        $rendered .= $line;
                    }
                }
                $iterator->next();
            } while($iterator->valid() && ( !isset($this->seekEnd) || $this->seekEnd > $seek));


            return $rendered;

        }


        /**
         * render object to string with wraped lines
         * @param null $restricted_properties
         * @param bool $force_rendering
         * @return string - rendered object
         */
        function Render($restricted_properties = null, $force_rendering = false){
            return $this->WrapComponent($this->RenderWithoutWrap($restricted_properties, $force_rendering));
            //return $this->InternalRender($restricted_properties, $force_rendering);
        }

        function isValid(){
            if($this->valid){
                if(isset($this->components)){
                    foreach($this->components as $comp){
                        if(!$comp->isValid()){
                            return false;
                        }
                    }
                }

                return true;
            }
            return false;
        }

 /**
   * Test a PROP-FILTER or COMP-FILTER and return a true/false
   * COMP-FILTER (is-defined | is-not-defined | (time-range?, prop-filter*, comp-filter*))
   * PROP-FILTER (is-defined | is-not-defined | ((time-range | text-match)?, param-filter*))
   *
   * @param array $filter An array of XMLElement defining the filter
   *
   * @return boolean Whether or not this vComponent passes the test
   */
  function TestFilter( $filters ) {
    foreach( $filters AS $k => $v ) {
      $tag = $v->GetNSTag();
//      dbg_error_log( 'vCalendar', ":TestFilter: '%s' ", $tag );
      switch( $tag ) {
        case 'urn:ietf:params:xml:ns:caldav:is-defined':
        case 'urn:ietf:params:xml:ns:carddav:is-defined':
          if ( count($this->properties) == 0 && count($this->components) == 0 ) return false;
          break;
        
        case 'urn:ietf:params:xml:ns:caldav:is-not-defined':
        case 'urn:ietf:params:xml:ns:carddav:is-not-defined':
          if ( count($this->properties) > 0 || count($this->components) > 0 ) return false;
          break;

        case 'urn:ietf:params:xml:ns:caldav:comp-filter':
        case 'urn:ietf:params:xml:ns:carddav:comp-filter':
          $subcomponents = $this->GetComponents($v->GetAttribute('name'));
          $subfilter = $v->GetContent();
//          dbg_error_log( 'vCalendar', ":TestFilter: Found '%d' (of %d) subs of type '%s'",
//                       count($subcomponents), count($this->components), $v->GetAttribute('name') );
          $subtag = $subfilter[0]->GetNSTag(); 
          if ( $subtag == 'urn:ietf:params:xml:ns:caldav:is-not-defined'
          			 || $subtag == 'urn:ietf:params:xml:ns:carddav:is-not-defined' ) {
            if ( count($properties) > 0 ) {
//              dbg_error_log( 'vComponent', ":TestFilter: Wanted none => false" );
              return false;
            }
          }
          else if ( count($subcomponents) == 0 ) {
            if ( $subtag == 'urn:ietf:params:xml:ns:caldav:is-defined'
          			 || $subtag == 'urn:ietf:params:xml:ns:carddav:is-defined' ) {
//              dbg_error_log( 'vComponent', ":TestFilter: Wanted some => false" );
              return false;
            }
            else {
//              dbg_error_log( 'vCalendar', ":TestFilter: Wanted something from missing sub-components => false" );
              $negate = $subfilter[0]->GetAttribute("negate-condition");
              if ( empty($negate) || strtolower($negate) != 'yes' ) return false;
            }
          }
          else {
            foreach( $subcomponents AS $kk => $subcomponent ) {
              if ( ! $subcomponent->TestFilter($subfilter) ) return false;
            }
          }
          break;

        case 'urn:ietf:params:xml:ns:carddav:prop-filter':
        case 'urn:ietf:params:xml:ns:caldav:prop-filter':
          $subfilter = $v->GetContent();
          $properties = $this->GetProperties($v->GetAttribute("name"));
          dbg_error_log( 'vCalendar', ":TestFilter: Found '%d' props of type '%s'", count($properties), $v->GetAttribute('name') );
          $subtag = $subfilter[0]->GetNSTag();
          if ( $subtag == 'urn:ietf:params:xml:ns:caldav:is-not-defined'
          			 || $subtag == 'urn:ietf:params:xml:ns:carddav:is-not-defined' ) {
            if ( count($properties) > 0 ) {
//              dbg_error_log( 'vCalendar', ":TestFilter: Wanted none => false" );
              return false;
            }
          }
          else if ( count($properties) == 0 ) {
            if ( $subtag == 'urn:ietf:params:xml:ns:caldav:is-defined'
            			 || $subtag == 'urn:ietf:params:xml:ns:carddav:is-defined' ) {
//              dbg_error_log( 'vCalendar', ":TestFilter: Wanted some => false" );
              return false;
            }
            else {
//              dbg_error_log( 'vCalendar', ":TestFilter: Wanted '%s' from missing sub-properties => false", $subtag );
              $negate = $subfilter[0]->GetAttribute("negate-condition");
              if ( empty($negate) || strtolower($negate) != 'yes' ) return false;
            }
          }
          else {
            foreach( $properties AS $kk => $property ) {
              if ( !$property->TestFilter($subfilter) ) return false;
            }
          }
          break;
      }
    }
    return true;
  }

    }


