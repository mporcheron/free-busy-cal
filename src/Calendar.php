<?php

/**
 * File for a calendar configuration and data.
 *
 * @package MPorcheron\FreeBusyCal
 */

namespace MPorcheron\FreeBusyCal;

use ICal\ICal;
use Sabre\DAV\Client as DAVClient;
use Sabre\VObject\Reader;
use Sabre\VObject\FreeBusyGenerator;

/**
 * Container for a calendar's configuration and data, this class handles a specific calendar source's configuration
 * and event data in iCal format.
 *
 * @author    Martin Porcheron <martin-fbc@porcheron.uk>
 * @copyright (c) Martin Porcheron 2017.
 * @license   MIT Licence
 */

class Calendar extends \ArrayObject
{
    /**
     * @var string iCal file.
     */
    protected $iCal = null;

    /**
     * @var mixed[] Configuration data.
     */
    private $data;

    /**
     * Create the calendar configuration with the default values.
     */
    public function __contruct()
    {
        parent::__construct([], \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Set the iCal file to parsed from an HTTP(S) or FTP address.
     *
     *
     * @param  string $url
     *  iCal file to be downloaded.
     * @return MPorcheron\FreeBusyCal\Calendar
     *  `$this`.
     * @throws InvalidArgumentException
     *  If the passed file is not valid or is not downloadable.
     */
    public function setUrl($url)
    {
        if (!\in_array(\substr($url, 0, 7), ['http://', 'https:/', 'ftp://'])) {
            throw new \InvalidArgumentException('URL "'. $url .'" must start with http://, https:// or ftp://');
        }
        $this->path = $url;
        return $this;
    }

    /**
     * Set the iCal file to parsed (can be local or remote)
     *
     * @param  string $file
     *  iCal file to be parsed.
     * @return MPorcheron\FreeBusyCal\Calendar
     *  `$this`.
     * @throws InvalidArgumentException
     *  If the passed file does not exist or is not readable.
     */
    public function setFile($file)
    {
        if (!\is_readable($file)) {
            throw new \InvalidArgumentException('The file "'. $file .'" does not exist or is not readable');
        }

        $this->path = $file;
        return $this;
    }

    /**
     * Set the iCal data to parsed. Note: no validation occurs!
     *
     * @param  string $iCal
     *  iCal data to be parsed.
     * @return MPorcheron\FreeBusyCal\Calendar
     *  `$this`.
     */
    public function setiCal($iCal)
    {
        $this->iCal = $iCal;
        return $this;
    }

    /**
     * Fetch the iCal file needed to generate the availability calendar.
     *
     * @param  mixed[] $config
     *  Configuration data.
     * @param  boolean $refetch
     *  Refetch iCal data if it has already been fetched once.
     * @return MPorcheron\FreeBusyCal\Calendar
     *  `$this`.
     * @throws \BadFunctionCallException
     *  If the iCal data hasn't been fetched/set yet.
     */
    public function fetch(array $config, $refetch = false)
    {
        if (!$refetch && !empty($this->iCal)) {
            throw new \BadFunctionCallException('iCal data has already been fetched');
        }

        if (\is_null($this->path)) {
            throw new \BadFunctionCallException('Calendar::setUrl(), or ' .
                'Calendar::setFile() before parsing iCal');
        }

        $contents = @\file_get_contents($this->path);
        if ($contents === false) {
            throw new \InvalidArgumentException('Could not load iCal from path "'. $this->path .'"');
        }
        $this->iCal = $contents;

        return $this;
    }

    /**
     * Parse the iCal file needed to generate the availability calendar.
     *
     * @param  mixed[] $config
     *  Configuration data.
     * @return MPorcheron\FreeBusyCal\FreeBusyCalendar
     *  Availability for the calendar.
     * @throws \BadFunctionCallException
     *  If the iCal data hasn't been fetched/set yet.
     */
    public function parse(array $config)
    {
        if (\is_null($this->iCal)) {
            throw new \BadFunctionCallException('Must call Calendar::setiCal(), Calendar::setUrl(), or ' .
                'Calendar::setFile() before parsing iCal');
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
