<?php

/**
 * File for a calendar configuration and data.
 *
 * @package MPorcheron\FreeBusyCal
 */

namespace MPorcheron\FreeBusyCal;

use Sabre\DAV\Client as DAVClient;
use Sabre\Xml\Service as XmlService;
use Sabre\VObject\Reader;
use Sabre\VObject\FreeBusyGenerator;

/**
 * Container for a calendar's configuration and data for a calendar from a CalDAV server.
 *
 * @author    Martin Porcheron <martin-fbc@porcheron.uk>
 * @copyright (c) Martin Porcheron 2017.
 * @license   MIT Licence
 */

class CalDAVCalendar extends Calendar
{
    /** @const stinrg Property for the calendar home set PROPFIND */
    const CAL_HOME_SET = '{urn:ietf:params:xml:ns:caldav}calendar-home-set';

    /** @const string Property for the calendar resourcetype */
    const CAL_RESOURCETYPE = '{DAV:}resourcetype';

    /** @const stirng Attrbiute for the calendar getctag property */
    const CAL_GETCTAG = '{http://calserver.org/ns/:}getctag';

    /** @const stirng Resource type for a calendar */
    const CAL_RT_CALENDAR = '{urn:ietf:params:xml:ns:caldav}calendar';

    /** @const string Proprety type for iCal calendar data */
    const CAL_DATA = '{urn:ietf:params:xml:ns:caldav}calendar-data';

    /** @const string XML attribute for propstat */
    const DAV_PROPSTAT = '{DAV:}propstat';

    /** @var Sabre\DAV\Client WebDAV client */
    private $davClient;

    /**
     * Create the calendar configuration with the default values.
     */
    public function __construct()
    {
        parent::__construct([], \ArrayObject::ARRAY_AS_PROPS);

        $this->offsetSet('username', '');
        $this->offsetSet('password', '');
        $this->offsetSet('principal', '');
    }

    /**
     * Set the username to connect with.
     *
     * @param  string $username
     *  New username to login with. Leave blank to disable.
     * @return \MPorcheron\FreeBusyCal\Calendar
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
     * @return \MPorcheron\FreeBusyCal\Calendar
     *  `$this`.
     */
    public function setPassword($password = '')
    {
        $this->offsetSet('password', $password);
        return $this;
    }

    /**
     * Set the CalDAV principal URL.
     *
     * @param  string $url
     *  New URL to connect to.
     * @return \MPorcheron\FreeBusyCal\Calendar
     *  `$this`.
     */
    public function setPrincipalUrl($url)
    {
        $url = \filter_var($url, FILTER_SANITIZE_URL);
        $parts = \parse_url($url);

        $this->offsetSet('principal', $url);
        $this->offsetSet('baseUri', $parts['scheme'] .'://'. $parts['host'] .
            (isset($parts['port']) ? ':'. $parts['port'] : ''));

        return $this;
    }

    /**
     * Fetch the data needed to generate the availability calendar. If the iCal
     * data is already set, fetching is skipped unless {@code $refetch} is {@code true}
     *
     * @param  mixed[] $config
     *  Configuration data.
     * @param  boolean $refetch
     *  Refetch iCal data if it has already been fetched once.
     * @return \MPorcheron\FreeBusyCal\Calendar
     *  `$this`.
     */
    public function fetch(array $config, $refetch = false)
    {
        if (!$refetch && $this->iCal != null) {
            return $this;
        }

        // Instantiate the DAV client
        $settings = [
            'baseUri' => $this->baseUri,
            'userName' => $this->username,
            'password' => $this->password,
        ];
        $this->davClient = new DAVClient($settings);

        // Fetch the calendar URLs
        $calHomeSetUrl = $this->getCalendarHomeSet();
        $calUrls =  $this->getCalendarUrls($calHomeSetUrl);
        $calData = $this->getCalendarEventData($calUrls, $config['startDate'], $config['endDate']);

        // Merge each even to one data sequence
        $contents = \implode($calData);
        $contents = \preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $contents);
        $contents = "BEGIN:VCALENDAR\n" . \preg_replace("/(BEGIN|END):VCALENDAR\n/", "", $contents) . "END:VCALENDAR";
        $this->iCal = \preg_replace("/\n\s/", "", $contents);

