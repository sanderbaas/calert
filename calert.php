<?php
use Sabre\VObject;

include 'vendor/autoload.php';

$shortopts = 'c::';
$longopts = array('config::','user::','pass::','url::','auth::','week','day','tomorrow','start::','end::','ctag::','locale::','timezone::','ctag-file::','ctag-write-file::','ctag-read-file::');

$opts = getopt($shortopts, $longopts);

$allowedAuthMethods = array('basic');

$calendarUrl = false;
$username = false;
$password = false;
$auth = false;
$start = false;
$end = false;
$locale = null;
$timezone = null;
$ctag = false;
$ctagWriteFile = false;
$ctagReadFile = false;

if (!empty($opts['config']) && is_readable($opts['config'])) {
  $config = parse_ini_file($opts['config']);
  if ($config && !empty($config['url'])) { $calendarUrl = $config['url']; }
  if ($config && !empty($config['auth']) && in_array($config['auth'], $allowedAuthMethods)) {
    $auth = $config['auth'];
  }
  if ($config && !empty($config['username'])) { $username = $config['username']; }
  if ($config && !empty($config['password'])) { $password = $config['password']; }
  if ($config && !empty($config['ctag_write_file'])) { $ctagWriteFile = $config['ctag_write_file']; }
  if ($config && !empty($config['ctag_read_file'])) { $ctagReadFile = $config['ctag_read_file']; }
  if ($config && !empty($config['ctag_file'])) {
    $ctagReadFile = $config['ctag_file'];
    $ctagWriteFile = $config['ctag_file'];
  }
  if ($config && !empty($config['locale'])) { $locale = $config['locale']; }
  if ($config && !empty($config['timezone'])) { $timezone = $config['timezone']; }
}

if (!empty($opts['ctag-file'])) {
  $ctagWriteFile = $opts['ctag-file'];
  $ctagReadFile = $opts['ctag-file'];
}

if (!empty($opts['url'])) { $calendarUrl = $opts['url']; }
if (!empty($opts['user'])) { $username = $opts['user']; }
if (!empty($opts['pass'])) { $password = $opts['pass']; }
if (!empty($opts['auth']) && in_array($opts['auth'], $allowedAuthMethods)) {
  $auth = $opts['auth'];
}
if (!empty($opts['locale'])) { $locale = $opts['locale']; }
if (!empty($opts['timezone'])) { $timezone = $opts['timezone']; }

if ($auth == false && $username !== false && $password !== false) {
  $auth = 'basic';
}

if (isset($opts['week'])) {
  // today + 6 days
  $start = new DateTime('now');
  $start->modify('midnight');
  $end = new DateTime('now');
  $end->modify('+1 week midnight');
}

if (isset($opts['day'])) {
  //today + 1 day
  $start = new DateTime('now');
  $start->modify('midnight');
  $end = new DateTime('now');
  $end->modify('+1 day midnight');
}

if (isset($opts['tomorrow'])) {
  $start = new DateTime('tomorrow');
  $start->modify('midnight');
  $end = new DateTime('tomorrow');
  $end->modify('+1 day midnight');
}

if (isset($opts['ctag']) && !empty($opts['ctag'])) {
  $ctag = $opts['ctag'];
}

if (isset($opts['ctag']) && empty($opts['ctag'])) {
  $ctag = true;
}

if (isset($opts['ctag-write-file'])) {
  $ctagWriteFile = $opts['ctag-write-file'];
}

if (isset($opts['ctag-read-file'])) {
  $ctagReadFile = $opts['ctag-read-file'];
}

// only read ctag from file if ctag not given as opt
// so when --ctag and not when --ctag=123
if ($ctag===true && $ctagReadFile && is_readable($ctagReadFile)) {
  $ctagr = file_get_contents($ctagReadFile);
  if (!empty($ctagr)) { $ctag = $ctagr; }
}

if (!empty($opts['start']) && date_parse($opts['start'])) {
  $start = new DateTime($opts['start']);
}

if (!empty($opts['end']) && date_parse($opts['end'])) {
  $end = new DateTime($opts['end']);
}

if (!$end && $start) {
  $end = new DateTime($start->format('Y-m-d'));
  $end->modify('+1 day midnight');
}

if (!$calendarUrl) {
  die('A calendar url is required. Please supply an url with --url or with a config file through --config.' . PHP_EOL);
}

