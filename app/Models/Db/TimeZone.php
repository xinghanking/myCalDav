<?php

namespace App\Models\Db;

use App\Models\Base\Db;

class TimeZone extends Db
{
    protected $table = 'timezone';
    protected $primaryKey = 'id';

    public function add($calendarId, $info) {
        foreach ($info as $k => $timezone) {
            $info[$k] = [
                'calendar_id' => $calendarId,
                'tzid'     => $timezone['TZID'],
                'standard' => isset($timezone['STANDARD']) ? json_encode($timezone['STANDARD'][0], JSON_UNESCAPED_UNICODE) : '',
                'daylight' => isset($timezone['DAYLIGHT']) ? json_encode($timezone['DAYLIGHT'][0], JSON_UNESCAPED_UNICODE) : '',
                'last_modified' => $timezone['LAST-MODIFIED']
            ];
        }
        return $this->batchInsert($info);
    }
}