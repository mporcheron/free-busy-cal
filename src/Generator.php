<?php

/**
 * This file is the main FreeBusyCal generator used to retrieve and generate a free/busy calendar. See the class
 * description for example usage.
 *
 * @package MPorcheron\FreeBusyCal
 */

namespace MPorcheron\FreeBusyCal;

/**
 * FreeBusyCal generator - this is the main class used to generate a free busy calendar.
 *
 * Create a calendar configuration:
 * 
 *      $cal = new \MPorcheron\FreeBusyCal\UserCalendar()
 *          ->setUsername('ad\username')
 *          ->setPassword('password')
 *          ->setUrl('https://caldav.example.com:8443/users/username@example.com/calendar');
 * 
 *
 * Create the Generator object and add the calendar:
 * 
 *      $fbc = new \MPorcheron\FreeBusyCal\Generator($cal);
 * 
 *
 * Set the date range to extract, e.g. start from this Monday, and run for 14 days (i.e. two weeks), but exclude
 * weekends:
 * 
 *      $fbc->setDateRange(new \DateTime('Monday this week'), 14, false);
 * 
 *
 * Only generate a calendar between 9am (inclusive) and 5pm (exclusive), and show a slot every 30 minutes:
 * 
 *      $fbc->setTimeRange(9, 17, 30);
 * 
 *
 * Fetch the calendars and process them:
 * 
 *      $fbc->fetchAndParse();
 * 
 *
 * Print out the calendar table, with the class `cal`, default date and time formats, the labels `Free` and `Busy` for
 *  slots, and show times as ranges (i.e. start – end) as opposed to just start time:
 * 
 *     $contents = $fbc->generate(function (Fbc\FreeBusyCalendar &$cal) {
 *         $output = '<table class="cal">';
 *
 *         // Output table headers with days
 *         $output .= '<tr><th></th>';
 *         $days = [ 'S', 'M', 'T', 'W', 'T', 'F', 'S' ];
 *         foreach ($cal->getCalendarDates(Fbc\FreeBusyCalendar::DATE_FORMAT) as $label => &$dt) {
 *             $output .= '<th class="day">'. $days[$dt->format('N')] .'</th>';
 *         }
 *         $output .= '</tr>';
 *
 *         // Output table headers with dates
 *         $output .= '<tr><th></th>';
 *         foreach ($cal->getCalendarDates(Fbc\FreeBusyCalendar::DATE_FORMAT) as $label => &$dt) {
 *             $output .= '<th class="date">'. $label .'</th>';
 *         }
 *         $output .= '</tr>';
 *
 *         // Iterate through each time and $output .= the availability
 *         $times = $cal->getCalendarTimes(Fbc\FreeBusyCalendar::TIME_FORMAT);
 *         foreach ($times as $hour => $temp) {
 *             foreach ($temp as $minute => $labels) {
 *                 $output .= '<tr><td class="time">'. $labels[0];
 *                 if ($showRange) {
 *                     $output .= '&nbsp;&ndash;&nbsp;' . $labels[1];
 *                 }
 *                 $output .= '</td>';
 *
 *                 foreach ($cal->getCalendarDates(Fbc\FreeBusyCalendar::DATE_FORMAT) as $dt) {
 *                     if ($cal->isFree($dt->format('Y-m-d'), $hour, $minute)) {
 *                         $output .= '<td class="avail free">Free</td>';
 *                     } else {
 *                         $output .= '<td class="avail busy">Busy</td>';
 *                     }
 *                 }
 *             }
 *             $output .= '</td>';
 *         }
 *         $output .= '</table>';
 *
 *         return $output;
 *     });
 *
 * Alternatively test if a specific time/date (i.e. 5pm on 4th May 2016) is available:
 * 
 *      $cal = $fbc->getFreeBusyCalendar();
 *      $free = $cal->isFree('2016-05-04', 17, 0);
 * 
 *
 * @author    Martin Porcheron <martin@porcheron.uk>
 * @copyright (c) Martin Porcheron 2016.
 * @license   MIT Licence
 */

class Generator
{
    
    /**
     * @var \MPorcheron\FreeBusyCal\UserCalendar[] Array of calendars to scrape for data.
     */
    private $calendars;

    /**
     * @var mixed[] Configuration data.
     */
    private $config;

    /**
     * @var \MPorcheron\FreeBusyCal\FreeBusyCalendar Cached calendar data.
     */
    private $freeBusyCalendar = null;

    /**
     * Construct the controller and populate it with the configuration values. Constructing this class sets the time
     * limit for script execution to indefinite and the default timezone because of a PHP oddity.
     *
     * Output buffering is started also.
     *
     * @param \MPorcheron\FreeBusyCal\UserCalendar $cal
     *  (One or more) calendars to extract data from.
     */
    public function __construct(&$cal)
    {
        \set_time_limit(0);
        \date_default_timezone_set('Europe/London');

        $this->calendars = \func_get_args();

        $this->config  = [
            'numDays' => 14,
            'startDate' => new \DateTime('Monday this week'),
            'endDate' => new \DateTime('Sunday this week'),
            'startHour' => 9,
            'endHour' => 17,
            'interval' => 60,
            'includeWeekends' => false];
    }