if (!$start && !$end && !$ctag && !$ctagReadFile && !$ctagWriteFile) {
  $message = 'A date range or ctag is required. Please supply one of the following: ' . PHP_EOL;
  $message .= '- a start date through --start, end date will equal start date' . PHP_EOL;
  $message .= '- a start and end date through --start and --end' . PHP_EOL;
  $message .= '- the week option (--week)' . PHP_EOL;
  $message .= '- the day option (--day)' . PHP_EOL;
  $message .= '- a ctag (--ctag=123) after which to show events' . PHP_EOL;
  $message .= '- a ctag-file (--ctag-file=filename)' . PHP_EOL;
  $message .= '- a ctag-read-file (--ctag-read-file=filename)' . PHP_EOL;
  $message .= '- a ctag-write-file (--ctag-write-file=filename)' . PHP_EOL;
  die($message);
}

// either config or user, pass and url should be passed
// either ctag or week or day or start and end shoud be passed
function getCalendarCtag($url, $auth=false, $username=false, $password=false){
  $headers = array();

  if ($auth && $auth == 'basic' && $username && $password) {
    $headers[] = 'Authorization: Basic ' . base64_encode($username.':'.$password);
  }

  $headers[] = 'Depth: 0';
  $headers[] = 'Content-type: application/xml';

  $context = stream_context_create(array(
    'http' => array(
        'method' => 'PROPFIND',
        'header' => implode("\r\n", $headers) . "\r\n",
        'content' => '<d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/"><d:prop><d:displayname /><d:sync-token /><cs:getctag /></d:prop></d:propfind>'
    )
  ));

  $doc = false;
  $cs = false;
  $ctag = false;
  $xml = false;

  if (isHostOnline($url)) {
    $xmlData = file_get_contents($url,false,$context);
    $xml=simplexml_load_string($xmlData);
  }

  $doc = $xml;

  if ($xml!==false && $xml->children('d',true)) {
    $doc = $xml->children('d',true);
  }

  if ($doc && $doc->response && $doc->response->propstat && $doc->response->propstat->prop && $doc->response->propstat->prop->children('cs',true)) {
    $cs = $doc->response->propstat->prop->children('cs',true);
  }

  if ($doc && $doc->response && $doc->response->propstat && $doc->response->propstat->prop && $doc->response->propstat->prop->{'sync-token'}) {
    $cs = $doc->response->propstat->prop->{'sync-token'};
    if ($cs) { $ctag=$cs[0]; }
  }

  if ($cs!==false && $cs->getctag) {
    $ctag = $cs->getctag;
  }

  return $ctag;
}

function isHostOnline($location) {
return true;
  $port = 80;
  if (substr($location,0,8)=='https://') { $port = 443; }

  $_location = str_replace(array('https://','http://'),'', $location);
  $parts = explode('/', $_location);
  $host = $parts[0];

  $connected = @fsockopen($host, $port);

  if ($connected === false) { return false; }

  fclose($connected);

  return true;
}

