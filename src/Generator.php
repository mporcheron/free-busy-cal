<?php

/**
 * This file is the main FreeBusyCal generator used to retrieve and generate a free/busy calendar. See the class
 * description for example usage.
 *
 * @package MPorcheron\FreeBusyCal
 */

namespace MPorcheron\FreeBusyCal;

/**
 * FreeBusyCal generator.
 *
 * Create a calendar configuration:
 * <code>
 * $cal = (new MPorcheron\FreeBusy\Calendar())
 *   ->setUsername('ad\username')
 *   ->setPassword('password')
 *   ->setUrl('https://caldav.example.com:8443/users/username@example.com/calendar');
 * </code>
 *
 * Create the Generator object and add the calendar:
 * <code>
 * $fbc = (new MPorcheron\FreeBusyCal\Generator($cal));
 * </code>
 *
 * Set the date range to extract, e.g. start from this Monday, and run for 14 days (i.e. two weeks), but exclude
 * weekends:
 * <code>
 * $fbc->setDateRange(new \DateTime('Monday this week'), 14, false);
 * </code>
 *
 * Only generate a calendar between 9am (inclusive) and 5pm (exclusive), and show a slot every 30 minutes:
 * <code>
 * $fbc->setTimeRange(9, 17, 30);
 * </code>
 *
 * Label the days in our output (optional, depends on if you use the built in output):
 * <code>
 * $fbc->setDayLabels('M', 'T', 'W', 'T', 'F', 'S', 'S');
 * </code>
 *
 * Show two weeks horizontally (optional, depends on if you use the built in output):
 * <code>
 * $fbc->setWeeksPerRow(2);
 * </code>
 *
 * Fetch the calendars and process them:
 * <code>
 * $fbc->->fetch();
 * </code>
 *
 * Print out the calendar table, with the class `cal`, default date and time formats, the labels `Free` and `Busy` for
 *  slots, and show times as ranges (i.e. start – end) as opposed to just start time:
 * <code>
 * echo $fbc->getTable('class="cal"',
 *  MPorcheron\FreeBusyCal\Generator::DATE_FORMAT,
 *  MPorcheron\FreeBusyCal\Generator::TIME_FORMAT,
 *  'Free',
 *  'Busy',
 *  true);
 * </code>
 *
 * Alternatively test if a specific time/date (i.e. 5pm on 4th May 2016) is available:
 * <code>
 * $free = $fbc->isFree('2016-05-04', 17, 0);
 * </code>
 *
 * @author Martin Porcheron <martin@porcheron.uk>
 * @copyright (c) Martin Porcheron 2016.
 * @license MIT Licence
 */

class Generator
{
    
    /**
     * @var MPorcheron\FreeBusyCal\Calendar[] Array of calendars to scrape for data.
     */
    private $calendars;

    /**
     * @var mixed[] Configuration data.
     */
    private $config;

    /**
     * @var mixed[] Cached calendar data.
     */
    private $cachedCalendarData = null;

    /**
     * @var mixed[] Cached calendar days.
     */
    private $cachedCalendarDays = null;

    /**
     * @var mixed[] Cached calendar dates.
     */
    private $cachedCalendarDates = [];

    /**
     * @var mixed[] Cached calendar times.
     */
    private $cachedCalendarTimes = [];

    /**
     * @var string Default date format.
     */
    const DATE_FORMAT = 'j/n';

    /**
     * @var string Default time format.
     */
    const TIME_FORMAT = 'G:i';

    /**
     * Construct the controller and populate it with the configuration values. Constructing this class sets the time
     * limit for script execution to indefinite and the default timezone because of a PHP oddity.
     *
     * @param MPorcheron\FreeBusyCal\Calendar $cal,...
     *  Calendars to extract data from.
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
            'daysOfTheWeek' => [ 'M', 'T', 'W', 'T', 'F', 'S', 'S' ],
            'weeksPerRow' => 2,
            'includeWeekends' => false];
    }

    /**
     * Get the congiruation data.
     * 
     * @return mixed[]
     *  Configuration data.
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * Add a calendar to extract data from.
     *
     * @param MPorcheron\FreeBusyCal\Calendar $cal
     *  Calendar to also extact data from.
     */
    public function addCalendar(Calendar &$cal)
    {
        $this->calendars[] = $cal;
    }

