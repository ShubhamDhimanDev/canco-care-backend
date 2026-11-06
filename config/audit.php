<?php

return [

    /*
    | How many days an audit entry is kept. `php artisan audit:prune` (scheduled daily) deletes older ones.
    | Set to 0 to keep everything forever.
    */
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

    /*
    | Longest text kept for a single old/new value. Longer text (a story body, say) is cut and marked.
    */
    'max_value_length' => 20000,

];
