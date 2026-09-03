<?php

namespace App\Events\Pos;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PosMasterUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $table,
        public string $since,
        public ?string $id = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('pos-master')];
    }

    public function broadcastAs(): string
    {
        return match ($this->table) {
            'books' => 'BookUpdated',
            'book_editions' => 'BookEditionUpdated',
            'book_edition_stocks' => 'StockUpdated',
            'inventory_stocks' => 'StockUpdated',
            'promotions' => 'PromotionUpdated',
            'tier_discounts' => 'TierDiscountUpdated',
            'users' => 'CustomerUpdated',
            'settings' => 'SettingUpdated',
            default => 'MasterUpdated',
        };
    }

    public function broadcastWith(): array
    {
        return [
            'table' => $this->table,
            'since' => $this->since,
            'id' => $this->id,
        ];
    }
}
