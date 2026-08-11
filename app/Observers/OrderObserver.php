<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\Order;
use App\Support\ActivityLogger;

final class OrderObserver
{
    public function created(Order $order): void
    {
        ActivityLogger::log(ActivityAction::OrderCreate, "Pesanan {$order->no_order} dibuat", $order);
    }
}
