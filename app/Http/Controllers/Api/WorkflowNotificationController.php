<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class WorkflowNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->serialize($notification));

        return response()->json([
            'data' => $notifications,
            ...$this->unreadCounts($request),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return response()->json([
            'data' => $this->serialize($notification->fresh()),
            ...$this->unreadCounts($request),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'unread_count' => 0,
            'approval_unread_count' => 0,
            'approval_unread_counts' => [
                'main' => 0,
                'needs' => 0,
                'invoices' => 0,
            ],
        ]);
    }

    private function unreadCounts(Request $request): array
    {
        $unread = $request->user()
            ->unreadNotifications()
            ->get(['id', 'data']);

        $approval = $unread->filter(function (DatabaseNotification $notification): bool {
            $url = (string) data_get($notification->data, 'url', '');

            return str_starts_with($url, '/approval');
        });

        return [
            'unread_count' => $unread->count(),
            'approval_unread_count' => $approval->count(),
            'approval_unread_counts' => [
                'main' => $approval->filter(fn (DatabaseNotification $notification): bool => data_get($notification->data, 'url') === '/approval')->count(),
                'needs' => $approval->filter(fn (DatabaseNotification $notification): bool => str_starts_with((string) data_get($notification->data, 'url', ''), '/approval/needs'))->count(),
                'invoices' => $approval->filter(fn (DatabaseNotification $notification): bool => str_starts_with((string) data_get($notification->data, 'url', ''), '/approval/invoices'))->count(),
            ],
        ];
    }

    private function serialize(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'data' => $notification->data,
            'created_at' => $notification->created_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
        ];
    }
}
