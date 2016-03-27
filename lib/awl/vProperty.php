<?php

require_once('XMLElement.php');
/**
 * A Class for representing properties within a myComponent (VCALENDAR or VCARD)
 *
 * @package awl
 */
class vProperty extends vObject {
    /**#@+
     * @access private
     */

    /**
     * The name of this property
     *
     * @var string
     */
    protected $name;

    /**
     * An array of parameters to this property, represented as key/value pairs.
     *
     * @var array
     */
    protected $parameters;

    /**
     * The value of this property.
     *
     * @var string
     */
    protected $content;

    /**
     * The original value that this was parsed from, if that's the way it happened.
     *
     * @var ArrayIterator
     */
    protected $iterator;

    /**
     * The original seek of iterator
     * @var int
     */
    protected $seek;

    protected $line;

    //protected $rendered;


    /**#@-*/

    /**
     * Parsing of the incoming string is now performed lazily, in ParseFromIterator.
     * You should use getter methods such as Value() and getParameterValue() instead of direct access
     * to $content, $parameters etc, to ensure that parsing has occurred.
     *
     */
    function __construct( $name = null, &$master = null, &$refData = null, $seek = null ) {
        parent::__construct($master);


        if(isset($name) && strlen($name) > 0){
            $this->name = $name;
        } else {
            unset($this->name);
        }

        unset($this->content);
        unset($this->parameters);

        if ( isset($refData)){

            if(gettype($refData) == 'object') {
                $this->iterator = &$refData;
                $this->seek = &$seek;
                unset($this->line);
            } else {
                $this->line = $refData;

                unset($this->iterator);
                unset($this->seek);
            }
        } else {
            unset($this->iterator);
            unset($this->seek);

        }
    }

    /**
     * Parses the incoming string, which is formatted as per RFC2445 as a
     *   propname[;param1=pval1[; ... ]]:propvalue
     * However we allow ourselves to assume that the RFC2445 content unescaping has already
     * happened when myComponent::ParseFrom() called myComponent::UnwrapComponent().
     *
     * Note this function is called lazily, from the individual getter methods. This avoids the cost of parsing at
     * the point of object instantiation.
     */
    function ParseFromIterator()
    {
        $unescaped;

        if (isset($this->iterator)) {
            $this->iterator->seek($this->seek);
            $unescaped = $this->iterator->current();
        } else if (isset($this->line)) {
            $unescaped = $this->line;
        } else {
            $unescaped = '';
        }

        $this->ParseFrom($unescaped);
        unset($unescaped);
    }

    function ParseFrom( &$unescaped ) {
        // unescape \r and \n in the value
        $unescaped = preg_replace( array('{\\\\[nN]}', '{\\\\[rR]}'), array("\n", "\r"), $unescaped);

        // Split into two parts on : which is not preceded by a \, or within quotes like "str:ing".
        $offset = 0;
        do {
            $splitpos = strpos($unescaped,':',$offset);
            $start = substr($unescaped,0,$splitpos);
            if ( substr($start,-1) == '\\' ) {
                $offset = $splitpos + 1;
                continue;
            }
            $quotecount = strlen(preg_replace('{[^"]}', '', $start ));
            if ( ($quotecount % 2) != 0 ) {
                $offset = $splitpos + 1;
                continue;
            }
            break;
        }
        while( true );
        $values = substr($unescaped, $splitpos+1);

        $possiblecontent = preg_replace( "/\\\\([,:\"\\\\])/", '$1', $values);
        // in case if the name was set manualy content by function Valued
        // -> don't reset it by $rendered data
        if(!isset($this->content)){
            // TODO: add "\r" to preg_replace at begin
            $len = strlen($possiblecontent);
            if($len > 0 && "\r" == $possiblecontent[$len-1]){

                $possiblecontent = substr($possiblecontent, 0, $len-1);
            }
            $this->content = $possiblecontent;
        }


        // Split on ; which is not preceded by a \
        $parameters = preg_split( '{(?<!\\\\);}', $start);


        $possiblename = strtoupper(array_shift( $parameters ));
        // in case if the name was set manualy by function Name
        // -> don't reset it by $rendered data
        if(!isset($this->name)){
            $this->name = $possiblename;
        }

        // in case if the parameter was set manualy by function Parameters
        // -> don't reset it by $rendered data
        if(!isset($this->parameters)){
            $this->parameters = array();
            foreach( $parameters AS $k => $v ) {
                $pos = strpos($v,'=');
                if($pos !== FALSE) {
                    $name = strtoupper(substr( $v, 0, $pos));
                    $value = substr( $v, $pos + 1);
                }
                else {
                    $name = strtoupper($v);
                    $value = null;
                }
                if ( preg_match( '{^"(.*)"$}', $value, $matches) ) {
                    $value = $matches[1];
                }
                if ( isset($this->parameters[$name]) && is_array($this->parameters[$name]) ) {
                    $this->parameters[$name][] = $value;
                }
                elseif ( isset($this->parameters[$name]) ) {
                    $this->parameters[$name] = array( $this->parameters[$name], $value);
                }
                else
                    $this->parameters[$name] = $value;
            }
        }
//    dbg_error_log('myComponent', " vProperty::ParseFrom found '%s' = '%s' with %d parameters", $this->name, substr($this->content,0,200), count($this->parameters) );
    }


