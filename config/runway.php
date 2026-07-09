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
            'name' => 'JAtelier Bilder',
            'singular' => 'JAtelier Bild',
            // Records are created only via /api/submit — duplicating a submission
            // in the CP makes no sense, so drop the "Duplizieren" action.
            'duplicatable' => false,
            // The detail page is a review view: all fields are read-only and
            // moderation happens via the Freigeben/Ablehnen buttons, so make the
            // resource read-only to drop the pointless "Speichern" button. This
            // also hides Runway's built-in delete — restored via DeleteImage.
            'read_only' => true,
            // The moderation blueprint has no text field for Runway to auto-pick
            // as the record title (all fields are custom read-only displays), so
            // name it explicitly — otherwise the CP title is null and crashes.
            // `title` is a model accessor → full name ("Vorname Name").
            'title_field' => 'title',
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
