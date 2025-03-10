<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Options extends \App\Models\Base\Controller
{
    public function index(Request $request){
        return response(null, Response::HTTP_OK, [
            'Allow' => 'OPTIONS, GET, HEAD, MKCALENDAR, PUT, PROPPATCH, DELETE, PROPFIND, REPORT',
            'DAV'   => '1, 3, calendarserver-principal-property-search, calendar-access, calendar-auto-schedule, calendar-availability, sync-collection, calendar-multiget'
        ]);
    }
}