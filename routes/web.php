<?php

use App\Http\Controllers\DownloadImageController;
use App\Http\Controllers\ModerateImageController;
use App\Http\Controllers\RemoveImageController;
use App\Http\Controllers\ServeGeneratedImageFileController;
use App\Http\Controllers\ServePreviewController;
use Illuminate\Support\Facades\Route;

// The landing page is served by Statamic's `pages` collection (home entry at /).
// Statamic registers its own front-end + Control Panel routes automatically.

// Serve a pre-consent composite preview from the private disk. Reachable only
// via the temporary signed URL minted by CompositeService.
Route::get('/previews/{preview}', ServePreviewController::class)
	->middleware('signed')
	->name('previews.show');

// Download a submitted composite (permanent, private). Reachable only via the
// temporary signed URL handed to the visitor on submit.
Route::get('/images/{uuid}/download', DownloadImageController::class)
	->middleware('signed')
	->name('images.download');

// Tokenised remove / unsubscribe from the publish email — deletes the record and
// its private files. Long-lived signed URL.
Route::get('/images/{uuid}/remove', RemoveImageController::class)
	->middleware('signed')
	->name('images.remove');

// Moderator-only view of a submitted image's private files (source / final) in
// the Control Panel. Uses the base `statamic.cp` group (session + CP auth guard)
// — NOT `statamic.cp.authenticated`, whose Inertia page-sharing middleware
// assumes an Inertia response and breaks on a raw file stream. The moderator
// permission is enforced in the controller.
Route::get('/'.config('statamic.cp.route').'/generated-images/{uuid}/file/{which}', ServeGeneratedImageFileController::class)
	->middleware('statamic.cp')
	->name('cp.generated-images.file');

// Moderation actions (targets of the "Freigeben" / "Ablehnen" buttons on the CP
// detail page). Same CP session group as the file route; permission enforced in
// the controller.
Route::post('/'.config('statamic.cp.route').'/generated-images/{uuid}/publish', [ModerateImageController::class, 'publish'])
	->middleware('statamic.cp')
	->name('cp.generated-images.publish');

Route::post('/'.config('statamic.cp.route').'/generated-images/{uuid}/reject', [ModerateImageController::class, 'reject'])
	->middleware('statamic.cp')
	->name('cp.generated-images.reject');
