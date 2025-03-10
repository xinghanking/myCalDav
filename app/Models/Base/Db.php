<?php

namespace App\Models\Base;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;

class Db extends Model
{
    protected $table = '';
    private static $instance;
    public $timestamps = false;

    public $fillable = [];

    protected function __construct(){
        parent::__construct();
    }

    /**
     * @return mixed|Model
     */
    public static function getInstance(){
        if (!(static::$instance instanceof static)){
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __clone()
    {
    }
    public static function beginTransaction(){
        self::query()->getConnection()->beginTransaction();
    }

    public static function commit(){
        self::query()->getConnection()->commit();
    }

    public static function rollBack(){
        self::query()->getConnection()->rollBack();
    }
    public function getRow($columns, $where) {
        $wh = [];
        foreach($where as $k => $v){
            $wh[] = [$k, '=', $v];
        }
        $res = static::query()->select($columns)->where($wh)->orderBy('id', 'asc')->first();
        if($res === null){
            return null;
        }
        return $res->toArray();
    }

    public function getColumn($column, $where = [])
    {
        $wh = [];
        foreach($where as $k => $v){
            $wh[] = [$k, '=', $v];
        }
        return static::query()->select($column)->where($wh)->get()->value($column);
    }

    public function getCount($where = [])
    {
        return static::query()->where($where)->count();
    }
    public function getData($columns, $where = [], $orderBy = null, $page = 1, $limit = 500){
        return static::query()->select($columns)->where($where)->orderBy($orderBy ?? 'id')->forPage($page, $limit)->get()->toArray();
    }

    public function insertGetId(array $values)
    {
        return static::query()->insertGetId($values);
    }

    public function insertOrUpdate(array $values, array $where) {
        return static::query()->updateOrCreate($where, $values)->newUniqueId();
    }

    public function del($where = [])
    {
        return $this->query()->where($where)->forceDelete();
    }

    public function batchInsert(array $values){
        return static::query()->insert($values);
    }

    public function updateSet(array $values, array $where = [])
    {
        return static::query()->where($where)->update($values);
    }

    public static function icsToArr(string $ics)
    {
        $info            = [];
        $stack           = [];
        $currentCompName = '';
        $currentNode     = &$info;
        if(!str_starts_with($ics, 'BEGIN:VCALENDAR') || substr($ics, -13) != 'END:VCALENDAR') {
            return null;
        }
        $ics = trim(substr($ics,15, -13));
        $ics = preg_split("/\r?\n/", $ics);
        if (empty($ics)){
            return null;
        }
        $currentKey = '';
        $currentValue = '';
        foreach ($ics as $c) {
            if(empty($c)) {
                continue;
            }
            if (in_array(substr($c, 0, 1),[' ', "\t"], true)) {
                if(!empty($currentKey) && is_string($currentValue)) {
                    $c = substr($c, 1);
                    $currentValue .= $c == '' ? "\n" : $c;
                    continue;
                }
            }
            if(!empty($currentKey)) {
                $key = explode(';', $currentKey);
                if (count($key) > 1) {
                    $currentKey = array_shift($key);
                    $currentValue = ['p' => [], 'v' => $currentValue];
                    foreach ($key as $p) {
                        [$k, $v] = explode('=', $p);
                        $currentValue['p'][$k] = $v;
                    }
                }
                if (empty($currentCompName)) {
                    $info['VCALENDAR'][$currentKey] = $currentValue;
                } else {
                    if (isset($currentNode[$currentKey])) {
                        if (is_array($currentNode[$currentKey]) && !isset($currentNode[$currentKey]['v'])) {
                            $currentNode[$currentKey][] = $currentValue;
                        } else {
                            $currentNode[$currentKey] = [$currentNode[$currentKey], $currentValue];
                        }
                    } else {
                        $currentNode[$currentKey] = $currentValue;
                    }
                }
                $currentKey = '';
                $currentValue = null;
            }
            if (empty(!$c) && str_contains($c, ':')) {
                [$key, $value] = explode(':', $c, 2);
                if ($key == 'BEGIN') {
                    $stack[]            = [$currentCompName, &$currentNode];
                    $currentCompName    = $value;
                    if(isset($currentNode[$value])) {
                        $currentNode[$value][] = [];
                    } else {
                        $currentNode[$value] = [[]];
                    }
                    $currentNode        = &$currentNode[$value][count($currentNode[$value]) - 1];
                } elseif ($key == 'END') {
                    if ($currentCompName != $value) {
                        return null;
                    }
                    $a               = array_pop($stack);
                    $currentNode     = &$a[1];
                    $currentCompName = $a[0];
                } else {
                    $currentKey = $key;
                    $currentValue = $value;
                }
            }
        }
        return $info;
    }

    public static function arrToIcs(array $arr) {
        $comp = [];
        if (isset($arr['RECURRENCE-ID']) && $arr['RECURRENCE-ID'] == '') {
            unset($arr['RECURRENCE-ID']);
        }
        foreach ($arr as $k => $value) {
            if (is_array($value)) {
                if(isset($value['p'])) {
                    foreach ($value['p'] as $p => $v) {
                        $value['p'][$p] = $p . '=' . $v;
                    }
                    $comp[] = $k . ';' . implode(';', $value['p']) . ':' . $value['v'];
                } elseif (isset($value[0])) {
                    if (is_array($value[0])) {
                        if(isset($value[0]['p'])) {
                            foreach($value as $item) {
                                foreach ($item['p'] as $p => $v) {
                                    $item['p'][$p] = $p . '=' . $v;
                                }
                                $comp[] = $k . ';' . implode(';', $item['p']) . ':' . $item['v'];
                            }
                        } else {
                            foreach ($value as $v) {
                                $comp[] = 'BEGIN:'."$k\n".self::arrToIcs($v) . "\nEND:"."$k";
                            }
                        }
                    } else {
                        foreach ($value as $v) {
                            $comp[] = $k.':'.$v;
                        }
                    }
                }
            } else {
                $comp[] = $k.':'.$value;
            }
        }
        return implode("\n", $comp);
    }
}