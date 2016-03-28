<?php

namespace Porcheron\FreeBusyCal;

use \Sabre\VObject\Reader;
use \Sabre\VObject\FreeBusyGenerator;

/**
 * FreeBusyCal controller.
 *
 * @author Martin Porcheron <martin@porcheron.uk>
 * @copyright (c) Martin Porcheron 2016.
 * @license MIT Licence
 */

class FreeBusyCal
{
    
    /**
     * @var Calendar[] Array of calendars to scrape for data.
     */
    private $calendars = [];

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
    const TIME_FORMAT = 'ga';

    /**
     * Construct the controller and populate it with the configuration values. Constructing this class sets the time
     * limit for script execution to indefinite and the default timezone because of a PHP oddity.
     */
    public function __construct(Calendar &...$cal)
    {
        include_once 'lib/awl/CalendarInfo.php';
        include_once 'lib/awl/CalDAVClient.php';

        \set_time_limit(0);
        \date_default_timezone_set('Europe/London');

        $this->calendars = \func_get_args();

        $this->config  = [
            'numDays' => 14,
            'startDate' => new \DateTime('Monday this week'),
            'endDate' => new \DateTime('Sunday this week'),
            'startHour' => 9,
            'endHour' => 17,
            'daysOfTheWeek' => [ 'M', 'T', 'W', 'T', 'F', 'S', 'S' ],
            'weeksPerRow' => 2,
            'includeWeekends' => false];
    }

    /**
     * Add a calendar to extract data from.
     *
     * @param Porcheron\FreeBusyCal\Calendar $cal Calendar to also extact data from.
     */
    public function addCalendar(Calendar &$cal) {
        $this->calendars[] = $cal;
    }

    /**
     * Set the date range to generate data for.
     *
     * @see http://php.net/manual/en/datetime.construct.php for valid strings for the start date.
     * @param \DateTime $startDate When to start the calendar from.
     * @param int $length Number of days from the start date to generate calendar for.
     * @param boolean $includeWeekends Set to false to ignore weekends. Note weekennds still count in the number of
     *  of days (i.e. 7 days, and {@code $includeWeekends} starting on a Monday, will show Mon - Fri.)
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
     */
    public function setDateRange(\DateTime &$startDate, $numDays = 7, $includeWeekends = false)
    {
        $this->config['startDate'] = $startDate;
        $this->config['endDate'] = clone $startDate;

        $this->config['numDays']= \filter_var(
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
     * @param string $mon Label for Monday.
     * @param string $tues Label for Tuesday.
     * @param string $wed Label for Wednesday.
     * @param string $thurs Label for Thurs.
     * @param string $fri Label for Friday.
     * @param string $sat Label for Saturday.
     * @param string $sun Label for Sunday.
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
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
     * @param int $weeksPerRow Number of weeks to show horizontally.
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
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
     * @param int $startHour First hour of the day to start output at (midnight = 0).
     * @param int $endHour Last hour of the day print output for (midnight = 0).
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
     */
    public function setTimeRange($startHour, $endHour)
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
            ['options' => ['default' => 9, 'min_range' => $startHour, 'max_range' => $startHour + 1]]
        );
        $this->config['endHour'] = $endHour;

        return $this;
    }

    /**
     * Fetch and process the data needed to generate the availability calendar.
     *
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
     */
    public function fetch()
    {
        $this->cachedCalendarData = null;
        $this->cachedCalendarDays = null;
        $this->cachedCalendarDates = [];
        $this->cachedCalendarTimes = [];

        $contents = '';
        foreach ($this->calendars as $cal) {
            $dav = new \CalDAVClient(
                $cal->url,
                $cal->username,
                $cal->password,
                '',
                ($cal->ssl ? 'ssl://' : '') . $cal->host,
                $cal->port
            );
            
            $events = $dav->GetEvents(
                $this->config['startDate']->format('Ymd\THis\Z'),
                $this->config['endDate']->format('Ymd\THis\Z')
            );
            foreach ($events as $event) {
                $contents .= $event['data'] ."\n";
            }
        }

        $contents = \preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $contents);
        $contents = "BEGIN:VCALENDAR\n" . \preg_replace("/(BEGIN|END):VCALENDAR\n/", "", $contents) . "END:VCALENDAR" ;
        $contents = \preg_replace("/\n\s/", "", $contents);

        // Process calendars
        $vcal = Reader::read($contents, Reader::OPTION_FORGIVING|Reader::OPTION_IGNORE_INVALID_LINES);
        $fbGenerator = new FreeBusyGenerator($this->config['startDate'], $this->config['endDate'], $vcal);
        $cmnts = $fbGenerator->getResult()->getComponents();
        $fb = $cmnts[0];