function getEventsByCtag($url, $ctag, $auth=false, $username=false, $password=false, $ctagWriteFile=false) {
  $report_data = '<?xml version="1.0" encoding="utf-8" ?>' .
  '<d:sync-collection xmlns:d="DAV:">' .
  '<d:sync-token>'. $ctag .'</d:sync-token>' .
  '<d:sync-level>1</d:sync-level>' .
  '<d:prop>' .
  '<d:getetag/>' .
  '</d:prop>' .
  '</d:sync-collection>';

  $headers = array();

  if ($auth && $auth == 'basic' && $username && $password) {
    $headers[] = 'Authorization: Basic ' . base64_encode($username.':'.$password);
  }

  $headers[] = 'Content-type: application/xml';

  $context = stream_context_create(array(
    'http' => array(
      'method' => 'REPORT',
      'header' => implode("\r\n", $headers) . "\r\n",
      'content' => $report_data
    )
  ));

  $xml = false;
  $doc = false;

  if (isHostOnline($url)) {
    $xmlData = file_get_contents($url,false,$context);
    $xml=simplexml_load_string($xmlData);
    $doc=$xml;
  }

  if ($xml===false) { return false; }

  $urls = array();

  if ($xml!==false && $xml->children('d',true)) {
    $doc = $xml->children('d',true);
  }

  if ($doc!==false && $doc->response) {
    foreach ($doc->response as $response) {
      if (empty($response->status)) { $urls[] = $response->href; }
    }
  }

  if ($ctagWriteFile && $doc->{'sync-token'}) {
    $result = false;
    $ctagw = $doc->{'sync-token'};

    if ($ctagw) {
      $result = file_put_contents($ctagWriteFile, $ctagw);
    }

    if (!$result && $ctagw) {
      echo 'Could not write ctag to ' . $ctagWriteFile . PHP_EOL;
    }
  }

  $report_data2 = '<c:calendar-multiget xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">' .
  '<d:prop>' .
  '<d:getetag />' .
  '<c:calendar-data />' .
  '</d:prop>';

  if (!empty($urls)) {
    foreach ($urls as $eventUrl) {
      $report_data2 .= '<d:href>' . $eventUrl . '</d:href>';
    }
  }
  $report_data2 .= '</c:calendar-multiget>';

  $headers = array();

  if ($auth && $auth == 'basic' && $username && $password) {
    $headers[] = 'Authorization: Basic ' . base64_encode($username.':'.$password);
  }

  $headers[] = 'Depth: 1';
  $headers[] = 'Content-type: application/xml';
  $headers[] = 'Prefer: return-minimal';

  $context = stream_context_create(array(
    'http' => array(
      'method' => 'REPORT',
      'header' => implode("\r\n", $headers) . "\r\n",
      'content' => $report_data2
    )
  ));

  $xmlData = false;

  if (isHostOnline($url)) {
    $xmlData = file_get_contents($url,false,$context);
  }

  if (!$xmlData) { return false; }
  $xmlData = onlyLastVevent($xmlData);
  return xmlDataToEvents($xmlData);
}

function onlyLastVevent($xmlData) {
  $parts = explode('BEGIN:VEVENT',$xmlData);
  if (sizeof($parts)==1) { return $xmlData; }
  $xml = $parts[0];
  $xml .= "BEGIN:VEVENT";
  $chunk = str_replace('END:VCALENDAR','',array_pop($parts));
  $chunk = str_replace('END:VEVENT',"END:VEVENT" .PHP_EOL. "END:VCALENDAR",$chunk);
  $xml .= $chunk;
  return $xml;
}

function xmlDataToEvents($xmlData, $dateStart=false, $dateEnd=false) {
  $xml=simplexml_load_string($xmlData);
  $doc = $xml;

  $vevents = array();
  $calendar = new VObject\Component\VCalendar();

  if ($xml!==false && $xml->children('d',true)) {
    $doc = $xml->children('d',true);
  }

  if ($doc!==false && $doc->response) {
    foreach ($doc->response as $response) {
      $responses[] = $response;
      $cal = $response->propstat->prop->children('cal',true);
      if (!$cal) { $cal = $response->propstat->prop->children('C',true); }
      $calData = (string)$cal->{'calendar-data'};
      $calendarPart = VObject\Reader::read($calData);
      $calendar->add($calendarPart->VEVENT);
    }
  }

  if ($dateStart && $dateEnd) {
    $filteredCalendar = $calendar->expand($dateStart, $dateEnd);
  }
  if (!$dateStart || !$dateEnd) {
    $filteredCalendar = $calendar;
  }

  $events = array();
  if (!empty($filteredCalendar->VEVENT)) {
    $events = $filteredCalendar->VEVENT;
  }

  $results = array();

  foreach ($events as $event){
    $summary = $event->SUMMARY;
    $dtEnd = !empty($event->DTEND) ? $event->DTEND->getDateTime() : false;
    $duration = !empty($event->DURATION) ? $event->DURATION : false;

    $dtStart = $event->DTSTART->getDateTime();
    $dtSame = false;
    if ($dtEnd) {
        $diff = (int)$dtStart->diff($dtEnd)->format('%r%a');
	$startTime = $dtStart->format('H:i:s');
        $endTime = $dtEnd->format('H:i:s');
        $dtSame = $diff==1 && $startTime==$endTime;
    }

    $startDate = new DateTime($dtStart->format('Y-m-d H:i:s'),$dtStart->getTimezone());
    if (!empty($timezone)) { $startDate->setTimezone(new DateTimeZone($timezone)); }
    $start = $startDate->format('Y-m-d H:i:s');

    if ($dtEnd) {
      $endDate = new DateTime($dtEnd->format('Y-m-d H:i:s'),$dtEnd->getTimezone());
      if (!empty($timezone)) { $endDate->setTimezone(new DateTimeZone($timezone)); }
      $end = $endDate->format('Y-m-d H:i:s');
    }

    if ($duration) {
      $interval = new DateInterval($duration);
      $endDate = new DateTime($startDate->format('Y-m-d H:i:s'), $startDate->getTimezone());
      $endDate->add($interval);
      $end = $endDate->format('Y-m-d H:i:s');
    }

    $diff = $startDate->diff($endDate);
    $sameDay = $dtSame || ((int)$diff->format('%r%a') == 0);
    $wholeDay = $dtSame;

    $results[] = array(
      'start' => $start,
      'startDate' => $startDate,
      'end' => $end,
      'endDate' => $endDate,
      'sameDay' => $sameDay,
      'wholeDay' => $wholeDay,
      'summary' => (string)$summary
    );
  }

  // order results on start date
  usort($results, function($a, $b){
    $ta = $a['startDate']->getTimestamp();
    $tb = $b['startDate']->getTimestamp();

    if ($a==$b) { return 0; }
    if ($a<$b) { return -1; }
    if ($a>$b) { return 1; }
  });

  return $results;
}

