<?php

namespace App\Services;

use App\Models\Bail;
use App\Models\User;

class UserServices
{
    public function index()
    {
        return User::all();
    }

}
