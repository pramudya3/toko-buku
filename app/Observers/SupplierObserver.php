<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\Supplier;
use App\Support\ActivityLogger;

final class SupplierObserver
{
    public function created(Supplier $supplier): void
    {
        ActivityLogger::log(ActivityAction::SupplierCreate, "Supplier '{$supplier->nama}' dibuat", $supplier);
    }

    public function updated(Supplier $supplier): void
    {
        ActivityLogger::log(ActivityAction::SupplierUpdate, "Supplier '{$supplier->nama}' diperbarui", $supplier);
    }

    public function deleted(Supplier $supplier): void
    {
        ActivityLogger::log(ActivityAction::SupplierDelete, "Supplier '{$supplier->nama}' dihapus", $supplier);
    }
}
