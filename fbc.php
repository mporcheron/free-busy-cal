<?php

/**
 * Free/Busy Calendar
 *
 * Copyright (c) 2015 Martin Porcheron <martin@porcheron.uk>
 * See LICENCE for legal information.
 *
 * --
 *
 * To setup: confingure the variables below with your account details. It may
 * be wise to run this script via crontab instead of linking to it directory.
 *
 * e.g. php -f fbc.php > /var/www/calendar.html would cause the calendar to be
 * generated and saved to a static HTML file.
 *
 * You may want to include some styling (well, you almost definitely will).
 */

\set_time_limit (0);

\date_default_timezone_set ('Europe/London');

include 'CalendarInfo.php';
include 'CalDAVClient.php';
include 'vendor/autoload.php';

////////////////////////////////////////////////////////////////////////////////

// Accounts Setup
$accounts       = array ();
$accounts[]     = [
                'username' => 'username',
                'password' => 'password',
                'server'   => 'ssl://example.uk',
                'port'     => 443,
                'uri'      => 'https://example/users/username@domain.uk/calendar'
                ];

$numDays        = 14;
$startDate      = new \DateTime ('Monday this week');
$endDate        = clone $startDate;
$endDate->add (new \DateInterval ('P' . $numDays . 'D'));

$dayOffset      = $startDate->format ('N') - 1;
$days           = [ 'M', 'T', 'W', 'T', 'F', 'S', 'S' ];

$startHour      = 9;
$endHour        = 17;
$numHours       = $endHour - $startHour;

$weeksPerRow    = 2;

$includeWeekends= false;

$webPage        = '/home/username/public_html/calendar.html';

$calendar       = array ();

////////////////////////////////////////////////////////////////////////////////

// Data Import
$contents = '';
foreach ($accounts as $account) {
    $dav = new CalDAVClient ($account['uri'], $account['username'], $account['password'], '', $account['server'], $account['port']);
    //$events = $dav->GetEvents (\date('Ymd\THis\Z', $this->startDate), \date('Ymd\THis\Z', $this->endDate));
    foreach ($dav->GetEvents () as $event) {
        $contents .= $event['data'] ."\n";
    }
}

//$contents = \preg_replace ("/END:VCALENDAR(\n|\r|\r\n)BEGIN:VCALENDAR((\n|\r|\r\n)VERSION:2.0)/", '', $contents);
$contents = \preg_replace ("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $contents);
$contents = "BEGIN:VCALENDAR\n" . \preg_replace ("/(BEGIN|END):VCALENDAR\n/", "", $contents) . "END:VCALENDAR" ;
$contents = \preg_replace ("/\n\s/", "", $contents);

// Process calendars
$vcal = \Sabre\VObject\Reader::read ($contents, \Sabre\VObject\Reader::OPTION_FORGIVING|\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
$fbGenerator = new \Sabre\VObject\FreeBusyGenerator ($startDate, $endDate, $vcal);
$cmnts = $fbGenerator->getResult ()->getComponents ();
$fb = $cmnts[0];

// Mark every time as free/busy
$currentHour = $startDate;
for ($day = 0; $day < $numDays; $day++) {
    if ($includeWeekends || $currentHour->format ('N') <= 5) {
        $nextHour = clone $currentHour;

        $dayKey = $currentHour->format ('Y-m-d');
        $calendar[$dayKey] = array ();

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            $currentHour->setTime ($hour, 0, 0);
            $nextHour->setTime ($hour + 1, 0, 0);

            $hourKey = $currentHour->format ('ga');
            $calendar[$dayKey][$hourKey] = $fb->isFree ($currentHour, $nextHour);
        }
    }

    $currentHour->add (new \DateInterval ('P1D'));
}

\ob_start ();

?><html>
    <head>
        <title>Free/Busy Calendar</title>
        <style type="text/css">
        table.calendar {
            cursor: default;
            width: 100%;
        }

        th {
            background: transparent;
            margin-bottom: 1em;
        }

        table.calendar td, table.calendar th {
            text-align: center;
            width: 9%;
        }

        .calendar td.time {
            padding-top: 37px;
        }

        .calendar th:nth-child(5n+1), .calendar td:nth-child(5n+1) {
            padding-right: 10px;
        }

        .calendar  div.time {
            text-align: right;
            padding-right: 15px;
            height: 30px;
        }

        .calendar div.date {
            height: 30px;
            vertical-align: bottom;
        }

        .calendar div.avail {
            cursor: default;
            color: #FFFFFF;
            display: block;
            margin-bottom: 1px;
            height: 27px;
            padding-top: 3px;
            text-align: center;
        }

        .calendar div.avail .status {
            opacity: 0;
            -webkit-transition: opacity .2s ease-in-out;
            -moz-transition: opacity .2s ease-in-out;
            -o-transition: opacity .2s ease-in-out;
            -ms-transition: opacity. 2s ease-in-out;
            transition: opacity .2s ease-in-out;
        }

        .calendar div.avail:hover .status {
            opacity: 1;
        }

        .calendar .avail.busy {
            background: #AA6786;
        }

        .calendar .avail.free {
            background: #71B075;
        }
        </style>
        <!-- Free/Busy Calendar by Martin Porcheron <martin@porcheron.uk> -->
    </head>
    <body>
        <table class="calendar">
            <tr>
                <th></th>
            <?php for ($i = 0; $i < $weeksPerRow * 7; $i++): $dayNum = ($i + $dayOffset) % 7; if (!$includeWeekends && $dayNum > 4) { continue; } $day = $days[$dayNum]; ?>
                <th><?php print $day; ?></th>
            <?php endfor; ?>
            </tr>
            <?php $max = $weeksPerRow * ($includeWeekends ? 7 : 5); $dayNum = 1; foreach ($calendar as $date => $times): ?>
            <?php if ($dayNum === 1): ?>
            <tr>
                <td class="time">
                    <?php foreach ($times as $time => $avail): ?>
                        <div class="time"><?php print $time; ?></div>
                    <?php endforeach; ?>
                </td>
            <?php endif; ?>
                <td>
                    <div class="date"><?php print date ('j/n', \strtotime ($date)); ?></div>
                    <?php foreach ($times as $time => $avail): ?>
                        <div class="avail <?php print $avail ? 'free' : 'busy'; ?>"><span class="status"><?php print $avail ? 'Free' : 'Busy'; ?></span></div>
                    <?php endforeach; ?>
                </td>
            <?php if ($dayNum === $max): ?>
            </tr>
            <?php $dayNum = 1; else: $dayNum++; endif; ?>
            <?php endforeach; ?>
        </table>
    </body>
</html><?php

@\file_put_contents ($webpage, \ob_get_contents ());
\ob_clean ();