    /**
     * Get/Set name property
     *
     * @param string $newname [optional] A new name for the property
     *
     * @return string The name for the property.
     */
    function Name( $newname = null ) {
        if ( $newname != null ) {
            $this->name = strtoupper($newname);
            if ( $this->isValid() ) $this->invalidate();
//      dbg_error_log('myComponent', " vProperty::Name(%s)", $this->name );
        } else if(!isset($this->name)){
            $this->ParseFromIterator();
        }
        return $this->name;
    }


    /**
     * Get/Set the content of the property
     *
     * @param string $newvalue [optional] A new value for the property
     *
     * @return string The value of the property.
     */
    function Value( $newvalue = null ) {
        if ( $newvalue != null ) {
            $this->content = $newvalue;
            if ( $this->isValid() ) $this->invalidate();
        } else if(!isset($this->content)){
            $this->ParseFromIterator();
        }
        return $this->content;
    }


    /**
     * Get/Set parameters in their entirety
     *
     * @param array $newparams An array of new parameter key/value pairs.  The 'value' may be an array of values.
     *
     * @return array The current array of parameters for the property.
     */
    function Parameters( $newparams = null ) {
        if ( $newparams != null ) {
            $this->parameters = array();
            foreach( $newparams AS $k => $v ) {
                $this->parameters[strtoupper($k)] = $v;
            }
            if ( $this->isValid() ) $this->invalidate();
        } else if(!isset($this->parameters)){
            $this->ParseFromIterator();
        }
        return $this->parameters;
    }


    /**
     * Test if our value contains a string
     *
     * @param string $search The needle which we shall search the haystack for.
     *
     * @return string The name for the property.
     */
    function TextMatch( $search ) {
        if ( isset($this->content) ) return strstr( $this->content, $search );
        return false;
    }


    /**
     * Get the value of a parameter
     *
     * @param string $name The name of the parameter to retrieve the value for
     *
     * @return string The value of the parameter
     */
    function GetParameterValue( $name ) {
        $name = strtoupper($name);

        if(!isset($this->parameters)){
            $this->ParseFromIterator();
        }

        if ( isset($this->parameters[$name]) ){
            return $this->parameters[$name];
        }
        return null;
    }

    /**
     * Set the value of a parameter
     *
     * @param string $name The name of the parameter to set the value for
     *
     * @param string $value The value of the parameter
     */
    function SetParameterValue( $name, $value ) {
        if(!isset($this->parameters)){
            $this->ParseFromIterator();
        }

        if ( $this->isValid() ) {
            $this->invalidate();
        }
            //tests/regression-suite/0831-Spec-RRULE-1.result
        //./dav_test --dsn 'davical_milan;port=5432' --webhost 127.0.0.1 --althost altcaldav --suite 'regression-suite' --case 'tests/regression-suite/0831-Spec-RRULE-1'
        $this->parameters[strtoupper($name)] = $value;
//    dbg_error_log('PUT', $this->name.$this->RenderParameters().':'.$this->content );
    }

    /**                                                                
     * Clear all parameters, or the parameters matching a particular type
     * @param string|array $type The type of parameters or an
     * array associating parameter names with true values: array( 'PARAMETER' => true, 'PARAMETER2' => true )
     */
    function ClearParameters( $type = null ) {
        if(!isset($this->parameters)){
            $this->ParseFromIterator();
        }

        if ( $this->isValid() ) {
            $this->invalidate();
        }

        if ( $type != null ) {
            $testtypes = (gettype($type) == 'string' ? array( $type => true ) : $type );
            // First remove all the existing ones of that type
            foreach( $this->parameters AS $k => $v ) {
                if ( isset($testtypes[$k]) && $testtypes[$k] ) {
                    unset($this->parameters[$k]);
                }
            }
        }
    }

    private static function escapeParameter($p) {
        if ( strpos($p, ';') === false && strpos($p, ':') === false ) return $p;
        return '"'.str_replace('"','\\"',$p).'"';
    }

