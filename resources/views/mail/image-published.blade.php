@component('mail::message')
# Ihr Bild wurde veröffentlicht

Hallo {{ $name }}

Ihr «JA zum Kunsthaus» Bild wurde freigegeben und ist nun veröffentlicht. Das fertige Bild ist angehängt.

Herzlichen Dank für Ihre Unterstützung.

Möchten Sie Ihr Bild wieder entfernen? Über den folgenden Link wird es vollständig gelöscht:

@component('mail::button', ['url' => $removeUrl, 'color' => 'primary'])
Bild entfernen
@endcomponent

{{ config('app.name') }}
@endcomponent
