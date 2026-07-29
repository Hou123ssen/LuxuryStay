<?php

return [
    'ringing_timeout_seconds' => (int) env('CALL_RINGING_TIMEOUT_SECONDS', 45),
    'accepted_cleanup_minutes' => (int) env('CALL_ACCEPTED_CLEANUP_MINUTES', 180),
];
