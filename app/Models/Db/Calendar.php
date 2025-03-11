<?php

namespace App\Models\Db;

use App\Models\Base\Db;

class Calendar extends Db
{
    protected $table = 'calendar';
    protected $primaryKey = 'id';

    protected $fields = ['id', 'uri', 'owner_id', 'prop', 'comp_prop', 'ics_data', 'etag', 'last_modified', 'sync_token'];

    const COMP_CALENDAR = 0;
    const COMP_VEVENT = 1;
    const COMP_VTODO = 2;
    const COMP_VJOURNAL = 3;
    const COMP_VFREEBUSY = 4;
    const PRODID = '-//Han Dress//CalDav//ZH_CN';
    const VERSION = '2.0';
    const CALSCALE = 'GREGORIAN';

    private $baseProp = [
        'd:resourcetype'            => '<d:collection /><c:calendar />',
        'c:supported-calendar-component-set' => '<c:comp name="VEVENT" /><c:comp name="VTODO" /><c:comp name="VJOURNAL" /><c:comp name="VFREEBUSY" />',
        'd:supported-report-set'    => '<d:report><d:sync-collection /></d:report><d:report><c:calendar-multiget /></d:report><d:report><c:calendar-query /></d:report><d:report><c:free-busy-query /></d:report>',
        'd:displayname'             => '',
        'd:supported-privilege-set' => [
            ['privilege>', '<c:read-free-busy />'],
            ['privilege>', '<d:read />'],
            ['privilege>', '<d:read-acl />'],
            ['privilege>', '<d:read-current-user-privilege-set />'],
            ['privilege>', '<d:write-properties />'],
            ['privilege>', '<d:write />'],
            ['privilege>', '<d:write-content />'],
            ['privilege>', '<d:unlock />'],
            ['privilege>', '<d:bind />'],
            ['privilege>', '<d:unbind />'],
            ['privilege>', '<d:write-acl />'],
            ['privilege>', '<d:share />']
        ],
        'c:calendar-timezone'    => 'Asia/Shanghai',
        'd:creationdate'         => '',
        'd:getlastmodified'      => '',
        'd:getetag'              => '',
        'd:sync-token'           => '',
    ];

    /**
     * @param $uri
     *
     * @return array
     */
    public function getBaseInfoByUri($uri)
    {
        return $this->getRow($this->fields, ['uri' => $uri]);
    }

    public function addCalendar($info, $prop) {
        if(empty($prop['d:displayname'])){
            $prop['d:displayname'] = basename($info['uri']);
        }
        $prop['d:creationdate'] = gmdate('Y-m-d\TH:i:s\Z', time());
        $prop['d:getlastmodified'] = gmdate('D, d M Y H:i:s', time()) . ' GMT';
        $prop['d:getetag'] = md5(uniqid(mt_rand(), true));
        $prop['c:getctag'] = $prop['d:getetag'];
        $prop['d:sync_token'] = 1;
        $prop = array_merge($this->baseProp, $prop);
        if(!empty($prop['c:supported-calendar-component-set'])) {
            if(is_array($prop['c:supported-calendar-component-set'])) {
                $components = [];
                foreach ($prop['c:supported-calendar-component-set'] as $comp)
                {
                    if ($comp[0] == 'c:comp' && !empty($comp[3])) {
                        $components[] = $comp[3]['name'];
                    }
                }
                $info['component_set'] = implode(',', $components);
            }
        }
        $comp = ['PRODID' => self::PRODID, 'VERSION' => self::VERSION, 'CALSCALE' => self::CALSCALE];
        if(isset($prop['c:calendar-timezone'])){
            $tz = Db::icsToArr(trim($prop['c:calendar-timezone']));
            if(isset($tz['VTIMEZONE'])){
                $comp['timezone'] = $tz['VTIMEZONE'];
                if(empty($props['zid']) && !empty($comp['timezone'][0]['TZID'])) {
                    $info['tzid'] = $comp['timezone'][0]['TZID'];
                }
            }
        }
        $info['owner_id'] = session('uid');
        $info['prop'] = json_encode($prop, JSON_UNESCAPED_UNICODE);
        $info['comp_prop'] = json_encode($comp, JSON_UNESCAPED_UNICODE);
        $info['ics_data'] = '';
        $id = self::query()->insertGetId($info);
        if (!empty($tz['VTIMEZONE'])) {
            $dbTimeZone = TimeZone::getInstance();
            $dbTimeZone->add($id, $tz['VTIMEZONE']);
        }
        return $id;
    }

    public function getPropByOwnerId($ownerId)
    {
        return $this->getData(['id', 'uri', 'prop', 'comp_prop'], [['owner_id', '=', $ownerId]]);
    }

    public function getCompPropById($id)
    {
        $compProp = $this->getColumn('comp_prop', ['id' => $id]);
        $compProp = json_decode($compProp, true);
        return Db::arrToIcs($compProp);
    }

    public function getIcsById($id, $compProp)
    {
        $ics = "BEGIN:VCALENDAR\n" . Db::arrToIcs(json_decode($compProp, true)) . "\n";
        $dbComp = Comp::getInstance();
        $comp = $dbComp->getData(['type', 'ics_data'], ['calendar_id' => $id]);
        foreach ($comp as $v) {
            $compName = array_search($v['type'], Comp::TYPE_MAP);
            $ics .= 'BEGIN:' . $compName . "\n" . $v['ics_data'] . "\n" . 'END:' . $compName . "\n";
        }
        $ics .= "END:VCALENDAR";
        $this->updateSet(['ics_data' => $ics], ['id' => $id]);
        return $ics;
    }

    public function updateProp($id, $prop)
    {
        $prop['d:getlastmodified'] = gmdate('Y-m-d\TH:i:s\Z', time());
        $prop['d:getetag'] = $this->createEtag();
        $prop['d:sync_token'] = $this->createSyncToken();
        $prop['d:getctag'] = $prop['d:getetag'];
        return static::query()->where('id', '=', $id)->update(['prop' => json_encode($prop, JSON_UNESCAPED_UNICODE)]);
    }
    public function updateEtag($id) {
        $syncToken = $this->createSyncToken();
        $etag = $this->createEtag();
        $lastModified = gmdate('D, d M Y H:i:s', time()) . ' GMT';
        $sql = 'UPDATE ' . $this->table . " 
        SET `ics_data`='', `prop`=JSON_SET(`prop`, '$.\"d:getetag\"','" . $etag . "' , '$.\"cs:getctag\"', :etag, '$.\"d:getlastmodified\"', :lastmodified, '$.\"d:sync-token\"', :sync_token)
        WHERE `id`=" . $id;
        $res = \Illuminate\Support\Facades\DB::update($sql, [':etag' => $etag, ':lastmodified' => $lastModified, ':sync_token' => $syncToken]);
        return $res !== false;
    }

    public function createSyncToken() {
        return session('uid') . '-' . time();
    }

    public function createEtag() {
        return session('uid') . '-' . time();
    }
}