<?php

namespace App\Models;

use App\Enums\DonationStatus;
use Database\Factories\DonationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One gift. Never exposed by the public API beyond a minimal status view.
 *
 * @property int $id
 * @property string $reference
 * @property DonationStatus $status
 * @property int $amount_cents
 * @property string $currency
 * @property int|null $event_id
 * @property string|null $event_title
 * @property string $donor_first_name
 * @property string $donor_last_name
 * @property string $donor_email
 * @property string|null $donor_phone
 * @property string $address_line1
 * @property string|null $address_line2
 * @property string $city
 * @property string $province
 * @property string $postal_code
 * @property string $country
 * @property Carbon $consent_at
 * @property string $gateway
 * @property bool $is_test
 * @property string|null $gateway_transaction_id
 * @property string|null $gateway_response_code
 * @property string|null $gateway_response_message
 * @property string|null $failure_reason
 * @property string|null $card_brand
 * @property string|null $card_last4
 * @property string $idempotency_key
 * @property Carbon|null $paid_at
 * @property int $refunded_cents
 * @property string|null $receipt_number
 * @property Carbon|null $receipt_issued_at
 * @property Carbon|null $receipt_sent_at
 * @property string|null $ip_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'reference', 'status', 'amount_cents', 'currency', 'event_id', 'event_title',
    'donor_first_name', 'donor_last_name', 'donor_email', 'donor_phone',
    'address_line1', 'address_line2', 'city', 'province', 'postal_code', 'country', 'consent_at',
    'gateway', 'is_test', 'gateway_transaction_id', 'gateway_response_code', 'gateway_response_message',
    'failure_reason', 'card_brand', 'card_last4', 'idempotency_key',
    'paid_at', 'refunded_cents', 'receipt_number', 'receipt_issued_at', 'receipt_sent_at', 'ip_hash',
])]
class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use HasFactory;

    /** The charity is in British Columbia; receipts state dates in its time zone. */
    public const RECEIPT_TIMEZONE = 'America/Vancouver';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DonationStatus::class,
            'amount_cents' => 'integer',
            'refunded_cents' => 'integer',
            'is_test' => 'boolean',
            'consent_at' => 'datetime',
            'paid_at' => 'datetime',
            'receipt_issued_at' => 'datetime',
            'receipt_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<DonationEvent, $this>
     */
    public function trail(): HasMany
    {
        return $this->hasMany(DonationEvent::class)->orderBy('id');
    }

    public function donorName(): string
    {
        return trim("{$this->donor_first_name} {$this->donor_last_name}");
    }

    /**
     * What the gift counts for after any refund.
     */
    public function netCents(): int
    {
        return max(0, $this->amount_cents - $this->refunded_cents);
    }

    /**
     * Cents as a plain "50.00" (no symbol), the way the receipt prints it.
     */
    public static function plainAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Donations that count towards the money raised (succeeded or partly refunded, real gateway only).
     *
     * @param  Builder<Donation>  $query
     */
    #[Scope]
    protected function raised(Builder $query): void
    {
        $query->whereIn('status', [DonationStatus::Succeeded->value, DonationStatus::PartiallyRefunded->value])
            ->where('is_test', false);
    }
}
