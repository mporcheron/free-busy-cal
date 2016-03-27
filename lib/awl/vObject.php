<?php
/**
 * Created by JetBrains PhpStorm.
 * User: milan
 * Date: 7/4/13
 * Time: 12:59 PM
 * To change this template use File | Settings | File Templates.
 */

abstract class vObject {

    protected $lineHeap;

    protected $valid = true;
    protected $master;

    function __construct(&$master = null){
        $this->master = isset($master) ? $master : $this;
    }


    function isValid(){
        return $this->valid;
    }

    protected function invalidate(){
        if ( isset($this->master) && $this->master != $this ) $this->master->invalidate();
        $this->valid = false;
    }

    function setMaster($master){
        $this->master = $master;
    }

    public function getMaster(){
        return $this->master;
    }

    /**
     * parse a lineHead to component or propertie
     * @return
     */
    //abstract function parse();
}