function getEventsByDates($url, $dateStart=false, $dateEnd=false, $auth=false, $username=false, $password=false) {
  $calUrl = $url;

  $report_data = '<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">' .
  '<d:prop>' .
  '<d:getetag />' .
  '<c:calendar-data />' .
  '</d:prop>' .
  '<c:filter>' .
  '<c:comp-filter name="VCALENDAR" />' .
  '</c:filter>' .
  '</c:calendar-query>';

  $headers = array();

  if ($auth && $auth == 'basic' && $username && $password) {
    $headers[] = 'Authorization: Basic ' . base64_encode($username.':'.$password);
  }

  $headers[] = 'Depth: 1';
  $headers[] = 'Content-type: application/xml';
  $headers[] = 'Prefer: return-minimal';

  $context = stream_context_create(array(
    'http' => array(
      'method' => 'REPORT',
      'header' => implode("\r\n", $headers) . "\r\n",
      'content' => $report_data
    )
  ));

  $xmlData = false;
  if (isHostOnline($calUrl)) {
    $xmlData = file_get_contents($calUrl,false,$context);
  }

  if (!$xmlData) { return false; }
  return xmlDataToEvents($xmlData, $dateStart, $dateEnd);
}

if ($ctagWriteFile) {
  $result = false;
  $ctagw = getCalendarCtag($calendarUrl, $auth, $username, $password);

  if ($ctagw) {
    $result = file_put_contents($ctagWriteFile, $ctagw);
  }

  if ($ctagw && $ctag===true) {
    $ctag = $ctagw;
  }

  if (!$result && $ctagw) {
    echo 'Could not write ctag to ' . $ctagWriteFile . PHP_EOL;
  }
}

$events = array();
if ($ctag) {
  $eventResults = getEventsByCtag($calendarUrl, $ctag, $auth, $username, $password, $ctagWriteFile);
  if ($eventResults) { $events = $eventResults; }
}
if (!$ctag) {
  $eventResults = getEventsByDates($calendarUrl, $start, $end, $auth, $username, $password);
  if ($eventResults) { $events = $eventResults; }
}

$fmt = new IntlDateFormatter($locale, null, null, $timezone, null);

foreach ($events as $event) {
  if ($event['sameDay']) {
    $fmt->setPattern('EEEE d MMMM YYYY');
    $dates = $fmt->format($event['startDate']->gettimestamp());
    $fmt->setPattern('HH:mm');
    $startTime = $fmt->format($event['startDate']->getTimestamp());
    $endTime = $fmt->format($event['endDate']->getTimestamp());

    if (!$event['wholeDay'] && ($startTime == $endTime)) {
      $dates .= PHP_EOL . $startTime;
    }

    if ($startTime !== $endTime) {
      $dates .= PHP_EOL . $startTime . ' - ' . $endTime;
    }
  }

  if (!$event['sameDay']) {
    $fmt->setPattern('EEEE d MMMM YYYY HH:mm');
    $start = $fmt->format($event['startDate']->getTimestamp());
    $end = $fmt->format($event['endDate']->getTimestamp());
    $dates = $start . ' - ' .$end;
  }
  echo $dates . PHP_EOL . $event['summary'] . PHP_EOL . PHP_EOL;
}
?>
