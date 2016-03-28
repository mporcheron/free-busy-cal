<?php

/**
 * FreeBusyCal example script. This file connects to an iCal server, retrieves data and calculates availbility,
 * generates and outputs HTML.
 *
 * @author Martin Porcheron <martin@porcheron.uk>
 * @copyright (c) Martin Porcheron 2016.
 * @license MIT Licence
 */

use Porcheron\FreeBusyCal as Fbc;

include 'vendor/autoload.php';

// Configuration of calendars //////////////////////////////////////////////////////////////////////////////////////////

$cal = (new Fbc\Calendar())
    ->setUsername('ad\username')
    ->setPassword('password')
    ->setUrl('https://caldav.example.com:8443/users/username@example.com/calendar');

$fbc = (new Fbc\Generator($cal))
    ->setDateRange(new \DateTime('Monday this week'), 14, false)
    ->setTimeRange(9, 17, 30)
    ->setDayLabels('M', 'T', 'W', 'T', 'F', 'S', 'S')
    ->setWeeksPerRow(2)
    ->fetch();

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
        <!-- Free/Busy Calendar by Martin Porcheron <martin@porcheron.uk> -->
    </head>
    <body>

HEADER;
echo $fbc->get('class="cal"', Fbc\Generator::DATE_FORMAT, Fbc\Generator::TIME_FORMAT, 'Free', 'Busy', true);
echo <<<FOOTER

    </body>
</html>
FOOTER;
