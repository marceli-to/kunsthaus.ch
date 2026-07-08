<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Configure the resources (models) you'd like to be available in Runway.
    |
    */

    'resources' => [

        // JAtelier submissions — the moderation queue. Records are only created
        // via /api/submit (never in the CP), so `create` is withheld at the
        // permission layer; moderators review, change status, and delete (which
        // wipes the private files via the model observer). The blueprint lives at
        // resources/blueprints/runway/generated_image.yaml.
        \App\Models\GeneratedImage::class => [
            'name' => 'Generierte Bilder',
            'singular' => 'Generiertes Bild',
            'order_by' => 'created_at',
            'order_by_direction' => 'desc',
            'cp_icon' => 'assets',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Runway URIs Table
    |--------------------------------------------------------------------------
    |
    | When using Runway's front-end routing functionality, Runway will store model
    | URIs in a table to enable easy "URI -> model" lookups. If needed, you can
    | customize the table name here.
    |
    */

    'uris_table' => 'runway_uris',

    /*
    |--------------------------------------------------------------------------
    | Disable Migrations?
    |--------------------------------------------------------------------------
    |
    | Should Runway's migrations be disabled?
    | (eg. not automatically run when you next vendor:publish)
    |
    */

    'disable_migrations' => false,

];
