<?php

namespace App\Http\Controllers;

use App\Models\Base\Controller;
use App\Models\Base\Db;
use App\Models\Db\Calendar;
use App\Models\Db\Comp;
use App\Models\Db\TimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Delete extends Controller
{
    public function index(Request $request, $username)
    {
        if($username != session('username')) {
            response(null,Response::HTTP_FORBIDDEN);
        }
        $uri = $request->getRequestUri();
        try{
            $dbCalendar = Calendar::getInstance();
            if (str_ends_with($uri, '.ics')) {
                $dbComp = Comp::getInstance();
                $info = $dbComp->getBaseInfoByUri($uri);
                if (empty($info)) {
                    return response(null,Response::HTTP_NOT_FOUND);
                }
                Db::beginTransaction();
                $dbComp->del([['uid', '=', $info['uid']]]);
                $dbCalendar->updateEtag($info['calendar_id']);
                Db::commit();
            } else {
                $info = $dbCalendar->getBaseInfoByUri($uri);
                if (empty($info)) {
                    return response(null,Response::HTTP_NOT_FOUND);
                }
                Db::beginTransaction();
                $dbCalendar->del([['id', '=', $info['id']]]);
                $dbComp = Comp::getInstance();
                $dbComp->del([['calendar_id', '=', $info['id']]]);
                $dbTimeZone = TimeZone::getInstance();
                $dbTimeZone->del([['calendar_id', '=', $info['id']]]);
                Db::commit();
            }
            return response(null,Response::HTTP_OK);
        } catch (\Throwable $th) {
            if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
                Db::rollBack();
            }
            return response(null,Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}