<?php

namespace App\Observers;

use App\Models\InventoryItem; 
use App\Services\NotificationService;

class ItemObserver
{
    protected const LOW_STOCK_THRESHOLD = 20;

    public function __construct(protected NotificationService $notifications)
    {
    }

    public function updated(InventoryItem $item): void
    {
        if (!$item->isDirty('current_stock')) {
            return;
        }

        $newStock = (float) $item->current_stock;
        $oldStock = (float) $item->getOriginal('current_stock');

        if ($newStock <= 0 && $oldStock > 0) {
            $this->notifications->notify(
                title: 'Item Out of Stock',
                message: "\"{$item->item_name}\" has run out of stock.",
                type: 'item_out_of_stock',
                kind: 'overdue',
                filter: 'alerts',
                referenceType: 'item',
                referenceId: $item->item_id,
            );
            return;
        }

        if ($newStock > 0 && $newStock < self::LOW_STOCK_THRESHOLD && $oldStock >= self::LOW_STOCK_THRESHOLD) {
            $this->notifications->notify(
                title: 'Low Stock Warning',
                message: "\"{$item->item_name}\" is low on stock (" . rtrim(rtrim(number_format($newStock, 2), '0'), '.') . " left).",
                type: 'item_low_stock',
                kind: 'warning',
                filter: 'alerts',
                referenceType: 'item',
                referenceId: $item->item_id,
            );
        }
    }
}