<?php

/**
 * File for a calendar configuration and data.
 *
 * @package MPorcheron\FreeBusyCal
 */

namespace MPorcheron\FreeBusyCal;

use Sabre\VObject\Reader;
use Sabre\VObject\FreeBusyGenerator;

/**
 * Container for a calendar's configuration and data, this class handles a specific calendar source's configuration
 * and event data.
 *
 * @author    Martin Porcheron <martin@porcheron.uk>
 * @copyright (c) Martin Porcheron 2016.
 * @license   MIT Licence
 */

class UserCalendar extends \ArrayObject
{
    /**
     * @var string iCal file.
     */
    private $iCal = null;

    /**
     * @var mixed[] Configuration data.
     */
    private $data;

    /**
     * Create the calendar configuration with the default values.
     */
    public function __construct()
    {
        parent::__construct([], \ArrayObject::ARRAY_AS_PROPS);

        $this->offsetSet('username', '');
        $this->offsetSet('password', '');
        $this->offsetSet('uri', '');
        $this->offsetSet('hostname', '');
        $this->offsetSet('port', 80);
        $this->offsetSet('ssl', false);
    }

    /**
     * Set the username to connect with.
     *
     * @param  string $username
     *  New username to login with. Leave blank to disable.
     * @return \MPorcheron\FreeBusyCal\UserCalendar
     *  `$this`.
     */
    public function setUsername($username = '')
    {
        $this->offsetSet('username', $username);
        return $this;
    }

    /**
     * Set the password to connect with.
     *
     * @param  string $password
     *  New password to login with. Leave blank to disable.
     * @return \MPorcheron\FreeBusyCal\UserCalendar
     *  `$this`.
     */
    public function setPassword($password = '')
    {
        $this->offsetSet('password', $password);
        return $this;
    }

    /**
     * Set the URL to connect to.
     *
     * @param  string $url
     *  New URL to connect to.
     * @return \MPorcheron\FreeBusyCal\UserCalendar
     *  `$this`.
     */
    public function setUrl($url)
    {
        $this->offsetSet('url', \filter_var($url, FILTER_SANITIZE_URL));
        $components = \parse_url($this->offsetGet('url'));

        if (isset($components['scheme']) && $components['scheme'] == 'https'
            || $components['scheme'] == 'ssl' || $components['scheme'] == 'tls'
        ) {
            $this->offsetSet('ssl', true);
        }

        if (isset($components['hostname'])) {
            $this->offsetSet('host', $components['hostname']);
        } elseif (isset($components['host'])) {
            $this->offsetSet('host', $components['host']);
        }

        if (isset($components['port'])) {
            $this->offsetSet('port', $components['port']);
        }

        return $this;
    }

    /**
     * Connect with SSL?
     *
     * @param  boolean $ssl
     *  Set to {@code true} to connect with SSL.
     * @return \MPorcheron\FreeBusyCal\UserCalendar
     *  `$this`.
     */
    public function enableSsl($ssl)
    {
        $this->offsetSet('ssl', \filter_var($ssl, FILTER_VALIDATE_BOOLEAN));
        return $this;
    }

    /**
     * Set the iCal file to parsed. Note: no validation occurs!
     *
     * @param  string $iCal
     *  iCal file to be parsed.
     * @return \MPorcheron\FreeBusyCal\UserCalendar
     *  `$this`.
     * @throws InvalidArgumentException
     *  If the passed file does not exist or is not readable.
     */
    public function setiCalFile($iCal)
    {
        if (!\is_readable($iCal)) {
            throw new \InvalidArgumentException('The file "'. $iCal .'" does not exist or is not readable');
        }

        $this->iCal = \file_get_contents($iCal);
        return $this;
    }

    /**
     * Set the iCal data to parsed. Note: no validation occurs!
     *
     * @param  string $iCal
     *  iCal data to be parsed.
     * @return \MPorcheron\FreeBusyCal\UserCalendar
     *  `$this`.
     */
    public function setiCal($iCal)
    {
        $this->iCal = $iCal;
        return $this;
    }

