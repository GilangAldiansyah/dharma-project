<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $afterId = $request->query('after_id', 0);

        $notifications = AppNotification::where('id', '>', $afterId)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'data'       => $n->data,
                'is_read'    => $n->is_read,
                'created_at' => $n->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $notifications,
        ]);
    }

    public function unreadCount()
    {
        return response()->json([
            'success' => true,
            'count'   => AppNotification::where('is_read', false)->count(),
        ]);
    }

    public function markAllRead()
    {
        AppNotification::where('is_read', false)->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}
