<?php

namespace App\Models\Concerns;

use App\Services\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every create, change, trash, restore and delete of a model in the audit trail, with the old
 * and new values (only the fields that changed, on an update).
 *
 * The model says what it is called in the log (`auditType`) and how to name one record (`auditLabel`).
 * Seeders run with model events off, so seeded data is not logged.
 *
 * @phpstan-require-extends Model
 */
trait Audited
{
    /** The short name used in the log and its filters, e.g. "story". */
    abstract public function auditType(): string;

    /** A readable name for this record, kept in the entry so it still reads well after a rename or delete. */
    abstract public function auditLabel(): string;

    public static function bootAudited(): void
    {
        static::created(function (Model $model): void {
            /** @var Model&self $model */
            app(Audit::class)->record('created', $model->auditType(), $model->getKey(), $model->auditLabel(), null, $model->attributesToArray());
        });

        static::updated(function (Model $model): void {
            /** @var Model&self $model */
            $new = [];
            $old = [];

            foreach (array_keys($model->getChanges()) as $key) {
                if (in_array($key, Audit::IGNORED, true)) {
                    continue;
                }

                $old[$key] = $model->getOriginal($key);
                $new[$key] = $model->getAttribute($key);
            }

            if ($new === []) {
                return;
            }

            app(Audit::class)->record('updated', $model->auditType(), $model->getKey(), $model->auditLabel(), $old, $new);
        });

        static::deleted(function (Model $model): void {
            /** @var Model&self $model */
            $forcing = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();
            $soft = method_exists($model, 'isForceDeleting') && ! $forcing;

            app(Audit::class)->record(
                $soft ? 'trashed' : 'deleted',
                $model->auditType(),
                $model->getKey(),
                $model->auditLabel(),
                $soft ? null : $model->attributesToArray(),
            );
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                /** @var Model&self $model */
                app(Audit::class)->record('restored', $model->auditType(), $model->getKey(), $model->auditLabel());
            });
        }
    }
}
