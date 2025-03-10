<?php

namespace App\Http\Controllers;

use App\Models\Base\Controller;
use App\Models\Db\Calendar;
use App\Models\Db\Comp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Get extends Controller
{
    public function index(Request $request, $username){
        if($username != session('username')) {
            response(null,Response::HTTP_FORBIDDEN);
        }
        $resource = $request->getRequestUri();
        if (in_array(substr($resource, -4), ['.ics'])) {
            $dbComp = Comp::getInstance();
            $info = $dbComp->getBaseInfoByUri($resource);
            if(empty($info)) {
                return response(null,Response::HTTP_NOT_FOUND);
            }
            $dbCalendar = Calendar::getInstance();
            $ics = "BEGIN:CALENDAR\n" . $dbCalendar->getCompPropById($info['calendar_id']) . "\n";
            $ics .= $dbComp->getIcsByCompUid($info['uid'], $info['type']) . "\n";
            $ics .= 'END:CALENDAR';
            if ($request->method() == 'HEAD') {
                return response(null, Response::HTTP_OK, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Length' => strlen($ics), 'Last-Modified' => $info['last_modified'], 'ETag' => $info['etag']]);
            }
            return response($ics, Response::HTTP_OK, ['Content-Type' => 'text/calendar; charset=utf-8', 'Last-Modified' => $info['last_modified'], 'ETag' => $info['etag']]);
        }
        $dbCalendar = Calendar::getInstance();
        $info = $dbCalendar->getBaseInfoByUri($resource);
        if(empty($info)) {
            return response(null,Response::HTTP_NOT_FOUND);
        }
        if (empty($info['ics_data'])) {
            $info['ics_data'] = $dbCalendar->getIcsById($info['id'], $info['comp_prop']);
        }
        if ($request->method() == 'HEAD') {
            return response(null, Response::HTTP_OK, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Length' => strlen($info['ics_data']), 'Last-Modified' => $info['last_modified'], 'ETag' => $info['etag']]);
        }
        return response($info['ics_data'], Response::HTTP_OK, ['Content-Type' => 'text/calendar; charset=utf-8', 'Last-Modified' => $info['last_modified'], 'ETag' => $info['etag']]);
    }
}