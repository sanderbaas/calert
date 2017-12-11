# Calert: Calendar Reminders

With Calert you can extract events from caldav calendars. This information can
be used to send reminders by email for example. There are several options:
- request events from a calendar from the current day, today `--day`
- request events from a calendar from the current week, beginning now `--week`
- request events from a calendar by a date range `--start=1/1/18 --end=10/10/18`
- request events created after ctag `--ctag=xyz`

## Example use in crontab

    # m h  dom mon dow   command
      0 20  *   *   0    /usr/bin/php /home/user/calert/calert.php --config=/home/user/calert.conf --week | mail -E -r noreply@test.com -s "Calendar week report" you@test.com
      * *   *   *   *    /usr/bin/php /home/user/calert/calert.php --config=/home/user/calert.conf --ctag | mail -E -r noreply@test.com -s "New calendar item" you@test.com

## Example config file

An example config file is included (see: default.conf)

    [Calendar]
    url = 'https://calendar.io'
    username = 'username'
    password = 'password'
    auth = 'basic'
    
    [Ctag]
    ctag_file = '/home/user/.calert-ctag'


