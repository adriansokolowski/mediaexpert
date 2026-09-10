<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default location
    |--------------------------------------------------------------------------
    |
    | Every scheduling endpoint works in the context of a location. The task
    | assumes a single location, so requests may omit "location_id" and the
    | location identified by this slug is used instead.
    |
    */

    'default_location_slug' => env('SCHEDULING_DEFAULT_LOCATION', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Fallback timezone
    |--------------------------------------------------------------------------
    |
    | Used when seeding a location. Timestamps are always persisted in UTC;
    | this timezone only describes how business hours are interpreted.
    |
    */

    'timezone' => env('SCHEDULING_TIMEZONE', 'Europe/Warsaw'),

    /*
    |--------------------------------------------------------------------------
    | Slot duration
    |--------------------------------------------------------------------------
    */

    'slot_duration_minutes' => (int) env('SCHEDULING_SLOT_DURATION', 30),

    /*
    |--------------------------------------------------------------------------
    | Administrative access
    |--------------------------------------------------------------------------
    |
    | Closing days (holidays) can be managed over HTTP. There is no user model
    | in this project, so the endpoints are guarded by a single shared token
    | sent in the "X-Admin-Token" header.
    |
    */

    'admin_token' => env('SCHEDULING_ADMIN_TOKEN'),

];
