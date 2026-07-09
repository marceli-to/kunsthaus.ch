@component('mail::message')
# Neues Bild eingereicht

Ein Besucher hat ein «JA zum Kunsthaus» Bild eingereicht. Es wartet auf die Freigabe.

**Name:** {{ $name }}<br>
**E-Mail:** {{ $email }}<br>
**Stil:** {{ $style }}

Das fertige Bild ist angehängt. Zur Prüfung und Freigabe im Control Panel anmelden.

{{ config('app.name') }}
@endcomponent
