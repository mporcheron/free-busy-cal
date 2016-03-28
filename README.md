# Free/Busy Calendar - https://www.porcheron.uk/fbc

## Usage
An example usage can be found in `example.php`. This file connects to a CalDAV server, extracts two weeks of dates 
(excluding weekends) and generates an HTML table. A sample walkthough of the code is below:

Create a calendar configuration:

    $cal = (new Porcheron\FreeBusy\Calendar())
      ->setUsername('ad\username')
      ->setPassword('password')
      ->setUrl('https://caldav.example.com:8443/users/username@example.com/calendar');


Create the Generator object and add the calendar:

    $fbc = (new Porcheron\FreeBusyCal\Generator($cal));


Set the date range to extract, e.g. start from this Monday, and run for 14 days (i.e. two weeks), but exclude
weekends:

    $fbc->setDateRange(new \DateTime('Monday this week'), 14, false);


Only generate a calendar between 9am (inclusive) and 5pm (exclusive):

    $fbc->setTimeRange(9, 17);


Label the days in our output (optional, depends on if you use the built in output):

    $fbc->setDayLabels('M', 'T', 'W', 'T', 'F', 'S', 'S');


Show two weeks horizontally (optional, depends on if you use the built in output):

    $fbc->setWeeksPerRow(2);


Fetch the calendars and process them:

    $fbc->->fetch();


Print out the calendar table:

    echo $fbc->getTable('class="cal"', 
     Porcheron\FreeBusyCal\Generator::DATE_FORMAT,
     Porcheron\FreeBusyCal\Generator::TIME_FORMAT, 
     'Free',
     'Busy');


Alternatively test if a specific time/date (i.e. 5pm on 4th May 2016) is available:

    $free = $fbc->isFree('2016-05-04', 17, 0);


## Questions/Issues?
Please submit a [GitHub issue](https://github.com/mporcheron/FreeBusyCal)