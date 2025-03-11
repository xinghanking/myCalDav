<?php

namespace App\Http\Controllers;

use App\Models\Base\Controller;
use App\Models\Base\Db;
use App\Models\Db\Calendar;
use App\Models\Db\Comp;
use App\Models\Db\PropNS;
use App\Models\Db\SyncCollect;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Report extends Controller
{
    public function index(Request $request, $username) {
        if($username != session('username')) {
            response(null,Response::HTTP_FORBIDDEN);
        }
        $uri = $request->getRequestUri();
        $dbCalendar = Calendar::getInstance();
        $info = $dbCalendar->getBaseInfoByUri($uri);
        if (empty($info)) {
            return response(null, Response::HTTP_NOT_FOUND);
        }
        $xml = trim($request->getContent());
        $xml = $this->formatReqXml($xml);
        $xml = str_replace(['<d:sync-collection', '</d:sync-collection', '<c:calendar-multiget', '</c:calendar-multiget>', '<d:prop>', '</d:prop>'], ['<sync-collection', '</sync-collection', '<calendar-multiget', '</calendar-multiget>', '<prop>', '</prop>'], $xml);
        if(str_ends_with($xml, '</sync-collection>')) {
            $reqType = 'sync-collection';
        } elseif(str_ends_with($xml, '</calendar-multiget>')) {
            $reqType = 'calendar-multiget';
        } else {
            return response(null, Response::HTTP_NOT_FOUND);
        }
        //if(in_array($reqType, ['sync-collection', 'calendar-multiget', 'calendar-query'])) {
            preg_match('/<prop[^>]*>(.*)<\/prop>/is', $xml, $xmlMatches);
            if (empty($xmlMatches[1])) {
                return response(null, Response::HTTP_BAD_REQUEST);
            }
            preg_match_all('/<([^\/]+)\s*\/>/', $xmlMatches[1], $matches);
            if (empty($matches[1])) {
                return response(null, Response::HTTP_BAD_REQUEST);
            }
            $findProp = [];
            foreach ($matches[1] as $tagName) {
                $tagName = trim($tagName);
                $n = strpos($tagName, 'xmlns="');
                if($n !== false) {
                    $prefix = PropNS::getPrefixByUri(substr($tagName, $n + 7, -1));
                    $findProp[$prefix . ':' . trim(substr($tagName, 0, $n-1))] = '';
                } else {
                    $findProp[trim(str_contains($tagName, ':') ? $tagName : 'd:'.$tagName)] = '';
                }
            }
            $dbComp = Comp::getInstance();
            if($reqType == 'calendar-multiget') {
               $xml = str_replace(['<d:href', '</d:href>'], ['<href', '</href>'], $xml);
                preg_match_all('/<href[^>]*>\s*([^\s]+)\s*<\/href>/is', $xml, $matches);
                if (empty($matches[1])) {
                    return response(null, Response::HTTP_BAD_REQUEST);
                }
                $data = $dbComp->query()->select(['uri', 'uid', 'type', 'prop'])->whereIn('uri', $matches[1])->forPage(1, count($matches[1]))->get()->toArray();
            } else {
                preg_match('/<sync-token>(.+)<\/sync-token>/', str_replace('d:sync-token', 'sync-token', $xml), $xmlMatches);
                if (empty($xmlMatches[1]) || $xmlMatches[1] != $info['sync_token']) {
                    $num  = $dbComp->getCount([['calendar_id', '=', $info['id']], ['recurrence_id', '=', '']]);
                    $data = [];
                    if ($num > 0) {
                        $data = $dbComp->getData(['uri', 'uid', 'prop', 'type', 'sequence'], [['calendar_id', '=', $info['id']], ['recurrence_id', '=', '']], 'id', 1, $num);
                        $current = [];
                        foreach ($data as $comp) {
                            $current[$comp['uri']] = $comp['sequence'];
                        }
                        $dbSyncCollect = SyncCollect::getInstance();
                        $client = $dbSyncCollect->getCollectBySyncToken($xmlMatches[1] ?? '');
                        if(!empty($client)) {
                            $current = array_diff_assoc($current, $client);
                            $delComps = array_keys(array_diff_key($client, $current));
                        }
                        preg_match('/<nresults>>(.+)<\/nresults>>/', str_replace('d:nresults>', 'nresults>', $xml), $limit);
                        $size = $limit[1] ?? 100;
                        $syncToken = $info['sync_token'];
                        if($size < count($current)) {
                            $current = array_slice($current, 0, $size);
                            $client = array_merge($client, $current);
                            $syncToken = 0;
                        } elseif(!empty($delComps)) {
                            $delNum = $size - count($current);
                            if($delNum > 0 && $delNum < count($delComps)) {
                                $delComps = array_slice($delComps, 0, $delNum);
                                $client = array_diff_key($client, $delComps);
                                $syncToken = 0;
                            }
                        }
                        foreach ($data as $k => $comp) {
                            if(!isset($current[$comp['uri']])) {
                                unset($data[$k]);
                            }
                        }
                    }
                }
            }
            $multiStatus = [];
            if(!empty($data)) {
                if (isset($findProp['c:calendar-data'])) {
                    $info['comp_prop'] = json_decode($info['comp_prop'], true);
                    $ics = "<![CDATA[BEGIN:VCALENDAR\n" . Db::arrToIcs($info['comp_prop']) . "\n";
                    foreach($data as $comp) {
                        $response = [['href', $comp['uri']]];
                        $comp['prop'] = json_decode($comp['prop'], true);
                        $comp['prop']['c:calendar-data'] = $ics . $dbComp->getIcsByCompUid($info['id'], $comp['uid'], $comp['type']) . "\nEND:VCALENDAR\n]]>";
                        $prop = array_intersect_key($comp['prop'], $findProp);
                        $response[] = ['propstat', [['prop', $this->formatProps($prop)], ['status', 'HTTP/1.1 200 OK']]];
                        $missProp = array_diff_key($findProp, $comp['prop']);
                        if(!empty($missProp)) {
                            $response[] = ['propstat', [['prop', $this->formatProps($missProp)], ['status', 'HTTP/1.1 404 Not Found']]];
                        }
                        $multiStatus[] = ['response', $response];
                    }
                } else {
                    foreach($data as $comp) {
                        $response = [['href', $comp['uri']]];
                        $comp['prop'] = json_decode($comp['prop'], true);
                        $prop = array_intersect_key($comp['prop'], $findProp);
                        if(empty($prop)) {
                            $missProp = $findProp;
                        } else {
                            $response[] = ['propstat', [['prop', $this->formatProps($prop)], ['status', 'HTTP/1.1 200 OK']]];
                            $missProp = array_diff_key($findProp, $comp['prop']);
                        }
                        if(!empty($missProp)) {
                            $response[] = ['propstat', [['prop', $this->formatProps($missProp)], ['status', 'HTTP/1.1 404 Not Found']]];
                        }
                        $multiStatus[] = ['response', $response];
                    }
                }
            }
            if($reqType == 'sync-collection') {
                if(empty($client)){
                    $syncToken = $info['sync_token'];
                } else {
                    $id = $dbSyncCollect->addRecord($info['id'], $client, $syncToken);
                    if($syncToken == 0) {
                        $syncToken = $id;
                    }
                }
                $multiStatus[] = ['sync-token', $syncToken];
            }
            $multiStatus = ['multistatus', $multiStatus];//}
        $res = $this->xml_encode($multiStatus);
        file_put_contents('/home/web/1', $res, FILE_APPEND);
        return response($res, Response::HTTP_MULTI_STATUS, ['Content-type' => 'text/xml; charset=utf-8']);
    }

    protected function formatProps($props)
    {
        foreach ($props as $tagName => $value) {
            [$prefix, $localName] = explode(':', $tagName, 2);
            $props[$tagName] = [$localName, $value, PropNs::getNsIdByPrefix($prefix)];
        }
        return array_values($props);
    }
}