    /**
     * Set the date range to generate data for.
     *
     * @see http://php.net/manual/en/datetime.construct.php
     * @param \DateTime $startDate
     *  When to start the calendar from.
     * @param int $length
     *  Number of days from the start date to generate calendar for.
     * @param boolean $includeWeekends
     *  Set to false to ignore weekends. Note weekennds still count in the number of days (i.e. 7 days, and
     *  `$includeWeekends` starting on a Monday, will show Mon - Fri.)
     * @return MPorcheron\FreeBusyCal\Calendar
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
     * Set the labels for each day.
     *
     * @param string $mon
     *  Label for Monday.
     * @param string $tues
     *  Label for Tuesday.
     * @param string $wed
     *  Label for Wednesday.
     * @param string $thurs
     *  Label for Thurs.
     * @param string $fri
     *  Label for Friday.
     * @param string $sat
     *  Label for Saturday.
     * @param string $sun
     *  Label for Sunday.
     * @return MPorcheron\FreeBusyCal\Generator
     *  `$this`.
     */
    public function setDayLabels($mon, $tues, $wed, $thurs, $fri, $sat, $sun)
    {
        $arr = [];
        foreach (\func_get_args() as $label) {
            $labels[] = \filter_var($label, FILTER_SANITIZE_STRING);
        }
        $this->config['daysOfTheWeek'] = $labels;
        return $this;
    }

    /**
     * Number of weeks to show per calendar row.
     *
     * @param int $weeksPerRow
     *  Number of weeks to show horizontally.
     * @return MPorcheron\FreeBusyCal\Generator
     *  `$this`.
     */
    public function setWeeksPerRow($weeksPerRow)
    {
        $this->config['weeksPerRow'] = \filter_var(
            $weeksPerRow,
            FILTER_SANITIZE_NUMBER_INT,
            ['options' => ['default' => 2, 'min_range' => 1]]
        );
        return $this;
    }

    /**
     * Set the time range to generate data for.
     *
     * @see http://php.net/manual/en/datetime.construct.php
     * @param int $startHour
     *  First hour of the day to start output at (midnight = `0`). Minimum is `1`, maximum is `22`, default is `9`.
     * @param int $endHour
     *  Last hour of the day print output for (midnight = 0). Minimum is `$startHour`, maximum is `23`, default is `17`.
     * @param int $interval
     *  How many mniutes to break each slot in the calendar up by (60 = segment by hour). You should make this number
     *  one of the following to fit evenly into the hour: 1,2,3,4,5,6,10,12,15,20,30,60. Minumum is `1`, maximum is
     *  `60`, default is `60`.
     * @return MPorcheron\FreeBusyCal\Generator
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
     * Fetch and process the data needed to generate the availability calendar.
     *
     * @return MPorcheron\FreeBusyCal\Generator
     *  `$this`.
     */
    public function fetch()
    {
        // Clear object data caches
        $this->cachedCalendarData = null;
        $this->cachedCalendarDays = null;
        $this->cachedCalendarDates = [];
        $this->cachedCalendarTimes = [];

        // Fetch data from the CalDAV server
        $availability = null;
        foreach ($this->calendars as $cal) {
            if (is_null($availability)) {
                $availability = $cal->fetch($this->config);
            } else {
                $availability->merge($cal->fetch($this->config));
            }
        }

        $this->cachedCalendarData = $availability;
        return $this;
    }

    /**
     * Retrieve the labels for the calendar days to be displayed.
     *
     * @return string[]
     *  Array of the calendar days to be displayed.
     */
    public function getCalendarDays()
    {
        if (!is_null($this->cachedCalendarDays)) {
            return $this->cachedCalendarDays;
        }

        $dayOffset = $this->config['startDate']->format('N') - 1;
        $days = [];
        for ($i = 0; $i < $this->config['weeksPerRow'] * 7; $i++) {
            $dayNum = ($i + $dayOffset) % 7;

            if (!$this->config['includeWeekends'] && $dayNum > 4) {
                continue;
            }
            $days[] = $this->config['daysOfTheWeek'][$dayNum];
        }

        $this->cachedCalendarDays = $days;
        return $days;
    }

    /**
     * Retrieve the values labels for the calendar dates to be displayed.
     *
     * @see http://php.net/manual/en/datetime.construct.php
     * @param string $format
     *  PHP `date` format the dates to be displayed.
     * @return string[]
     *  Array of the calendar dates to be displayed.
     */
    public function getCalendarDates($format = self::DATE_FORMAT)
    {
        if (!empty($this->cachedCalendarDates) && isset($this->cachedCalendarDates[$format])) {
            return $this->cachedCalendarDates[$format];
        }

        $dates = [];
        $currentDate = clone $this->config['startDate'];
        for ($day = 0; $day < $this->config['numDays']; $day++) {
            if ($this->config['includeWeekends'] || $currentDate->format('N') <= 5) {
                $dates[$currentDate->format($format)] = clone $currentDate;
            }

            $currentDate->add(new \DateInterval('P1D'));
        }

        $this->cachedCalendarDates[$format] = $dates;
        return $dates;
    }

