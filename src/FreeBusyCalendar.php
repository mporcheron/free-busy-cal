<?php

/**
 * Data store of the availablility.
 *
 * @package MPorcheron\FreeBusyCal
 */

namespace MPorcheron\FreeBusyCal;

/**
 * Calendar of a person's availability (either one caelndar or multiple).
 *
 * @author Martin Porcheron <martin@porcheron.uk>
 * @copyright (c) Martin Porcheron 2016.
 * @license MIT Licence
 */

class FreeBusyCalendar extends \ArrayObject
{

	/**
	 * Construct the availability matrix.
	 * 
	 * @param mixed $input
	 * 	The input parameter accepts an array or an Object.
	 * @param int $flags
	 * 	Flags to control the behaviour of the ArrayObject object.
	 * @param stirng iterator_class
	 * 	Specify the class that will be used for iteration of the ArrayObject object.
	 */
	public function __construct($input = [], $flags = 0, $iterator_class = 'ArrayIterator') {
		parent::__construct($input, $flags, $iterator_class);
	}

	/**
	 * Merge availability data. If busy, will remain busy.
	 * 
	 * @param MPorcheron\FreeBusyCal\FreeBusyCalendar $cal
	 * 	Availability matrix to merge.
	 */
	public function merge(MPorcheron\FreeBusyCal\FreeBusyCalendar $cal) {
		$this->_merge($cal->getArrayCopy());
	}

	/**
	 * Merge availability data. If busy, will remain busy.
	 * 
	 * @param mixed[] $arr
	 * 	Array of availability to merge.
	 */
	private function _merge(array &$arr) {
		foreach ($arr as $key => $value) {
			if (is_array($value)) {
				$this->_merge($value);
			} else {
				$existing = $this->offsetExists($key) ? $this->offsetGet($key) : 0;
				$this->offsetSet($key, max($existing, $value));
			}
		}
	}
}