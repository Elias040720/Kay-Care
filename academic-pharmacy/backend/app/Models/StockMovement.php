<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const REASON_CHECKOUT = 'checkout';

    public const REASON_ADJUSTMENT = 'adjustment';

    public const REASON_RESTOCK = 'restock';

    public const REASON_CANCELLATION = 'cancellation';

    public const REASON_SEED = 'seed';

    public const ADMIN_REASONS = [self::REASON_ADJUSTMENT, self::REASON_RESTOCK];

    protected $fillable = ['product_id', 'delta', 'stock_after', 'reason', 'reference', 'note', 'admin_user_id'];

    protected function casts(): array
    {
        return ['delta' => 'integer', 'stock_after' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}
