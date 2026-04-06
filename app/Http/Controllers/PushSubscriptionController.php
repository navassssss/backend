<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PushSubscriptionController extends Controller
{
    public function __construct(private readonly WebPushService $push) {}

    /**
     * Save/update a push subscription for the authenticated user.
     * Called by the frontend after Notification.requestPermission() is granted.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|url',
            'keys'     => 'nullable|array',
            'keys.p256dh' => 'nullable|string',
            'keys.auth'   => 'nullable|string',
        ]);

        $user = $request->user();

        PushSubscription::updateOrCreate(
            ['endpoint' => $request->endpoint],
            [
                'user_id'    => $user->id,
                'p256dh_key' => $request->input('keys.p256dh'),
                'auth_token' => $request->input('keys.auth'),
                'user_agent' => $request->userAgent(),
            ]
        );

        return response()->json(['message' => 'Subscription saved'], 201);
    }

    /**
     * Remove a push subscription (user un-subscribed in browser).
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|url']);

        PushSubscription::where('endpoint', $request->endpoint)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Unsubscribed']);
    }

    /**
     * Return the VAPID public key so the frontend can subscribe.
     * This endpoint is PUBLIC (no auth required).
     */
    public function vapidKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('webpush.vapid_public_key'),
        ]);
    }

    /**
     * Test: send a push notification to the authenticated user.
     * Only available in non-production environments.
     */
    public function test(Request $request): JsonResponse
    {
        if (app()->isProduction()) {
            return response()->json(['message' => 'Not available in production'], 403);
        }

        $this->push->sendToUser($request->user()->id, [
            'title' => 'DHIC Test Notification',
            'body'  => 'Push notifications are working! 🎉',
            'url'   => '/',
            'tag'   => 'dhic-test',
        ]);

        return response()->json(['message' => 'Test notification sent']);
    }

    /**
     * Send a notification to specific users (Principal use).
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array',
            'title'    => 'required|string|max:100',
            'body'     => 'required|string|max:300',
            'url'      => 'nullable|string',
        ]);

        $this->push->sendToUsers($request->user_ids, [
            'title' => $request->title,
            'body'  => $request->body,
            'url'   => $request->input('url', '/'),
            'tag'   => 'dhic-admin-' . time(),
        ]);

        return response()->json(['message' => 'Notifications dispatched']);
    }
}
