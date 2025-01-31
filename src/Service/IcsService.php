<?php
/**
 * Created by PhpStorm.
 * User: Emanuel
 * Date: 03.10.2019
 * Time: 19:01
 */

namespace App\Service;


class IcsService
{
    const DT_FORMAT = 'Ymd\THis\Z';
    protected $properties = array();
    private $isModerator;
    private $timezoneId;
    private $timezoneStart;
    private $timezoneEnd;
    private $timeZone;

    public function __construct()
    {
        $this->isModerator = false;
        $this->timezoneId = 'Europe/Berlin';
        $this->timezoneStart = new \DateTime();
    }

    /**
     * @return \DateTime
     */
    public function getTimezoneStart(): \DateTime
    {
        return $this->timezoneStart;
    }

    /**
     * @param \DateTime $timezoneStart
     */
    public function setTimezoneStart(\DateTime $timezoneStart): void
    {
        $this->timezoneStart = $timezoneStart;
    }

    /**
     * @return mixed
     */
    public function getTimezoneEnd()
    {
        return $this->timezoneEnd;
    }

    /**
     * @param mixed $timezoneEnd
     */
    public function setTimezoneEnd($timezoneEnd): void
    {
        $this->timezoneEnd = $timezoneEnd;
    }

    /**
     * @return mixed
     */
    public function getIsModerator()
    {
        return $this->isModerator;
    }

    /**
     * @param mixed $isModerator
     */
    public function setIsModerator($isModerator): void
    {
        $this->isModerator = $isModerator;
    }

    private $available_properties = array(
        'description',
        'dtend',
        'dtstart',
        'location',
        'summary',
        'rrule',
        'uid',
        'sequense',
        'organizer',
        'attendee'
    );
    private $appointments = array();
    private $method; // REQUEST,CANCELED,PUBLISH

    public function set($key, $val = false)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v);
            }
        } else {
            if (in_array($key, $this->available_properties)) {
                $this->properties[$key] = $this->sanitizeVal($val, $key);
            }
        }
        $this->appointments[] = $this->properties;
    }

    public function add($key)
    {
        $this->appointments[] = $key;
    }

    public function setMethod($method)
    {

        $this->method = $method;
    }

    public function toString()
    {
        $rows = $this->buildProps();
        $res = implode("\r\n", $rows);
        return $res;
    }

    private function buildProps()
    {
        // Build ICS properties - add header
        $ics_props = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//hacksw/handcal//NONSGML v1.0//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:' . $this->method,
        );
        $ics_props[] = 'BEGIN:VTIMEZONE';
        $ics_props[] = 'TZID:' . $this->timezoneId;

        $this->timezoneStart = $this->timezoneStart->modify('first day of last year')->modify('last sunday of march');
        $timezoneStandard = clone $this->timezoneStart;
        $timezoneStandard->modify('last sunday of october');
        $ics_props[] = 'BEGIN:DAYLIGHT';
        $ics_props[] = 'DTSTART:'.$this->timezoneStart->format('Ymd').'T020000';//19501029T020000';
        $ics_props[] = 'TZOFFSETFROM:'.$this->timezoneStart->format('O');//+0100';
        $ics_props[] = 'TZOFFSETTO:'.$timezoneStandard->format('O');//+0200';
        $ics_props[] = 'RRULE:FREQ=YEARLY;BYMINUTE=0;BYHOUR=2;BYDAY=-1SU;BYMONTH=3';
        $ics_props[] = 'END:DAYLIGHT';



        $ics_props[] = 'BEGIN:STANDARD';
        $ics_props[] = 'DTSTART:'.$timezoneStandard->format('Ymd').'T020000';//19501029T020000';//19500326T020000';
        $ics_props[] = 'TZNAME:' . $timezoneStandard->format('T');
        $ics_props[] = 'TZOFFSETFROM:' . $timezoneStandard->format('O');
        $ics_props[] = 'TZOFFSETTO:' . $this->timezoneStart->format('O');
        $ics_props[] = 'RRULE:FREQ=YEARLY;BYMINUTE=0;BYHOUR=2;BYDAY=-1SU;BYMONTH=10';

        $ics_props[] = 'END:STANDARD';
        $ics_props[] = 'END:VTIMEZONE';

        // Build ICS properties - add header
        foreach ($this->appointments as $data) {
            $ics_props[] = 'BEGIN:VEVENT';

            $props = array();
            foreach ($data as $p => $q) {

                if ($this->isModerator) {
                    $props[strtoupper($p . ($p === 'attendee' ? ';RSVP=false:MAILTO' : ''))] = $q;
                } else {
                    $props[strtoupper($p . ($p === 'attendee' ? ';ROLE=REQ-PARTICIPANT; PARTSTAT=NEEDS-ACTION;RSVP=true:MAILTO' : ''))] = $q;
                }
            }
            // Set some default values
            $props['DTSTAMP'] = $this->formatTimestamp('now');
            $props['LAST-MODIFIED'] = $this->formatTimestamp('now');
            if (!$props['UID']) {
                $props['UID'] = uniqid('sd', true);
            }

            // Append properties
            foreach ($props as $k => $v) {
                if($k === 'DTSTART' || $k ==='DTEND'){
                    $k = $k.';TZID='.$this->timezoneId.'';
                }
                $ics_props[] = "$k:$v";
            }
            $ics_props[]='BEGIN:VALARM';
            $ics_props[] = 'ACTION:DISPLAY';
            $ics_props[] = 'TRIGGER:-PT10M';
            $ics_props[] = 'DESCRIPTION:'.$data['summary'];
            $ics_props[] = 'END:VALARM';
            $ics_props[] = 'END:VEVENT';
        }
        $ics_props[] = 'END:VCALENDAR';
        // Build ICS properties - add footer

        return $ics_props;
    }

    private function sanitizeVal($val, $key = false)
    {
        switch ($key) {
            case 'dtend':
                break;
            case 'dtstamp':
                $val = $this->formatTimestamp($val);
                break;
            case 'dtstart':
                $val = $this->formatTimestamp($val);
                break;
            default:
                $val = $this->escape_string($val);
                break;
        }
        return $val;
    }

    private function formatTimestamp($timestamp)
    {
        $dt = new \DateTime($timestamp);
        return $dt->format(self::DT_FORMAT);
    }

    private function escape_string($str)
    {
        return preg_replace('/([\,;])/', '\\\$1', $str);
    }

}
