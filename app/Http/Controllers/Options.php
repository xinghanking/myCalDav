<?php

namespace App\Http\Controllers;

use App\Models\Base\Controller;
use Illuminate\Http\Response;

class Options extends Controller
{
    public function index(){
        return response('')
        ->withStatus(Response::HTTP_OK)
        ->withHeaders([
            'Allow' => 'OPTIONS, GET, HEAD, MKCALENDAR, PUT, PROPPATCH, DELETE, PROPFIND, REPORT',
            'DAV'   => '1, 3, calendarserver-principal-property-search, calendar-access, calendar-auto-schedule, calendar-availability, sync-collection, calendar-multiget'
        ]);
    }
}