    /**
     * Get the congiruation data.
     *
     * @return mixed[]
     *  Configuration data.
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Add a calendar to extract data from.
     *
     * @param \MPorcheron\FreeBusyCal\UserCalendar $cal
     *  Calendar to also extact data from.
     */
    public function addCalendar(Calendar &$cal)
    {
        $this->calendars[] = $cal;
    }

    /**
     * Set the date range to generate data for.
     *
     * @see    http://php.net/manual/en/datetime.construct.php
     * @param  \DateTime $startDate
     *  When to start the calendar from.
     * @param  int       $length
     *  Number of days from the start date to generate calendar for.
     * @param  boolean   $includeWeekends
     *  Set to false to ignore weekends. Note weekennds still count in the number of days (i.e. 7 days, and
     *  `$includeWeekends` starting on a Monday, will show Mon - Fri.)
     * @return \MPorcheron\FreeBusyCal\UserCalendar
     *  `$this`.
     */
    public function setDateRange(\DateTime &$startDate, $numDays = 7, $includeWeekends = false)
    {
        $this->config['startDate'] = $startDate;
        $this->config['endDate'] = clone $startDate;

        $this->config['numDays'] = \filter_var(
            $numDays,
            FILTER_SANITIZE_NUMBER_INT,
            ['options' => ['default' => 7, 'min_range' => 1]]
        );
        $this->config['endDate']->add(new \DateInterval('P' . $this->config['numDays'] . 'D'));

        $this->config['includeWeekends'] = \filter_var($includeWeekends, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Set the time range to generate data for.
     *
     * @see    http://php.net/manual/en/datetime.construct.php
     * @param  int $startHour
     *  First hour of the day to start output at (midnight = `0`). Minimum is `1`, maximum is `22`, default is `9`.
     * @param  int $endHour
     *  Last hour of the day print output for (midnight = 0). Minimum is `$startHour`, maximum is `23`, default is `17`.
     * @param  int $interval
     *  How many mniutes to break each slot in the calendar up by (60 = segment by hour). You should make this number
     *  one of the following to fit evenly into the hour: 1,2,3,4,5,6,10,12,15,20,30,60. Minumum is `1`, maximum is
     *  `60`, default is `60`.
     * @return \MPorcheron\FreeBusyCal\Generator
     *  `$this`.
     */
    public function setTimeRange($startHour, $endHour, $interval = 60)
    {
        $startHour = \filter_var(
            $startHour,
            FILTER_SANITIZE_NUMBER_INT,
            ['options' => ['default' => 9, 'min_range' => 1, 'max_range' => 22]]
        );
        $this->config['startHour'] = $startHour;

        $endHour = \filter_var(
            $endHour,
            FILTER_SANITIZE_NUMBER_INT,
            ['options' => ['default' => 17, 'min_range' => $startHour, 'max_range' => 23]]
        );
        $this->config['endHour'] = $endHour;

        $interval = \filter_var(
            $interval,
            FILTER_SANITIZE_NUMBER_INT,
            ['options' => ['default' => 60, 'min_range' => 1, 'max_range' => 60]]
        );
        $this->config['interval'] = $interval;

        return $this;
    }

    /**
     * Fetch and parse the data needed to generate the availability calendar.
     *
     * @param  boolean $refetch
     *  Refetch iCal data if it has already been fetched once.
     * @return \MPorcheron\FreeBusyCal\Generator
     *  `$this`.
     */
    public function fetchAndParse($refetch = false)
    {
        // Clear object data caches
        $this->freeBusyCalendar = null;

        // Fetch data from the CalDAV server
        $freeBusyCalendar = null;
        foreach ($this->calendars as $cal) {
            if (is_null($freeBusyCalendar)) {
                $freeBusyCalendar = $cal->fetch($refetch)->parse($this->config);
            } else {
                $freeBusyCalendar->merge($cal->fetch($refetch)->parse($this->config));
            }
        }

        $this->freeBusyCalendar = $freeBusyCalendar->setConfig($this->config);

        return $this;
    }

    /**
     * Generates the calendar and returs output.
     *
     * @param  function $func
     *  Function that takes a single paramter of a \MPorcheron\FreeBusyCal\FreeBusyCalendar` and returns out the output 
     *  as a string.
     * @return \MPorcheron\FreeBusyCal\Generator
     *  Output from the print function.
     * @throws \BadFunctionCallException
     *  If the calendar hasn't been fetched yet.
     */
    public function generate($func)
    {
        if (\is_null($this->freeBusyCalendar)) {
            throw new \BadFunctionCallException('Must call Generator::fetchAndParse() before querying availability');
        }

        return $func($this->freeBusyCalendar);
    }

    /**
     * Retrieve the calendar of availability.
     *
     * @return \MPorcheron\FreeBusyCal\FreeBusyCalendar
     *  Calendar availability.
     * @throws \BadFunctionCallException
     *  If the calendar hasn't been fetched yet.
     */
    public function getFreeBusyCalendar()
    {
        if (\is_null($this->freeBusyCalendar)) {
            throw new \BadFunctionCallException('Must call Generator::fetchAndParse() before querying availability');
        }

        return $this->freeBusyCalendar;
    }
}