    /**
     * Retrieve the values labels for the calendar times to be displayed. At the moment, only hours are supported.
     *
     * @see http://php.net/manual/en/datetime.construct.php
     * @param string $format
     *  PHP `date` format the times to be displayed.
     * @return string[]
     *  Array of the calendar times to be displayed.
     *  `[YYYY-mm-dd][hour][minute] => [current time formatted, next time formatted]`
     */
    public function getCalendarTimes($format = self::TIME_FORMAT)
    {
        if (!empty($this->cachedCalendarTimes) && isset($this->cachedCalendarTimes[$format])) {
            return $this->cachedCalendarTimes[$format];
        }

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

        $this->cachedCalendarTimes[$format] = $times;
        return $times;
    }

    /**
     * Retrieve the availability for the calendar times to be displayed.
     *
     * @return boolean[]
     *  Array of the calendar availability to be displayed.
     */
    public function getCalendarData()
    {
        return $this->cachedCalendarData;
    }

    /**
     * Determine if we are available at a given time.
     *
     * @param string $date
     *  Date to check if available in YYYY-mm-dd format.
     * @param string $hour
     *  Hour to check if available.
     * @param string $minute
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
        if (\is_null($this->cachedCalendarData)) {
            throw new \BadFunctionCallException('Must call Generator::fetch() before querying availability');
        }

        if (!isset($this->cachedCalendarData[$date][$hour][$minute])) {
            throw new \OutOfBoundsException('Time tested for availbility must be in calendar range');
        }

        return $this->cachedCalendarData[$date][$hour][$minute];
    }

    /**
     * Retrieve the calendar of availability.
     * 
     * @return MPorcheron\FreeBusyCal\Availability
     *  Calendar availability.
     * @throws \BadFunctionCallException
     *  If the calendar hasn't been fetched yet.
     */
    public function getAvailability()
    {
        if (\is_null($this->cachedCalendarData)) {
            throw new \BadFunctionCallException('Must call Generator::fetch() before querying availability');
        }

        return $this->cachedCalendarData;
    }

    /**
     * Output the processed calendar data.
     *
     * @see http://php.net/manual/en/datetime.construct.php
     * @param string $tableAttrs
     *  HTML attributes for the table object.
     * @param string $dateFormat
     *  PHP `date` format the dates to be displayed.
     * @param string $timeFormat
     *  PHP `date` format the times to be displayed.
     * @param string $freeText
     *  Text for an available slot.
     * @param string $busyText
     *  Text for a busy slot.
     * @param boolean $showRange
     *  Show the start and end time for a slot (if `true`), seperated with two non-breaking spaces and an en-dash.
     * @return string
     *  Calendar of availbility.
     */
    public function getTable(
        $tableAttrs = '',
        $dateFormat = self::DATE_FORMAT,
        $timeFormat = self::TIME_FORMAT,
        $freeText = 'Free',
        $busyText = 'Busy',
        $showRange = false
    ) {
        $table = '<table '. $tableAttrs .'>';

        // Output table headers with days
        $table .= '<tr><th></th>';
        foreach ($this->getCalendarDays() as $day) {
            $table .= '<th class="day">'. $day .'</th>';
        }
        $table .= '</tr>';

        // Output table headers with dates
        $table .= '<tr><th></th>';
        foreach ($this->getCalendarDates($dateFormat) as $label => &$dt) {
            $table .= '<th class="date">'. $label .'</th>';
        }
        $table .= '</tr>';

        // Iterate through each time and print the availability
        $times = $this->getCalendarTimes($timeFormat);
        foreach ($times as $hour => $temp) {
            foreach ($temp as $minute => $labels) {
                $table .= '<tr><td class="time">'. $labels[0];
                if ($showRange) {
                    $table .= '&nbsp;&ndash;&nbsp;' . $labels[1];
                }
                $table .= '</td>';
                
                foreach ($this->getCalendarDates($dateFormat) as $dt) {
                    if ($this->isFree($dt->format('Y-m-d'), $hour, $minute)) {
                        $table .= '<td class="avail free">'. $freeText .'</td>';
                    } else {
                        $table .= '<td class="avail busy">'. $busyText .'</td>';
                    }
                }
            }
            $table .= '</td>';
        }
        $table .= '</table>';

        return $table;
    }
}
