@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- Logo: public/img/logo.png. E-mail needs an absolute https URL — asset()
     builds it from APP_URL. --}}
<img src="{{ asset('img/logo.png') }}" class="logo" alt="{{ config('app.name') }}">
</a>
</td>
</tr>
