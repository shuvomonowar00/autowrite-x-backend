<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'test_param' => 'required|string|max:255',
            'test_number' => 'required|integer|min:1|max:100',
            'test_email' => 'required|email',
        ]);

        return response()->json([
            'message' => 'Test successful',
            'data' => [
                'test_param' => $request->input('test_param'),
                'test_number' => $request->input('test_number'),
                'test_email' => $request->input('test_email'),
            ]
        ], 200);
    }
}
