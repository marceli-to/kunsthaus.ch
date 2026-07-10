@component('mail::message')
# Ihr Bild wurde veröffentlicht

Hallo {{ $name }}

Vielen Dank, dass Sie die Kampagne «JA zum Kunsthaus» unterstützen!

Ihr Bild wurde freigegeben und ist nun veröffentlicht auf <span style="white-space: nowrap;">[kunsthaus-ja.ch](https://kunsthaus-ja.ch)</span>.

**Ihr Bild ist ausserdem angehängt an diese E-Mail.**<br>
**Teilen Sie es auf Social Media und mit Ihren Bekannten via Whatsapp.**

**Jedes JA zählt. Machen Sie sichtbar, dass Zürich JA zum Kunsthaus sagt.**

Möchten Sie Ihr Bild auf <span style="white-space: nowrap;">[kunsthaus-ja.ch](https://kunsthaus-ja.ch)</span> wieder entfernen? Über den folgenden Link wird es vollständig gelöscht:

@component('mail::button', ['url' => $removeUrl, 'color' => 'primary'])
Bild entfernen
@endcomponent

Kampagne «JA zum Kunsthaus»<br>
<span style="white-space: nowrap;">[kunsthaus-ja.ch](https://kunsthaus-ja.ch)</span>
@endcomponent
