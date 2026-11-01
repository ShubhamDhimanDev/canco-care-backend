<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Database\Factories\InquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A message sent through the public contact form. Never exposed by the public API.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $inquiry_type_id
 * @property string|null $type_name
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $message
 * @property Carbon $consent_at
 * @property string|null $source_url
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property InquiryStatus $status
 * @property int|null $assigned_to
 * @property Carbon|null $replied_at
 * @property string|null $admin_notes
 * @property string|null $notified_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'reference', 'inquiry_type_id', 'type_name', 'first_name', 'last_name', 'email', 'phone', 'message',
    'consent_at', 'source_url', 'ip_hash', 'user_agent', 'status', 'assigned_to', 'replied_at', 'admin_notes', 'notified_to',
])]
class Inquiry extends Model
{
    /** @use HasFactory<InquiryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'consent_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InquiryType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(InquiryType::class, 'inquiry_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * "+14035551234" → "(403) 555-1234" for people to read.
     */
    public function displayPhone(): string
    {
        $digits = preg_replace('/\D/', '', $this->phone) ?? '';
        $digits = strlen($digits) === 11 && str_starts_with($digits, '1') ? substr($digits, 1) : $digits;

        return strlen($digits) === 10
            ? sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6))
            : $this->phone;
    }
}