        return $this;
    }

    /**
     * Fetch the calendar home set from the principal.
     *
     * @throws UnexpectedValueException
     *  Thrown if we can't get a calendar home set from the principal.
     * @return string
     *  URL to the calendar home set.
     */
    private function getCalendarHomeSet()
    {
        $calHomeSet = $this->davClient->propfind($this->principal, [self::CAL_HOME_SET]);
        if (empty($calHomeSet)) {
            throw new UnexpectedValueException(
                'CalDAV principal "'. $this->principal .'" did not return a calendar home set');
        }

        return $calHomeSet[self::CAL_HOME_SET][0]['value'];
    }

    /**
     * Fetch the URLs for the calendars from the home set.
     *
     * @param string $calHomeSetUrl
     *  Calendar home set URL.
     * @throws UnexpectedValueException
     *  Thrown if we can't get any calendars from the home set URL.
     * @return string[]
     *  Array of URLs for the calendars.
     */
    private function getCalendarUrls($calHomeSetUrl)
    {
        $cals = $this->davClient->propfind($calHomeSetUrl, [self::CAL_RESOURCETYPE, self::CAL_GETCTAG], 1);
        if (empty($cals)) {
            throw new UnexpectedValueException('Calendar home set "'. $calHomeSetUrl .'" did not return any cals');
        }
       
        $calUrls = [];
        foreach ($cals as $path => $resource) {
            $values = $resource[self::CAL_RESOURCETYPE]->getValue();
            foreach ($values as $value) {
                if ($value === self::CAL_RT_CALENDAR) {
                    $calUrls[] = $this->baseUri . $path;
                }
            }
        }

        return $calUrls;
    }

    /**
     * Retrieve calendar event data for the given date range.
     *
     * @param array $urls
     *  URLs of calendars to retrieve data from.
     * @param \DateTime $startDate
     *  The start date to fetch events from.
     * @param \DateTime $endDate
     *  The end date to fetch events up to.
     * @return array
     *  The calendar event data as an array of stirngs.
     */
    private function getCalendarEventData(array $urls, \DateTime $startDate, \DateTime $endDate)
    {
        $range = '<c:time-range start="'. $startDate->format('Ymd\THis\Z');
        $range .= '" end="'. $endDate->format('Ymd\THis\Z') .'"/>';
        $request = <<<REQUEST
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
    <d:prop>
        <c:calendar-data />
    </d:prop>
    <c:filter>
        <c:comp-filter name="VCALENDAR">
            <c:comp-filter name="VEVENT">
                $range
            </c:comp-filter>
        </c:comp-filter>
    </c:filter>
</c:calendar-query>
REQUEST;

        $xmlService = new XmlService();
        $xmlService->elementMap = [
            '{DAV:}response' => 'Sabre\Xml\Deserializer\keyValue',
        ];

        $iCalData = [];
        
        foreach ($urls as $url) {
            $dav = $this->davClient->request('REPORT', $url, $request);
            $responses = $xmlService->parse($dav['body']);

            if (empty($responses)) {
                continue;
            }

            foreach ($responses as $response) {
                $value = $response['value'];
                if (isset($value[self::DAV_PROPSTAT]) && is_array($value[self::DAV_PROPSTAT])) {
                    foreach ($value[self::DAV_PROPSTAT] as $prop) {
                        if (!is_array($prop)  || !isset ($prop['value']) || !is_array($prop['value'])) {
                            continue;
                        }

                        foreach ($prop['value'] as $propValue) {
                            if (isset($propValue['name']) && $propValue['name'] === self::CAL_DATA) {
                                $iCalData[] = $propValue['value'];
                            }
                        };
                    }
                }
            }
        }

        return $iCalData;
    }
}
