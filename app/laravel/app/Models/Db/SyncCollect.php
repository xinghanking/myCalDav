<?php

namespace App\Models\Db;

use App\Models\Base\Db;

class SyncCollect extends Db
{
    protected $table = 'sync_collect';
    public $fillable = ['id', 'owner_id', 'calendar_id', 'collect', 'sync_token'];

    public function getCollectBySyncToken($syncToken) {
        if (empty($syncToken)) {
            return [];
        }
        if (is_numeric($syncToken)) {
            $where = ['id' => intval($syncToken)];
        } else {
            $where = ['sync_token' => $syncToken];
        }
        $collect = $this->getColumn('collect', $where);
        if (empty($collect)) {
            return [];
        }
        return json_decode($collect, true);
    }

    public function addRecord($calendarId, $collect, $syncToken) {
        $row = [
            'owner_id' => session('uid'),
            'calendar_id' => $calendarId,
            'collect' => json_encode($collect),
            'sync_token' => $syncToken
        ];
        return $this->insertGetId($row);
    }
}