    /**
     * Render the set of parameters as key1=value1[;key2=value2[; ...]] with
     * any colons or semicolons escaped.
     */
    function RenderParameters() {
        $rendered = "";
        if(isset($this->parameters)){
            foreach( $this->parameters AS $k => $v ) {
                if ( is_array($v) ) {
                    foreach( $v AS $vv ) {
                        $rendered .= sprintf( ';%s=%s', $k, vProperty::escapeParameter($vv) );
                    }
                }
                else {
                    if($v !== null) {
                        $rendered .= sprintf( ';%s=%s', $k, vProperty::escapeParameter($v) );
                    }
                    else {
                        $rendered .= sprintf( ';%s', $k);
                    }
                }
            }
        }

        return $rendered;
    }


    /**
     * Render a suitably escaped RFC2445 content string.
     */
    function Render( $force = false ) {
        // If we still have the string it was parsed in from, it hasn't been screwed with
        // and we can just return that without modification.
//        if ( $force === false && $this->isValid() && isset($this->rendered) && strlen($this->rendered) < 73 ) {
//            return $this->rendered;
//        }

        // in case one of the memberts doesn't set -> try parse from rendered
        if(!isset($this->name) || !isset($this->content) || !isset($this->parameters)) {
            $this->ParseFromIterator();
        }
        $property = preg_replace( '/[;].*$/', '', $this->name );
        $escaped = $this->content;
        $property = preg_replace( '/^.*[.]/', '', $property ); //temporarily remove grouping prefix from CARDDAV attributes ("item1.", "item2.", etc)
        switch( $property ) {
            /** Content escaping does not apply to these properties culled from RFC2445 */
            case 'ATTACH':                case 'GEO':                       case 'PERCENT-COMPLETE':      case 'PRIORITY':
            case 'DURATION':              case 'FREEBUSY':                  case 'TZOFFSETFROM':          case 'TZOFFSETTO':
            case 'TZURL':                 case 'ATTENDEE':                  case 'ORGANIZER':             case 'RECURRENCE-ID':
            case 'URL':                   case 'EXRULE':                    case 'SEQUENCE':              case 'CREATED':
            case 'RRULE':                 case 'REPEAT':                    case 'TRIGGER':               case 'RDATE':
            case 'COMPLETED':             case 'DTEND':                     case 'DUE':                   case 'DTSTART':
            case 'DTSTAMP':               case 'LAST-MODIFIED':             case 'CREATED':               case 'EXDATE':
            break;

            /** Content escaping does not apply to these properties culled from RFC6350 / RFC2426 */
            case 'ADR':                case 'N':            case 'ORG':
            // escaping for ';' for these fields also needs to happen to the components they are built from.
            $escaped = preg_replace( '/\r?\n/', '\\n', $escaped);
            $escaped = str_replace( ',', '\\,', $escaped);
            break;

            /** Content escaping applies by default to other properties */
            default:
                $escaped = preg_replace( '/\r?\n/', '\\n', $escaped);
                $escaped = preg_replace( "/([,])/", '\\\\$1', $escaped);
        }

        $rendered = '';

        $property = sprintf( "%s%s:", $this->name, $this->RenderParameters() );
        if ( (strlen($property) + strlen($escaped)) <= 72 ) {
            $rendered = $property . $escaped;
        }
        else if ( (strlen($property) <= 72) && (strlen($escaped) <= 72) ) {
            $rendered = $property . "\r\n " . $escaped;
        }
        else {
            $rendered = preg_replace( '/(.{72})/u', '$1'."\r\n ", $property.$escaped );
        }
//    trace_bug( 'Re-rendered "%s" property.', $this->name );
        return $rendered;
    }


    public function __toString() {
        return $this->Render();
    }


