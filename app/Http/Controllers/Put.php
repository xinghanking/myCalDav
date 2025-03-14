<?php

namespace App\Http\Controllers;

use App\Models\Base\Controller;
use App\Models\Base\Db;
use App\Models\Db\Calendar;
use App\Models\Db\Comp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Put extends Controller
{
    public function index(Request $request, $username)
    {
        if ($username != session('username')) {
            return response('', Response::HTTP_FORBIDDEN);
        }
        if (empty($request->header('content-length'))) {
            return response('',Response::HTTP_BAD_REQUEST);
        }
        $uri = $request->getRequestUri();
        if (empty($request->header('content-type')) || strtok($request->header('content-type'), ';') != 'text/calendar' || !in_array(substr($uri, -4), ['.ics', '.ifb'])) {
            return response('<?xml version="1.0" encoding="UTF-8"?>
<d:error xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav"><d:supported-media-type><d:mediatype>text/calendar</d:mediatype></d:supported-media-type><cal:supported-calendar-data><cal:calendar-data content-type="text/calendar" version="2.0"/></cal:supported-calendar-data></d:error>', 415);
        }
        $ics = Db::icsToArr(trim($request->getContent()));
        if (empty($ics)) {
            return response('',Response::HTTP_BAD_REQUEST);
        }
        $dbCalendar = Calendar::getInstance();
        $upper = $dbCalendar->getBaseInfoByUri(dirname($uri) . '/');
        if (empty($upper)) {
            return response('',Response::HTTP_NOT_FOUND);
        }
        $dbCalComp = Comp::getInstance();
        $info      = $dbCalComp->getBaseInfoByUri($uri);
        if ($info === false) {
            return response('',Response::HTTP_SERVICE_UNAVAILABLE);
        }
        $tz  = $ics['VTIMEZONE'] ?? [];
        $ics = array_intersect_key($ics, Comp::TYPE_MAP);
        if (count($ics) > 1) {
            return response('',Response::HTTP_CONFLICT);
        }
        $type          = Comp::TYPE_MAP[key($ics)];
        $ics           = current($ics);
        $compUid       = '';
        $recurrenceIds = [];
        foreach ($ics as $item) {
            if ($compUid != '' && $compUid != $item['UID']) {
                return response('',Response::HTTP_BAD_REQUEST);
            }
            $compUid = $item['UID'];
            $item['RECURRENCE-ID'] = $dbCalComp->formatCurrenceId($item['RECURRENCE-ID'] ?? '');
            if (in_array($item['RECURRENCE-ID'], $recurrenceIds)) {
                return response('',Response::HTTP_BAD_REQUEST);
            }
            $recurrenceIds[] = $item['RECURRENCE-ID'];
        }
        if (empty($info)) {
            $obj = $dbCalComp->getRow('id', ['calendar_id' => $upper['id'], 'uid' => $compUid, 'recurrence_id' => '']);
            if (!empty($obj)) {
                return response('',Response::HTTP_CONFLICT);
            }
            $dbCalendar->beginTransaction();
            $dbCalComp->addObject($uri, $upper['id'], $type, $ics);
            $dbCalendar->updateEtag($upper['id']);
            $dbCalendar->commit();
            return response('',Response::HTTP_CREATED);
        } else {
            if ($type != $info['comp_type'] || $compUid != $info['uid']) {
                return response(null,409);
            }
            $instances = $dbCalComp->getData(['id', 'recurrence_id'], ['`calendar_id`=' => $upper['id'], '`uid`=' => $compUid, '`recurrence_id` IN ' => $recurrenceIds]);
            if (empty($instances)) {
                $dbCalComp->beginTransaction();
                $dbCalComp->addObject($uri, $upper['id'], $type, $ics);
                $dbCalComp->updateEtag($uri);
                $dbCalendar->updateEtag($info['calendar_id']);
                $dbCalComp->commit();
            } else {
                $existIns = [];
                foreach ($instances as $instance) {
                    $existIns[$instance['recurrence_id']] = $instance['id'];
                }
                $newIns = [];
                foreach ($ics as $item) {
                    $recurrenceId = $dbCalComp->formatCurrenceId($item['RECURRENCE-ID'] ?? '');
                    if (isset($existIns[$recurrenceId])) {
                        $existIns[$recurrenceId] = ['id' => $existIns[$recurrenceId], 'ics' => $item];
                    } else {
                        $newIns[] = $item;
                    }
                }
                $dbCalComp->beginTransaction();
                foreach ($existIns as $ins) {
                    $dbCalComp->updateInstance($ins['id'], $ins['ics']);
                }
                if (!empty($newIns)) {
                    $dbCalComp->addObject($uri, $upper['id'], $type, $newIns);
                }
                $dbCalComp->updateEtag($uri);
                $dbCalendar->updateEtag($info['calendar_id']);
                $dbCalComp->commit();
            }
            return response('',Response::HTTP_OK);
        }
    }
}