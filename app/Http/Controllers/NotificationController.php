<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationFilterRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(NotificationFilterRequest $request, NotificationService $notifications): View|JsonResponse
    {
        $data = $notifications->overview($request->validated());
        if ($request->expectsJson()) {
            $page = $data['notifications'];

            return response()->json([
                'html' => view('notifications.partials.feed', $data)->render(),
                'counts' => $data['counts'], 'total' => $data['total'],
                'page_url' => $page->currentPage() > $page->lastPage() ? $page->url($page->lastPage()) : null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return view('notifications.index', $data);
    }
}
