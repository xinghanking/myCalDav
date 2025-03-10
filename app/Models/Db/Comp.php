<?php

namespace App\Models\Db;

use App\Models\Base\Db;
use DateTime;
use DateTimeZone;

class Comp extends Db
{
    protected $table = 'comp';
    protected $primaryKey = 'id';

    protected $fields = ['id', 'uri', 'calendar_id', 'type', 'uid', 'recurrence_id', 'dtstart', 'dtend', 'prop', 'comp_prop', 'ics_data', 'last_modified', 'etag', 'sequence'];

    const TYPE_MAP = [
            'VEVENT'    => Calendar::COMP_VEVENT,
            'VTODO'     => Calendar::COMP_VTODO,
            'VJOURNAL'  => Calendar::COMP_VJOURNAL,
            'VFREEBUSY' => Calendar::COMP_VFREEBUSY
        ];

    public function getBaseInfoByUri($uri)
    {
        return $this->getRow($this->fields, ['uri' => $uri, 'recurrence_id' => '']);
    }

    public function getIcsByCompUid($uid, $type) {
        $compType = array_search($type, self::TYPE_MAP);
        $data = $this->getData('ics_data', ['uid' => $uid]);
        foreach ($data as $k => $v) {
            $data[$k] = 'BEGIN:' . $compType . "\n" . $v['ics_data'] . "\nEND:" . $compType;
        }
        return implode("\n", $data);
    }

    public function getInfoByCalendarId($calendarId)
    {
        return $this->getData(['uri', 'prop', 'comp_prop', 'ics_data'], [['calendar_id', '=', $calendarId]]);
    }

    public function formatCurrenceId($recurrenceId) {
        if(is_string($recurrenceId)) {
            return $recurrenceId;
        }
        if(is_array($recurrenceId)) {
            return implode(';', $recurrenceId['p']) . ':' . $recurrenceId['v'];
        }
        return '';
    }

    public function addObject($uri, $calendarId, $type, $ics) {
        if (count($ics) == 1) {
            $ics  = current($ics);
            $dtTime = $this->getDtTime($ics);
            $info = [
                'uri'           => $uri,
                'calendar_id'   => $calendarId,
                'uid'           => $ics['UID'],
                'recurrence_id' => $this->formatCurrenceId($ics['RECURRENCE-ID'] ?? ''),
                'type'          => $type,
                'dtstart'       => $dtTime['dtstart'],
                'dtend'         => $dtTime['dtend'],
                'prop'          => json_encode(['d:getlastmodified' => gmdate('D, d M Y H:i:s', time()) . ' GMT', 'd:getetag' => $this->createEtag()], JSON_UNESCAPED_SLASHES),
                'comp_prop'     => json_encode($ics, JSON_UNESCAPED_SLASHES),
                'ics_data'      => Db::arrToIcs($ics)
            ];
            return $this->insertGetId($info);
        }
        $icsData = [];
        $prop = json_encode(['d:getlastmodified' => gmdate('D, d M Y H:i:s', time()) . ' GMT', 'd:getetag' => $this->createEtag()], JSON_UNESCAPED_SLASHES);
        foreach ($ics as $item) {
            $dtTime = $this->getDtTime($item);
            $icsData[] = [
                'uri'           => empty($item['RECURRENCE_ID']) ? $uri : '',
                'calendar_id'   => $calendarId,
                'uid'           => $item['UID'],
                'recurrence_id' => $this->formatCurrenceId($item['RECURRENCE-ID'] ?? ''),
                'type'          => $type,
                'dtstart'       => $dtTime['dtstart'],
                'dtend'         => $dtTime['dtend'],
                'prop'          => $prop,
                'comp_prop'     => json_encode($item, JSON_UNESCAPED_SLASHES),
                'ics_data'      => Db::arrToIcs($item)
            ];
        }
        return $this->batchInsert($icsData);
    }

    public function updateInstance($id, $ics)
    {
        $info = $this->getDtTime($ics);
        $info['comp_prop'] = json_encode($ics, JSON_UNESCAPED_SLASHES);
        $info['ics_data'] = Db::arrToIcs($ics);
        return $this->update($info , ['`id`=' => $id]);
    }

    public function updateProp($id, $prop)
    {
        $prop['d:getlastmodified'] = gmdate('D, d M Y H:i:s', time()) . ' GMT';
        $prop['d:getetag'] = $this->createEtag();
        $prop['d:getctag'] = $prop['d:getetag'];
        return static::query()->where('id', '=', $id)->update(['prop' => json_encode($prop, JSON_UNESCAPED_UNICODE)]);
    }
    public function getDtTime($ics)
    {
        $info = [];
        if(isset($ics['DTSTART']['p']['TZID'])) {
            $dt = new DateTime($ics['DTSTART']['v'], new DateTimeZone($ics['DTSTART']['p']['TZID']));
            $info['dtstart'] = $dt->getTimestamp();
        } else {
            $info['dtstart'] = is_string($ics['DTSTART']) ? strtotime($ics['DTSTART']) : time();
        }
        if(isset($ics['DTEND']['p']['TZID'])) {
            $dt = new DateTime($ics['DTEND']['v'], new DateTimeZone($ics['DTEND']['p']['TZID']));
            $info['dtend'] = $dt->getTimestamp();
            return $info;
        }
        if (isset($ics['DTEND']) && is_string($ics['DTEND'])) {
            $info['dtend'] = strtotime($ics['DTEND']);
            return $info;
        }
        $info['dtend'] = $info['dtstart'] + (isset($ics['DURATION']) ? $this->totalDuration($ics['DURATION']) : 3600);
        return $info;
    }

    public function updateEtag($uri)
    {
        $tag = $this->createEtag();
        $sql = 'UPDATE ' . $this->table . ' 
        SET `prop`=JSON_SET(`prop`, \'$."d:getlastmodified"\', :lastmodified, \'$."cs:getctag"\', \'' . $tag . '\', \'$."d:getetag"\', :etag), `sequence`=`sequence`+1 WHERE `uri`=:uri';
        return \Illuminate\Support\Facades\DB::update($sql, [':lastmodified' => gmdate('D, d M Y H:i:s', time()) . ' GMT', ':etag' => $this->createEtag(), ':uri' => $uri]);
    }

    public function createEtag() {
        return session('uid') . '-' .time();
    }

    public function totalDuration($duration) {
        $total = 0;
        if (preg_match('/P(\d+Y)?(\d+M)?(\d+D)?(\d+W)?/', $duration,$matches)) {
            if (!empty($matches[1])) {
                $total += (int) substr($matches[1], 0, -1) * 365 * 86400;
            }
            if (!empty($matches[2])) {
                $total += (int) substr($matches[2], 0, -1) * 2592000;
            }
            if (!empty($matches[3])) {
                $total += (int) substr($matches[3], 0, -1) * 86400;
            }
            if (!empty($matches[4])) {
                $total += (int) substr($matches[4], 0, -1) * 7 * 86400;
            }
        }
        if (preg_match('/T(\d+H)?(\d+M)?(\d+S)?/', $duration, $matches)) {
            if (!empty($matches[1])) {
                $total += (int) substr($matches[1], 0, -1) * 3600;
            }
            if (!empty($matches[2])) {
                $total += (int) substr($matches[2], 0, -1) * 60;
            }
            if (!empty($matches[3])) {
                $total += (int) substr($matches[3], 0, -1);
            }
        }
        return $total;
    }
}