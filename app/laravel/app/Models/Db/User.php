<?php

namespace App\Models\Db;

use App\Models\Base\Db;

class User extends Db
{
    protected $table = 'user';
    protected $primaryKey = 'id';

    public $fillable = ['id', 'username', 'email', 'password'];
    protected $hidden = ['password'];

    public function getInfoByUserAndPass($username, $password)
    {
        return $this->getRow($this->fillable, ['username' => $username, 'password' => $password]);
    }
    public function addUser($userInfo) {
       return self::query()->insertGetId($userInfo);
    }

    public function delUser($user) {
        return self::query()->where('username', '=', $user)->delete();
    }

    public function isExistUser($username) {
        return self::query()->where('username', '=', $username)->exists();
    }
}