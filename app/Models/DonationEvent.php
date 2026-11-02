<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a donation's audit trail. The payload is sanitised before it is written.
 *
 * @property int $id
 * @property int $donation_id
 * @property string $type
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 */
#[Fillable(['donation_id', 'type', 'payload'])]
class DonationEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    /**
     * @return BelongsTo<Donation, $this>
     */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }
}
