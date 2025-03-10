<?php

namespace App\Http\Controllers;

use App\Models\Base\Controller;
use App\Models\Base\Db;
use App\Models\Db\CalChange;
use App\Models\Db\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $userInfo = [
            'username' => 'lisi',
            'password' => md5('zhangsan:123456'),
            'email' => 'lis@gmail.com',
        ];
        $data = [
            'ics_sequence' => json_encode(['a' => 1, 'b' => 2, 'b1' => 3]),
            'sync_token' => 'c'
        ];
        $objUser = User::getInstance();
        $objChange = CalChange::getInstance();
        Db::beginTransaction();
        $uid = $objUser->addUser($userInfo);
        Db::commit();
        return response()->json(['uid' => $uid], 200);
    }
}
