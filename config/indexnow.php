<?php

/*
|--------------------------------------------------------------------------
| IndexNow — sofortige URL-Meldung an Bing, Yandex, Seznam, Naver
|--------------------------------------------------------------------------
|
| Sobald ein Eintrag gespeichert, publiziert oder gelöscht wird, pingen wir die
| IndexNow-API an. Die teilnehmenden Suchmaschinen crawlen die URL dann in
| Minuten statt nach eigenem Zeitplan. Google nimmt NICHT teil und bleibt auf
| Sitemap + Search Console angewiesen.
|
| Der Key ist kein Geheimnis: er wird bewusst als Textdatei unter `key_location`
| ausgeliefert (public/<key>.txt), das ist der Eigentumsnachweis. Deshalb steht
| er hier als Default und muss nicht pro Umgebung gesetzt werden. Rotieren:
| INDEXNOW_KEY setzen UND public/<key>.txt entsprechend umbenennen — beide
| müssen übereinstimmen, sonst antwortet die API mit 403.
|
| Gemeldet wird nur aus der Produktion (siehe AppServiceProvider), sonst würden
| .test-URLs an die Suchmaschinen gehen.
|
*/

return [

	'key' => env('INDEXNOW_KEY', '1e8c8c730782aaff0beade794777c040'),

	// Ein beliebiger teilnehmender Endpoint genügt — die Betreiber verteilen
	// die Meldung untereinander.
	'endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),

];
