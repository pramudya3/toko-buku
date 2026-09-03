<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Events\Pos\PosMasterUpdated;
use App\Models\Warehouse;
use App\Support\ActivityLogger;

final class WarehouseObserver
{
    public function created(Warehouse $warehouse): void
    {
        ActivityLogger::log(ActivityAction::WarehouseCreate, "Gudang '{$warehouse->nama}' dibuat", $warehouse);
        broadcast(new PosMasterUpdated('warehouses', now()->toIso8601String(), $warehouse->id));
    }

    public function updated(Warehouse $warehouse): void
    {
        ActivityLogger::log(ActivityAction::WarehouseUpdate, "Gudang '{$warehouse->nama}' diperbarui", $warehouse);
        broadcast(new PosMasterUpdated('warehouses', now()->toIso8601String(), $warehouse->id));
    }

    public function deleted(Warehouse $warehouse): void
    {
        ActivityLogger::log(ActivityAction::WarehouseDelete, "Gudang '{$warehouse->nama}' dihapus", $warehouse);
        broadcast(new PosMasterUpdated('warehouses', now()->toIso8601String(), $warehouse->id));
    }
}
