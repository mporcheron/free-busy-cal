<?php

namespace Porcheron\FreeBusyCal;

include 'lib/awl/CalendarInfo.php';
include 'lib/awl/CalDAVClient.php';

/**
 * FreeBusyCal controller.
 *
 * @author Martin Porcheron <martin@porcheron.uk>
 * @copyright (c) Martin Porcheron 2016.
 * @license MIT Licence
 */

class FreeBusyCal {
    
    /**
     * @var Calendar[] Array of calendars to scrape for data.
     */
    private $calendars = [];

    /**
     * @var mixed[] Configuration data.
     */
    private $config;

    /**
     * Construct the controller and populate it with the configuration values. Constructing this class sets the time 
     * limit for script execution to indefinite and the default timezone because of a PHP oddity.
     */
    public function __construct(Calendar &...$cal) {
        \set_time_limit (0);
        \date_default_timezone_set('Europe/London');

        $this->calendars = \func_get_args();

        $this->config  = [
            'numDays' => 7,
            'startDate' => new \DateTime('Monday this week'),
            'endDate' => new \DateTime('Sunday this week'),
            'daysOfTheWeek' => [ 'M', 'T', 'W', 'T', 'F', 'S', 'S' ],
            'startHour' => 9,
            'endHour' => 17,
            'includeWeekends' => false];
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
    public function setDateRange(\DateTime &$startDate, $length = 7, $includeWeekends = false) {
        $this->config['startDate'] = $startDate;

        $this->config['endDate'] = clone $startDate;
        $length = \filter_var($length, FILTER_VALIDATE_INT, ['options' => ['default' => 7, 'min_range' => 1]]);
        $this->config['endDate']->add(new \DateInterval('P' . $length . 'D'));

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
    public function setDayLabels($mon, $tues, $wed, $thurs, $fri, $sat, $sun) {
        $this->config['daysOfTheWeek'] = [$mon, $tues, $wed, $thurs, $fri, $sat, $sun];
        return $this;
    }

    /**
     * Set the time range to generate data for.
     *
     * @param int $startHour First hour of the day to start output at (midnight = 0).
     * @param int $endHour Last hour of the day print output for (midnight = 0).
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
     */
    public function setTimeRange($startHour, $endHour) {
        $startHour = \filter_var($startHour, FILTER_VALIDATE_INT, 
            ['options' => ['default' => 9, 'min_range' => 1, 'max_range' => 22]]);
        $this->config['startHour'] = $startHour;

        $endHour = \filter_var($endHour, FILTER_VALIDATE_INT, 
            ['options' => ['default' => 9, 'min_range' => $startHour, 'max_range' => $startHour + 1]]);
        $this->config['endHour'] = $endHour;

        return $this;
    }

    /**
     * Fetch the data needed to generate the availability calendar.
     */
    public function fetch() {
        $contents = '';
        foreach ($this->calendars as $cal) {
            $dav = new \CalDAVClient ($cal->url, $cal->username, $cal->password, '',
                ($cal->ssl ? 'ssl://' : '') . $cal->host, $cal->port);
            $events = $dav->GetEvents($this->config['startDate']->format('Ymd\THis\Z'),
                $this->config['endDate']->format('Ymd\THis\Z'));
            print_r($dav);
            // foreach ($dav->GetEvents () as $event) {
            //     $contents .= $event['data'] ."\n";
            // }
        }

    }
}