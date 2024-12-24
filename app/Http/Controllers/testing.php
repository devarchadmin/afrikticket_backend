<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class testing extends Controller
{
    public function test(){
        return response()->json([
            'status' => 'success',
            'message' => 'Test api working from testing controller'
        ]);
    }
}
