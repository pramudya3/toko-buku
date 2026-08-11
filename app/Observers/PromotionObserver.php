<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\Promotion;
use App\Support\ActivityLogger;

final class PromotionObserver
{
    public function created(Promotion $promotion): void
    {
        ActivityLogger::log(ActivityAction::PromotionCreate, "Promosi '{$promotion->promo_name}' dibuat", $promotion);
    }

    public function updated(Promotion $promotion): void
    {
        ActivityLogger::log(ActivityAction::PromotionUpdate, "Promosi '{$promotion->promo_name}' diperbarui", $promotion);
    }

    public function deleted(Promotion $promotion): void
    {
        ActivityLogger::log(ActivityAction::PromotionDelete, "Promosi '{$promotion->promo_name}' dihapus", $promotion);
    }
}
