<?php

use App\Http\Controllers\DownloadImageController;
use App\Http\Controllers\ModerateImageController;
use App\Http\Controllers\RemoveImageController;
use App\Http\Controllers\ServeGeneratedImageFileController;
use App\Http\Controllers\ServePreviewController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// The landing page is served by Statamic's `pages` collection (home entry at /).
// Statamic registers its own front-end + Control Panel routes automatically.

// XML sitemap for search engines, generated from the routable Statamic entries.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

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
// its private files. Opaque token in the path (no signed query string — that
// pattern trips Google Safe Browsing's phishing heuristic).
Route::get('/bild-entfernen/{token}', [RemoveImageController::class, 'byToken'])
	->name('images.remove');

// Legacy remove links from mails sent before the token switch (signed URL).
Route::get('/images/{uuid}/remove', [RemoveImageController::class, 'byUuid'])
	->middleware('signed')
	->name('images.remove.legacy');

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
