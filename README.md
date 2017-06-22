# Free/Busy Calendar - https://www.porcheron.uk/fbc

## Usage
An example usage can be found in `example.php`. This file connects to a CalDAV server, extracts two weeks of dates 
(excluding weekends) and generates an HTML table. A sample walkthough of the code is below:

If you are loading an iCal file, use the `Calendar` class:

    $cal = (new MPorcheron\FreeBusyCal\Calendar())->setFile('calendar.ics');

Alternatively:

 * if the file is accessed over the internet, use the `setUrl(url)` function instead of `setFile(file)`. 
 * if you have the calendar source inside a string, use the function `setiCal(source)`

If your calendar is retrieved from a CalDAV server, use the `CalDAVCalendar` class:

    $cal = (new MPorcheron\FreeBusyCal\CalDAVCalendar())
        ->setUsername('my.apple.id@me.com')
        ->setPassword('application-specific-password')
        ->setPrincipalUrl('https://caldav.icloud.com/123456789876543/principal/');

Create the `Generator` object and add one or more calendars:

	$fbc = new \MPorcheron\FreeBusyCal\Generator($cal, $iCloud);

Set the date range to extract, e.g. start from this Monday, and run for 14 days (i.e. two weeks), but exclude
weekends:

    $fbc->setDateRange(new \DateTime('Monday this week'), 14, false);


Only generate a calendar between 9am (inclusive) and 5pm (exclusive):

    $fbc->setTimeRange(9, 17);


Fetch the calendars and process them:

    $fbc->fetchAndParse();


Print out the calendar as a table, default date and time formats, the labels `Free` and `Busy` for
slots, and show times as ranges (i.e. start – end):

    $contents = $fbc->generate(function (Fbc\FreeBusyCalendar &$cal) {
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


Alternatively test if a specific time/date (i.e. 5pm on 4th May 2016) is available:

     $cal = $fbc->getFreeBusyCalendar();
     $free = $cal->isFree('2016-05-04', 17, 0);


## Testing
This has been tested with the ICS file from Office 365 and iCloud CalDAV.

## Questions/Issues?
Please submit a [GitHub issue](https://github.com/mporcheron/FreeBusyCal)