    /**
     * Test a PROP-FILTER or PARAM-FILTER and return a true/false
     * PROP-FILTER (is-defined | is-not-defined | ((time-range | text-match)?, param-filter*))
     * PARAM-FILTER (is-defined | is-not-defined | ((time-range | text-match)?, param-filter*))
     *
     * Changed by GitLab user moosemark 2015-09-18 to fix initialisation of $content property (AWL issue 10)
     *
     * @param array $filter An array of XMLElement defining the filter
     *
     * @return boolean Whether or not this vProperty passes the test
     */
    function TestFilter( $filters ) {
        foreach( $filters AS $k => $v ) {
            $tag = $v->GetNSTag();
//      dbg_error_log( 'vCalendar', "vProperty:TestFilter: '%s'='%s' => '%s'", $this->name, $tag, $this->content );
            switch( $tag ) {
                case 'urn:ietf:params:xml:ns:caldav:is-defined':
                case 'urn:ietf:params:xml:ns:carddav:is-defined':
                    if ( empty($this->content) ) return false;
                    break;

                case 'urn:ietf:params:xml:ns:caldav:is-not-defined':
                case 'urn:ietf:params:xml:ns:carddav:is-not-defined':
                    if ( ! empty($this->content) ) return false;
                    break;

                case 'urn:ietf:params:xml:ns:caldav:time-range':
                    /** @todo: While this is unimplemented here at present, most time-range tests should occur at the SQL level. */
                    break;

                case 'urn:ietf:params:xml:ns:carddav:text-match':
                case 'urn:ietf:params:xml:ns:caldav:text-match':
                    $search = $v->GetContent();
                    // Call the Value() getter method to get hold of the vProperty content - need to ensure parsing has occurred
                    $haystack = $this->Value();
                    $match = isset($haystack);
                    if ( $match ) {
                             $collation = $v->GetAttribute("collation");
                             switch( strtolower($collation) ) {
                             case 'i;octet':
                                 // don't change search and haystack
                                 break;
                             case 'i;ascii-casemap':
                             case 'i;unicode-casemap':
                             default:
                                 // for ignore case search we transform
                                 // search and haystack to lowercase
                                 $search   = strtolower( $search );
                                 $haystack = strtolower( $haystack );
                                 break;
                             }

                             $matchType = $v->GetAttribute("match-type");
                             switch( strtolower($matchType) ) {
                             case 'equals':
                                 $match = ( $haystack === $search );
                                 break;
                             case 'starts-with':
                                $length = strlen($search);
                                 if ($length == 0) {
                                     $match = true;
                                 } else {
                                     $match = !strncmp($haystack, $search, $length);
                                 }
                                 break;
                             case 'ends-with':
                                $length = strlen($search);
                                 if ($length == 0) {
                                     $match = true;
                                 } else {
                                     $match = ( substr($haystack, -$length) === $search );
                                 }
                                 break;
                             default: // contains
                                 $match = strstr( $haystack, $search );
                                 break;
                             }
                    }

                    $negate = $v->GetAttribute("negate-condition");
                    if ( isset($negate) && strtolower($negate) == "yes" ) {
                             $match = !$match;
                    }
                    if ( ! $match ) return false;
                    break;

                case 'urn:ietf:params:xml:ns:carddav:param-filter':
                case 'urn:ietf:params:xml:ns:caldav:param-filter':
                    $subfilter = $v->GetContent();
                    $parameter = $this->GetParameterValue($v->GetAttribute("name"));
                    if ( ! $this->TestParamFilter($subfilter,$parameter) ) return false;
                    break;

                default:
                    dbg_error_log( 'myComponent', ' vProperty::TestFilter: unhandled tag "%s"', $tag );
                    break;
            }
        }
        return true;
    }

    function fill($sp, $en, $pe){

    }

    function TestParamFilter( $filters, $parameter_value ) {
        foreach( $filters AS $k => $v ) {
            $subtag = $v->GetNSTag();
//      dbg_error_log( 'vCalendar', "vProperty:TestParamFilter: '%s'='%s' => '%s'", $this->name, $subtag, $parameter_value );
            switch( $subtag ) {
                case 'urn:ietf:params:xml:ns:caldav:is-defined':
                case 'urn:ietf:params:xml:ns:carddav:is-defined':
                    if ( empty($parameter_value) ) return false;
                    break;

                case 'urn:ietf:params:xml:ns:caldav:is-not-defined':
                case 'urn:ietf:params:xml:ns:carddav:is-not-defined':
                    if ( ! empty($parameter_value) ) return false;
                    break;

                case 'urn:ietf:params:xml:ns:caldav:time-range':
                    /** @todo: While this is unimplemented here at present, most time-range tests should occur at the SQL level. */
                    break;

                case 'urn:ietf:params:xml:ns:carddav:text-match':
                case 'urn:ietf:params:xml:ns:caldav:text-match':
                    $search = $v->GetContent();
                    $match = false;
                    if ( !empty($parameter_value) ) $match = strstr( $this->content, $search );
                    $negate = $v->GetAttribute("negate-condition");
                    if ( isset($negate) && strtolower($negate) == "yes" ) {
                        $match = !$match;
                    }
                    if ( ! $match ) return false;
                    break;

                default:
                    dbg_error_log( 'myComponent', ' vProperty::TestParamFilter: unhandled tag "%s"', $tag );
                    break;
            }
        }
        return true;
    }
}
