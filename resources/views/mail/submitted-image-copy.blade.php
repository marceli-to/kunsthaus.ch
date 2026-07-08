@component('mail::message')
# Vielen Dank für Ihr «JA zum Kunsthaus»

Hallo {{ $name }}

Ihr persönliches Kampagnenbild ist angehängt — Sie dürfen es gerne herunterladen und teilen.

Ihr Bild wird nun von uns geprüft. Sobald es freigegeben ist, erhalten Sie eine weitere
Nachricht.

Herzlichen Dank für Ihre Unterstützung.

{{ config('app.name') }}
@endcomponent
