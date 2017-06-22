<?php

/**
 * FreeBusyCal example script. This file connects to an iCal server, retrieves data and calculates availbility,
 * generates and outputs HTML.
 *
 * @author Martin Porcheron <martin-fbc@porcheron.uk>
 * @copyright (c) Martin Porcheron 2017.
 * @license MIT Licence
 */

include 'vendor/autoload.php';

use MPorcheron\FreeBusyCal as Fbc;

// Configuration of calendars //////////////////////////////////////////////////////////////////////////////////////////

$exchange = (new Fbc\Calendar())
    ->setUrl('https://outlook.office365.com/owa/calendar/dadad@example.com/23432rcsdf34fsc/calendar.ics');

$iCloud = (new MPorcheron\FreeBusyCal\CalDAVCalendar())
    ->setUsername('my.apple.id@me.com')
    ->setPassword('application-specific-password')
    ->setPrincipalUrl('https://caldav.icloud.com/123456789876543/principal/');

// Fetch the calendars /////////////////////////////////////////////////////////////////////////////////////////////////
 
$fbc = (new Fbc\Generator($exchange, $iCloud))
    ->setDateRange(new \DateTime('Monday this week'), 14, false)
    ->setTimeRange(9, 17, 60)
    ->fetchAndParse();

// Output the calendar /////////////////////////////////////////////////////////////////////////////////////////////////

echo <<<HEADER
<html>
    <head>
        <title>Free/Busy Calendar</title>
        <style type="text/css">
            table.cal {
                border-spacing: .1em;
                cursor: default;
                width: 100%;
            }

            .cal th {
                background: transparent;
                margin-bottom: 1em;
            }

            .cal td, .cal th {
                text-align: center;
                width: 9%;
            }

            .cal td {
                padding: .5em 0;
            }

            .cal div.time {
                text-align: right;
                padding-right: 15px;
                height: 30px;
            }

            .cal div.date {
                height: 30px;
                vertical-align: bottom;
            }

            .cal div.avail {
                cursor: default;
                display: block;
                margin-bottom: 1px;
                height: 30px;
                padding-top: 3px;
                text-align: center;
            }

            .cal td.avail span {
                color: #FFFFFF;
                opacity: 0;
                -webkit-transition: opacity .2s ease-in-out;
                -moz-transition: opacity .2s ease-in-out;
                -o-transition: opacity .2s ease-in-out;
                -ms-transition: opacity. 2s ease-in-out;
                transition: opacity .2s ease-in-out;
            }

            .cal td.avail:hover span {
                opacity: 1;
            }

            .cal .avail.busy {
                background: #AA6786;
            }

            .cal .avail.free {
                background: #71B075;
            }
        </style>
        <!-- Free/Busy Calendar by Martin Porcheron <martin-fbc@porcheron.uk> -->
    </head>
    <body>

HEADER;

echo $fbc->generate(function (Fbc\FreeBusyCalendar &$cal) {
    // Show time range, or just start time
    $showRange = true;

    $output = '<table class="cal">';

    // Output table headers with days
    $output .= '<tr><th></th>';
    $days = [ 'S', 'M', 'T', 'W', 'T', 'F', 'S' ];
    foreach ($cal->getCalendarDates(Fbc\FreeBusyCalendar::DATE_FORMAT) as $label => &$dt) {
        $output .= '<th class="day">'. $days[$dt->format('N')] .'</th>';
    }
    $output .= '</tr>';

    // Output table headers with dates
    $output .= '<tr><th></th>';
    foreach ($cal->getCalendarDates(Fbc\FreeBusyCalendar::DATE_FORMAT) as $label => &$dt) {
        $output .= '<th class="date">'. $label .'</th>';
    }
    $output .= '</tr>';

    // Iterate through each time and $output .= the availability
    $times = $cal->getCalendarTimes(Fbc\FreeBusyCalendar::TIME_FORMAT);
    foreach ($times as $hour => $temp) {
        foreach ($temp as $minute => $labels) {
            $output .= '<tr><td class="time">'. $labels[0];
            if ($showRange) {
                $output .= '&nbsp;&ndash;&nbsp;' . $labels[1];
            }
            $output .= '</td>';
            
            foreach ($cal->getCalendarDates(Fbc\FreeBusyCalendar::DATE_FORMAT) as $dt) {
                if ($cal->isFree($dt->format('Y-m-d'), $hour, $minute)) {
                    $output .= '<td class="avail free">Free</td>';
                } else {
                    $output .= '<td class="avail busy">Busy</td>';
                }
            }
        }
        $output .= '</td>';
    }
    $output .= '</table>';

    return $output;
});

echo <<<FOOTER

    </body>
</html>
FOOTER;
