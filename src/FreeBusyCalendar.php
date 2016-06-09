<?php

/**
 * Data store of the availablility.
 *
 * @package \MPorcheron\FreeBusyCal
 */

namespace \MPorcheron\FreeBusyCal;

/**
 * Calendar of a person's availability (either one caelndar or multiple).
 *
 * @author    Martin Porcheron <martin@porcheron.uk>
 * @copyright (c) Martin Porcheron 2016.
 * @license   MIT Licence
 */

class FreeBusyCalendar extends \ArrayObject
{
    /**
     * @var string Default date format.
     */
    const DATE_FORMAT = 'j/n';

    /**
     * @var string Default time format.
     */
    const TIME_FORMAT = 'G:i';

    /**
     * @var mixed[] Configuration data.
     */
    private $config;

    /**
     * Construct the availability matrix.
     *
     * @param mixed                 $input
     *     The input parameter accepts an array or an Object.
     * @param int                   $flags
     *     Flags to control the behaviour of the ArrayObject object.
     * @param stirng iterator_class
     *     Specify the class that will be used for iteration of the ArrayObject object.
     */
    public function __construct($input = [], $flags = 0, $iterator_class = 'ArrayIterator')
    {
        parent::__construct($input, $flags, $iterator_class);
    }

    /**
     * Set the configuration values to be used in the generation of the
     * representation of the calendar.
     *
     * @param  mixed[] $config
     *     Configuration dats.
     * @return \MPorcheron\FreeBusyCal\FreeBusyCalendar
     *     `$this`.
     */
    public function setConfig(array &$config)
    {
        $this->config =& $config;
        return $this;
    }

    /**
     * Retrieve the values labels for the calendar dates to be displayed.
     *
     * @see    http://php.net/manual/en/datetime.construct.php
     * @param  string $format
     *  PHP `date` format the dates to be displayed.
     * @return string[]
     *  Array of the calendar dates to be displayed.
     */
    public function getCalendarDates($format = self::DATE_FORMAT)
    {
        $dates = [];

        $currentDate = clone $this->config['startDate'];
        for ($day = 0; $day < $this->config['numDays']; $day++) {
            if ($this->config['includeWeekends'] || $currentDate->format('N') <= 5) {
                $dates[$currentDate->format($format)] = clone $currentDate;
            }

            $currentDate->add(new \DateInterval('P1D'));
        }

        return $dates;
    }

    /**
     * Retrieve the values labels for the calendar times to be displayed. At the moment, only hours are supported.
     *
     * @see    http://php.net/manual/en/datetime.construct.php
     * @param  string $format
     *  PHP `date` format the times to be displayed.
     * @return string[]
     *  Array of the calendar times to be displayed.
     *  `[YYYY-mm-dd][hour][minute] => [current time formatted, next time formatted]`
     */
    public function getCalendarTimes($format = self::TIME_FORMAT)
    {
        $times = [];
        $interval = $this->config['interval'];
        for ($hour = $this->config['startHour'], $minute = 0, $nextHour = $this->config['startHour'], $nextMinute = 0;
            $hour < $this->config['endHour'];
            $hour = $nextHour, $minute = $nextMinute) {
            $nextHour = $hour + floor(($minute + $interval) / 60);
            $nextMinute = ($minute + $interval) % 60;

            if (!isset($times[$hour])) {
                $times[$hour] = [];
            }

            $times[$hour][$minute] = [
                \date($format, \strtotime($hour .':' . $minute)),
                \date($format, \strtotime($nextHour .':' . $nextMinute)), ];
        }

        return $times;
    }

    /**
     * Determine if we are available at a given time.
     *
     * @param  string $date
     *  Date to check if available in YYYY-mm-dd format.
     * @param  string $hour
     *  Hour to check if available.
     * @param  string $minute
     *  Minute to check if available.
     * @return boolean
     *  `true` if available, false otherwise.
     * @throws \OutOfBoundsException
     *  If the queried date or time is out of the given calendar range.
     * @throws \BadFunctionCallException
     *  If the calendar hasn't been fetched yet.
     */
    public function isFree($date, $hour, $minute = 0)
    {
        if (!$this->offsetExists($date)) {
            throw new \OutOfBoundsException('Date tested for availbility must be in calendar range');
        }
        
        $date = $this->offsetGet($date);
        if (!isset($date[$hour][$minute])) {
            throw new \OutOfBoundsException('Time tested for availbility must be in calendar range');
        }

        return $date[$hour][$minute];
    }

    /**
     * Merge availability data. If busy, will remain busy.
     *
     * @param \MPorcheron\FreeBusyCal\FreeBusyCalendar $cal
     *     Availability matrix to merge.
     */
    public function merge(MPorcheron\FreeBusyCal\FreeBusyCalendar $cal)
    {
        $this->doMerge($cal->getArrayCopy());
    }

    /**
     * Merge availability data. If busy, will remain busy.
     *
     * @param mixed[] $arr
     *     Array of availability to merge.
     */
    private function doMerge(array &$arr)
    {
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $this->doMerge($value);
            } else {
                $existing = $this->offsetExists($key) ? $this->offsetGet($key) : 0;
                $this->offsetSet($key, max($existing, $value));
            }
        }
    }
}