    /**
     * Fetch the data needed to generate the availability calendar. If the iCal
     * data is already set, fetching is skipped unless {@code $refetch} is {@code true}
     *
     * @param  boolean $refetch
     *  Refetch iCal data if it has already been fetched once.
     * @return \MPorcheron\FreeBusyCal\UserCalendar
     *  `$this`.
     */
    public function fetch($refetch = false)
    {
        if (!$refetch && $this->iCal != null) {
            return $this;
        }

        include_once 'lib/awl/CalendarInfo.php';
        include_once 'lib/awl/CalDAVClient.php';

        $dav = new \CalDAVClient(
            $this->url,
            $this->username,
            $this->password,
            '',
            ($this->ssl ? 'ssl://' : '') . $this->host,
            $this->port
        );
        
        $events = $dav->GetEvents(
            // FIXME: commenting this is the only way to resolve recurring events
            // not being included in the events list; would like to actually solve
            //    $config['startDate']->format('Ymd\THis\Z'),
            //   $config['endDate']->format('Ymd\THis\Z')
        );

        $contents = '';
        foreach ($events as $event) {
            $contents .= $event['data'] ."\n";
        }

        $contents = \preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $contents);
        $contents = "BEGIN:VCALENDAR\n" . \preg_replace("/(BEGIN|END):VCALENDAR\n/", "", $contents) . "END:VCALENDAR";
        $this->iCal = \preg_replace("/\n\s/", "", $contents);

        return $this;
    }

    /**
     * Parse the iCal file needed to generate the availability calendar.
     *
     * @param  mixed[] $config
     *  Configuration data.
     * @return \MPorcheron\FreeBusyCal\FreeBusyCalendar
     *  Availability for the calendar.
     * @throws \BadFunctionCallException
     *  If the iCal data hasn't been fetched/set yet.
     */
    public function parse(array $config)
    {
        if (\is_null($this->iCal)) {
            throw new \BadFunctionCallException('Must call UserCalendar::fetch(), UserCalendar::setiCal(), or ' .
                'UserCalendar::setiCalFile() before parsing iCal');
        }

        $vcal = Reader::read($this->iCal, Reader::OPTION_FORGIVING|Reader::OPTION_IGNORE_INVALID_LINES);
        $fbGenerator = new FreeBusyGenerator($config['startDate'], $config['endDate'], $vcal);
        $cmnts = $fbGenerator->getResult()->getComponents();
        $fb = $cmnts[0];

        // Mark every time as free/busy
        $calendar = [];
        $currentTime = clone $config['startDate'];
        $startHour = $config['startHour'];
        $interval = $config['interval'];
        $endHour = $config['endHour'];
        for ($day = 0; $day < $config['numDays']; $day++) {
            if ($config['includeWeekends'] || $currentTime->format('N') <= 5) {
                $nextTime = clone $currentTime;

                $dayKey = $currentTime->format('Y-m-d');
                $calendar[$dayKey] = [];

                for ($hour = $startHour, $minute = 0, $hourDelta = 0, $nextMinute = 0;
                    $hour < $endHour;
                    $hour += $hourDelta, $minute = $nextMinute) {
                    $hourDelta = floor(($minute + $interval) / 60);
                    $nextMinute = ($minute + $interval) % 60;

                    $currentTime->setTime($hour, $minute, 0);
                    $nextTime->setTime($hour + $hourDelta, $nextMinute, 0);

                    if (!isset($calendar[$dayKey][$currentTime->format('G')])) {
                        $calendar[$dayKey][$currentTime->format('G')] = [];
                    }
                    
                    $calendar[$dayKey][$currentTime->format('G')][$minute] = $fb->isFree($currentTime, $nextTime);
                }
            }

            $currentTime->add(new \DateInterval('P1D'));
        }

        return new FreeBusyCalendar($calendar);
    }
}
