<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
{{-- ▸ PLACEHOLDER imprint — replace with the client's real address / contact / links --}}
<x-slot:footer>
<x-mail::footer>
Kunsthaus Zürich · Heimplatz 5 · 8001 Zürich
[info@kunsthaus-ja.ch](mailto:info@kunsthaus-ja.ch) · [kunsthaus.ch](https://www.kunsthaus.ch)

© {{ date('Y') }} {{ config('app.name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
