<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $redirectable_type
 * @property int $redirectable_id
 * @property string $old_slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['redirectable_type', 'redirectable_id', 'old_slug'])]
class SlugRedirect extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }
}