        // Mark every time as free/busy
        $calendar = [];
        $currentTime = clone $this->config['startDate'];
        for ($day = 0; $day < $this->config['numDays']; $day++) {
            if ($this->config['includeWeekends'] || $currentTime->format('N') <= 5) {
                $nextTime = clone $currentTime;

                $dayKey = $currentTime->format('Y-m-d');
                $calendar[$dayKey] = [];

                for ($hour = $this->config['startHour']; $hour < $this->config['endHour']; $hour++) {
                    $currentTime->setTime($hour, 0, 0);
                    $nextTime->setTime($hour + 1, 0, 0);

                    $calendar[$dayKey][$currentTime->format('G')] = [0 => $fb->isFree($currentTime, $nextTime)];
                }
            }

            $currentTime->add(new \DateInterval('P1D'));
        }

        $this->cachedCalendarData = $calendar;
        return $this;
    }

    /**
     * Retrieve the labels for the calendar days to be displayed.
     *
     * @return string[] Array of the calendar days to be displayed.
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
     * @param string $format Format the dates to be displayed.
     * @return string[] Array of the calendar dates to be displayed.
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
     * @param string $format Format the times to be displayed.
     * @return string[] Array of the calendar times to be displayed.
     */
    public function getCalendarTimes($format = self::TIME_FORMAT)
    {
        if (!empty($this->cachedCalendarTimes) && isset($this->cachedCalendarTimes[$format])) {
            return $this->cachedCalendarTimes[$format];
        }

        $times = [];
        for ($hour = $this->config['startHour']; $hour < $this->config['endHour']; $hour++) {
            $times[$hour] = [0 => \date($format, \strtotime($hour .':00'))];
        }

        $this->cachedCalendarTimes[$format] = $times;
        return $times;
    }

    /**
     * Retrieve the availability for the calendar times to be displayed.
     *
     * @return boolean[] Array of the calendar availability to be displayed.
     */
    public function getCalendarData()
    {
        return $this->cachedCalendarData;
    }

    /**
     * Determine if we are available at a given time.
     *
     * @param string $date Date to check if available in YYYY-mm-dd format.
     * @param string $hour Hour to check if available.
     * @param string $minute Minute to check if available. Not supported for any value other than zero.
     * @return boolean {@code true} if available, false otherwise.
     * @throws \OutOfBoundsException if the queried date or time is out of the given calendar range.
     * @throws \BadFunctionCallException if the calendar hasn't been fetched yet.
     */
    public function isFree($date, $hour, $minute = 0)
    {
        if (\is_null($this->cachedCalendarData)) {
            throw new \BadFunctionCallException('Must call FreeBusyCal::fetch() before querying availability');
        }

        if (!isset($this->cachedCalendarData[$date][$hour][$minute])) {
            print_r($this->cachedCalendarData);
            throw new \OutOfBoundsException('Time tested for availbility must be in calendar range');
        }

        return $this->cachedCalendarData[$date][$hour][$minute];
    }

    /**
     * Output the processed calendar data.
     *
     * @param string $dateFormat Format the dates to be displayed.
     * @param string $timeFormat Format the times to be displayed.
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
     */
    public function printTable(
        $tableAttrs = '',
        $dateFormat = self::DATE_FORMAT,
        $timeFormat = self::TIME_FORMAT,
        $freeText = 'Free',
        $busyText = 'Busy'
    ) {
        echo '<table '. $tableAttrs .'>';

        // Output table headers with days
        echo '<tr><th></th>';
        foreach ($this->getCalendarDays() as $day) {
            echo '<th class="day">'. $day .'</th>';
        }
        echo '</tr>';

        // Output table headers with dates
        echo '<tr><th></th>';
        foreach ($this->getCalendarDates($dateFormat) as $label => &$dt) {
            echo '<th class="date">'. $label .'</th>';
        }
        echo '</tr>';

        // Iterate through each time and print the availability
        $times = $this->getCalendarTimes($timeFormat);
        foreach ($times as $hour => $temp) {
            foreach ($temp as $minute => $label) {
                echo '<tr><td class="time">'. $label .'</td>';
                foreach ($this->getCalendarDates($dateFormat) as $dt) {
                    if ($this->isFree($dt->format('Y-m-d'), $hour, $minute)) {
                        print '<td class="avail free"><span>'.
                            \filter_var($freeText, FILTER_SANITIZE_STRING) .'</span></td>';
                    } else {
                        print '<td class="avail busy"><span>'.
                            \filter_var($busyText, FILTER_SANITIZE_STRING) .'</span></td>';
                    }
                }
            }
            echo '</td>';
        }
      
        echo '</table>';
    }
}
