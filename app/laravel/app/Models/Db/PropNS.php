<?php

namespace App\Models\Db;

use App\Models\Base\Db;
use Exception;

class PropNS extends Db
{
    protected $table = 'prop_ns';
    const LIMIT = 256;
    const DAV_ID = 1;
    const CAL_ID = 2;
    const CS_ID  = 3;
    const prefixDict = [
        'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y',
        'Z', 'A', 'B', 'C', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r',
        's', 't', 'u', 'v', 'w', 'x', 'y', 'z'
    ];
    public static array $uriMap = [];
    public static array $nsList = [];
    public static $prefixUri = [
        'd'    => 'DAV:',
        'c'    => 'urn:ietf:params:xml:ns:caldav',
        'cs'   => 'http://calendarserver.org/ns/',
        'ics'  => 'http://icalendar.org/ns/',
        'card' => 'urn:ietf:params:xml:ns:carddav',
        'vc'   => 'urn:ietf:params:xml:ns:vcard'
    ];
    private static $prefixNsId = [];

    protected function __construct(){
        parent::__construct();
    }

    public function init() {
        $arrRes = self::query()->select(['id', 'uri', 'prefix'])->forPage(1, self::LIMIT)->get()->toArray();
        if (is_array($arrRes)) {
            foreach ($arrRes as $ns) {
                self::$prefixUri[$ns['prefix']] = $ns['uri'];
                self::$nsList[$ns['id']] = ['prefix' => $ns['prefix'], 'uri' => $ns['uri']];
                self::$uriMap[$ns['uri']] = $ns['id'];
                self::$prefixNsId[$ns['prefix']] = $ns['id'];
            }
        }
    }
    public static function getInfoByNsId(int $nsId)
    {
        if(empty(self::$nsList)) {
            self::getInstance()->init();
        }
        return self::$nsList[$nsId] ?? [];
    }

    public static function getNsIdByPrefix(string $prefix) {
        if (empty(self::$prefixNsId[$prefix])) {
            self::getInstance()->init();
        }
        return self::$prefixNsId[$prefix];
    }

    /**
     * 根据uri获取命名空间id
     * @param string $uri 命名空间uri
     * @return int|mixed
     * @throws Exception
     */
    public function getNsIdByUri($uri)
    {
        if(empty($uri)) {
            return self::DAV_ID;
        }
        if (isset(self::$uriMap[$uri])) {
            return self::$uriMap[$uri];
        }
        $prefixUris = array_diff(self::prefixDict, array_keys(self::$prefixUri));
        if (empty($prefixUris)) {
            $id = count(self::$prefixNsId) + 1;
            $num = count($this->prefixDict);
            $prefix = $this->prefixDict[$id % $num - 1] . floor($id / $num);
        } else {
            $prefix = array_pop($prefixUris);
        }
        $id = $this->insertGetId(['uri' => $uri, 'prefix' => $prefix]);
        self::$uriMap[$uri] = $id;
        self::$nsList[$id] = ['prefix' => $prefix, 'uri' => $uri];
        self::$prefixUri[$prefix] = $uri;
        return $id;
    }

    /**
     * 根据id查询命名空间信息
     * @param int $id 命名空间id
     * @return array|mixed
     * @throws Exception
     */
    public static function getUriByPrefix($prefix)
    {
        if (empty(self::$prefixUri[$prefix])) {
            self::getInstance()->init();
        }
        return self::$prefixUri[$prefix];
    }

    public static function getPrefixByUri($uri)
    {
        $prefix = array_search($uri, self::$prefixUri);
        if($prefix !== false) {
            return $prefix;
        }
        self::getInstance()->init();
        self::getInstance()->getNsIdByUri($uri);
        return array_search($uri, self::$prefixUri);
    }
}