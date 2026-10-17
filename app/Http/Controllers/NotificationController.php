<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationFilterRequest;
use App\Services\NotificationService;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(NotificationFilterRequest $request, NotificationService $notifications): View
    {
        return view('notifications.index', $notifications->overview($request->validated()));
    }
}
