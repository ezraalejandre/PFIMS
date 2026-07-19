<?php

namespace App\Services;

use App\Models\AppNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Create the in-app notification row. Call this from observers
     * whenever something notification-worthy happens.
     *
     * $referenceType/$referenceId let us avoid duplicate spam — e.g. we
     * don't want to re-notify "low stock" on every single save once an
     * item is already below threshold, only the moment it crosses it.
     */
    public function notify(
        string $title,
        string $message,
        string $type,
        string $kind,
        string $filter = 'alerts',
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): AppNotification {
        return AppNotification::create([
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'kind' => $kind,
            'filter' => $filter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * True if a notification of this type for this exact record already
     * exists and is unread — prevents re-notifying every time an already-
     * delayed project or already-low-stock item is saved again.
     */
    public function alreadyNotified(string $type, string $referenceType, int $referenceId): bool
    {
        return AppNotification::where('type', $type)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('is_read', false)
            ->exists();
    }

    /**
     * Refresh condition-based notifications before the mobile app reads them.
     * This keeps the bell badge current without requiring a websocket server.
     */
    public function syncSystemAlerts(): void
    {
        $today = Carbon::today()->toDateString();

        $delayed = DB::table('project_tbl')
            ->whereRaw('LOWER(status) = ?', ['delayed'])
            ->count();
        $this->syncSummary(
            'project_delayed_total',
            'Delayed Projects Need Attention',
            "{$delayed} delayed project" . ($delayed === 1 ? ' needs' : 's need') . ' attention.',
            $delayed,
            'overdue'
        );

        $atRisk = DB::table('project_tbl')
            ->whereRaw('LOWER(status) = ?', ['at risk'])
            ->count();
        $this->syncSummary(
            'project_at_risk_total',
            'Projects At Risk',
            "{$atRisk} project" . ($atRisk === 1 ? ' is' : 's are') . ' currently at risk.',
            $atRisk,
            'warning'
        );

        $pastDeadline = DB::table('project_tbl')
            ->whereNotNull('estimated_end_date')
            ->whereDate('estimated_end_date', '<', $today)
            ->whereRaw('LOWER(status) <> ?', ['completed'])
            ->count();
        $this->syncSummary(
            'project_past_deadline_total',
            'Projects Past Estimated Deadline',
            "{$pastDeadline} active project" . ($pastDeadline === 1 ? ' is' : 's are') . ' past the estimated deadline.',
            $pastDeadline,
            'overdue'
        );

        $budgetNearLimit = DB::table('budgets_tbl')
            ->where('budget_amount', '>', 0)
            ->whereRaw('COALESCE(actual_amount, 0) >= budget_amount * 0.90')
            ->count();
        $this->syncSummary(
            'budget_expense_overrun_total',
            'Budget / Expense Overrun',
            "{$budgetNearLimit} project budget" . ($budgetNearLimit === 1 ? ' is' : 's are') . ' near or over the spending limit.',
            $budgetNearLimit,
            'warning'
        );

        $lowStock = DB::table('inventory_item_tbl')
            ->whereColumn('current_stock', '<=', 'reorder_level')
            ->count();
        $this->syncSummary(
            'item_low_stock_total',
            'Low Stock Items',
            "{$lowStock} item" . ($lowStock === 1 ? ' is' : 's are') . ' at or below the reorder threshold.',
            $lowStock,
            'warning'
        );
    }

    private function syncSummary(string $type, string $title, string $message, int $count, string $kind): void
    {
        $query = AppNotification::where('type', $type)
            ->where('reference_type', 'summary')
            ->where('reference_id', 0);

        if ($count <= 0) {
            $query->where('is_read', false)->update(['is_read' => true]);
            return;
        }

        $notification = $query->first();
        $currentMessage = $notification?->message;

        if (!$notification) {
            $this->notify(
                title: $title,
                message: $message,
                type: $type,
                kind: $kind,
                filter: 'alerts',
                referenceType: 'summary',
                referenceId: 0,
            );
            return;
        }

        $notification->fill([
            'title' => $title,
            'message' => $message,
            'kind' => $kind,
            'filter' => 'alerts',
            'is_read' => $currentMessage !== $message ? false : $notification->is_read,
        ]);
        $notification->save();
    }
}
