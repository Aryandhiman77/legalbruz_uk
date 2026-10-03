<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentStatusNotification;

class NotificationService
{
    /**
     * Send payment approval notification
     */
    public static function sendPaymentApprovedNotification($payment)
    {
        $user = $payment->user;
        
        // Create in-app notification
        Notification::create([
            'user_id' => $user->id,
            'type' => 'payment_approved',
            'title' => '✅ Payment Approved',
            'message' => 'Your payment of £' . number_format($payment->amount, 2) . ' has been approved successfully!',
            'data' => [
                'payment_id' => $payment->id,
                'application_id' => $payment->application_id,
                'amount' => $payment->amount,
            ],
        ]);

        // Send email notification
        Mail::to($user->email)->send(new PaymentStatusNotification(
            $user,
            'approved',
            $payment
        ));
    }

    /**
     * Send payment rejection notification
     */
    public static function sendPaymentRejectedNotification($payment, $reason = null)
    {
        $user = $payment->user;
        
        // Create in-app notification
        Notification::create([
            'user_id' => $user->id,
            'type' => 'payment_rejected',
            'title' => '❌ Payment Rejected',
            'message' => 'Your payment of £' . number_format($payment->amount, 2) . ' has been rejected. Please contact support.',
            'data' => [
                'payment_id' => $payment->id,
                'application_id' => $payment->application_id,
                'amount' => $payment->amount,
                'reason' => $reason,
            ],
        ]);

        // Send email notification
        Mail::to($user->email)->send(new PaymentStatusNotification(
            $user,
            'rejected',
            $payment,
            $reason
        ));
    }

    /**
     * Get unread notifications count for user
     */
    public static function getUnreadCount($userId)
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get recent notifications for user
     */
    public static function getRecentNotifications($userId, $limit = 10)
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
