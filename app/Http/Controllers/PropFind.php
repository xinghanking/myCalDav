<?php

namespace App\Http\Controllers;

use App\Models\Base\Controller;
use App\Models\Db\Calendar;
use App\Models\Db\Comp;
use App\Models\Db\PropNS;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PropFind extends Controller
{
    private $baseProp = [];

    public function index(Request $request)
    {
        $uri = $request->getRequestUri();
        $depth = $request->header('Depth') ?? 0;
        if (!is_numeric($depth)) {
            $depth = -1;
        }
        $xml = trim($request->getContent());
        $xml = $this->formatReqXml($xml);
        $xml = str_replace(['<d:prop>', '</d:prop>'], ['<prop>', '</prop>'], $xml);
        $findProp = [];
        if (preg_match('/<(d:)?allprop\s*\/>/is', $xml)){
            $reqType = 'allprop';
        } elseif(preg_match('/<(d:)?propname\s*\/>/is', $xml)) {
            $reqType = 'propname';
        } elseif(preg_match('/<prop>(.*)<\/prop>/is', $xml, $xmlMatches)) {
            $reqType = 'prop';
            if (empty($xmlMatches[1])) {
                return response('', Response::HTTP_BAD_REQUEST);
            }
            preg_match_all('/<(.+?)\s*\/>/s', $xmlMatches[1], $matches);
            if (empty($matches[1])) {
                return response('', Response::HTTP_BAD_REQUEST);
            }
            foreach ($matches[1] as $tagName) {
                $tagName = trim($tagName);
                $start = strpos($tagName, 'xmlns="');
                if($start !== false) {
                    $prefix = PropNS::getPrefixByUri(substr($tagName,$start + 7, -1));
                    $findProp[$prefix.':'.trim(substr($tagName, 0, $start-1))] = '';
                } else {
                    $findProp[trim(str_contains($tagName, ':') ? $tagName : 'd:'.$tagName)] = '';
                }
            }
        } else {
            return response(null, Response::HTTP_BAD_REQUEST);
        }
        $baseProp = [
            'd:current-user-principal'    => ['current-user-principal', [['href', '/principals/' . session('username') . '/']]],
            'd:displayname'               => ['displayname', basename($uri)],
            'c:calendar-home-set'         => ['calendar-home-set', [['href', '/' . session('username') . '/calendars/']], PropNs::CAL_ID],
            'd:resourcetype'              => ['resourcetype', '<d:collection/>'],
            'c:calendar-user-address-set' => ['calendar-user-address-set', '<d:href>mailto:' .session('email') . '</d:href><d:href>/' . session('username') . '/</d:href>', PropNs::CAL_ID],
            'c:supported-calendar-component-set' => ['supported-calendar-component-set', '<c:comp name="VEVENT" /><c:comp name="VTODO" /><c:comp name="VJOURNAL" /><c:comp name="VFREEBUSY" />', PropNs::CAL_ID],
            'c:calendar-timezone'          => ['calendar-timezone', 'Asia/Shanghai', PropNs::CAL_ID],
            'd:current-user-privilege-set' => ['current-user-privilege-set', '<d:privilege><d:all/></d:privilege><d:privilege><c:read-free-busy/></d:privilege><d:privilege><d:read/></d:privilege><d:privilege><d:read-acl/></d:privilege><d:privilege><d:read-current-user-privilege-set/></d:privilege><d:privilege><d:write-properties/></d:privilege><d:privilege><d:write/></d:privilege><d:privilege><d:write-content/></d:privilege><d:privilege><d:unlock/></d:privilege><d:privilege><d:bind/></d:privilege><d:privilege><d:unbind/></d:privilege><d:privilege><d:write-acl/></d:privilege><d:privilege><d:share/></d:privilege>'],
            'd:owner'                     => ['owner', [['href', '/principals/' . session('username') . '/']]]
        ];
        if (rtrim($uri, '/') == '/principals/' . session('username')) {
            $baseProp['d:resourcetype'] = ['resourcetype', [['principal']]];
        }
        if (rtrim($uri, '/') == '/' . session('username') . '/calendars') {
            $baseProp['d:supported-report-set'] = ['supported-report-set', '<d:report><d:sync-collection /></d:report><d:report><c:calendar-multiget /></d:report><d:report><c:calendar-query /></d:report><d:report><c:free-busy-query /></d:report>'];
            $baseProp['d:sync-token'] = ['sync-token', ''];
        }
        $this->baseProp = $baseProp;
        if (!str_starts_with($uri, '/' . session('username') . '/calendars/') || rtrim($uri, '/') == '/' . session('username') . '/calendars') {
            if ($reqType == 'propname') {
                $prop = $this->getPropName($baseProp);
            } elseif ($reqType == 'allprop') {
                $prop = $baseProp;
            } else {
                $prop = array_intersect_key($baseProp, $findProp);
                $missProp = array_diff_key($findProp, $baseProp);
            }
            $propStat = [['href', $uri]];
            if (!empty($prop)) {
                $propStat[] = ['propstat', [['prop', array_values($prop)], ['status', 'HTTP/1.1 200 OK']]];
            }
            if (!empty($missProp)) {
                $propStat[] = ['propstat', [['prop', $this->getPropName($missProp)], ['status', 'HTTP/1.1 404 Not Found']]];
            }
            $response = [['response', $propStat]];
            if ($uri == '/' && $depth != 0) {
                $uri = '/' . session('username') . '/';
                --$depth;
                $response[] = ['response', array_merge([['href', $uri]], $propStat)];
            }
            if (rtrim($uri, '/') == '/' . session('username') && $depth != 0) {
                $response[] = ['response', array_merge([['href', '/' . session('username') . '/principals/']], $propStat)];
                --$depth;
                $uri = '/' . session('username') . '/calendars/';
                $response[] = ['response', array_merge([['href', $uri]], $propStat)];
            }
            if (rtrim($uri, '/') == '/' . session('username') . '/calendars' && $depth != 0) {
                $dbCalendar = Calendar::getInstance();
                $arrCalendar = $dbCalendar->getPropByOwnerId(session('uid'));
                if (!empty($arrCalendar)) {
                    --$depth;
                    foreach ($arrCalendar as $v) {
                        $response = array_merge($response, $this->getCalendarProp($v, $reqType, $depth, $findProp));
                    }
                }
            }
        } elseif(str_ends_with($uri, '.ics')) {
            $dbComp = Comp::getInstance();
            $info = $dbComp->getBaseInfoByUri($uri);
            if (empty($info)) {
                return response(null, Response::HTTP_NOT_FOUND);
            }
            $response = $this->getCompProp($info, $reqType, $findProp);
        } else {
            $dbCalendar = Calendar::getInstance();
            $info = $dbCalendar->getBaseInfoByUri($uri);
            if (empty($info)) {
                return response(null, Response::HTTP_NOT_FOUND);
            }
            $response = $this->getCalendarProp($info, $reqType, $depth, $findProp);
        }
        $response = $this->xml_encode(['multistatus', $response]);
        return response($response, Response::HTTP_MULTI_STATUS, ['Content-Type' => 'application/xml', 'DAV'  => '1, 3, calendarserver-principal-property-search, calendar-access, calendar-auto-schedule, calendar-availability, sync-collection, calendar-multiget']);
    }

    protected function getCalendarProp($info, $type, $depth, $findProp)
    {
        $info['prop'] = array_merge($this->baseProp, $this->getProp($info['prop']));
        if ($type == 'propname') {
            $prop = $this->getPropName($info['prop']);
        } else {
            if (empty($findProp)) {
                $prop = $info['prop'];
            } else {
                $prop = array_intersect_key($info['prop'], $findProp);
                $missProp = array_diff_key($findProp, $info['prop']);
            }
        }
        if(!empty($prop)) {
            $response = [
                ['href', $info['uri']],
                ['propstat', [['prop', array_values($prop)], ['status', 'HTTP/1.1 200 OK']]],
            ];
        }
        if (!empty($missProp)) {
            $response[] = ['propstat', [['prop', $this->getPropName($missProp)], ['status', 'HTTP/1.1 404 Not Found']]];
        }
        $response = [['response', $response]];
        if ($depth != 0) {
            $dbComp = Comp::getInstance();
            $arrComps = $dbComp->getInfoByCalendarId($info['id']);
            if (!empty($arrComps)) {
                foreach ($arrComps as $comp) {
                    $response = array_merge($response, $this->getCompProp($comp, $type, $findProp));
                }
            }
        }
        return $response;
    }

    private function getCompProp($info, $type, $findProp)
    {
        $allProp = array_merge($this->baseProp, $this->getProp($info['prop']));
        $response = [['href', $info['uri']]];
        if ($type == 'propname') {
            $prop = $this->getPropName($allProp);
            $response[] = ['propstat', [['prop', $prop], ['status', 'HTTP/1.1 200 OK']]];
            return [['response', $response]];
        }
        if (empty($findProp)) {
            $prop = array_values($allProp);
        } else {
            $prop = array_values(array_intersect_key($allProp, $findProp));
            $missProp = $this->getPropName(array_diff_key($findProp, $allProp));
        }
        if (!empty($prop)) {
            $response[] = ['propstat', [['prop', $prop], ['status', 'HTTP/1.1 200 OK']]];
        }
        if(!empty($missProp)){
            $response[] = ['propstat', [['prop', $missProp], ['status', 'HTTP/1.1 404 Not Found']]];
        }
        return [['response', $response]];
    }

    private function getProp($prop)
    {
        $prop = json_decode($prop, true);
        if (isset($prop['c:calendar-timezone'])) {
            unset($prop['c:calendar-timezone']);
        }
        foreach ($prop as $k => $v) {
            [$prefix, $localName] = explode(':', $k, 2);
            $prop[$k] = [$localName, $v, PropNS::getNsIdByPrefix($prefix)];
        }
        return $prop;
    }
    private function getPropName($prop) {
        foreach ($prop as $k => $v) {
            [$prefix, $localName] = explode(':', $k, 2);
            $prop[$k] = [$localName, '', PropNS::getNsIdByPrefix($prefix)];
        }
        return $prop;
    }
}