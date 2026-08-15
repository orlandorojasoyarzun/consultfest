<x-mail::layout>
{{-- Header: renders the Consultfest logo + brand name (overridden in
     resources/views/vendor/mail/html/header.blade.php). --}}
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

{{-- Footer: branded copy in Spanish, no Laravel-style copyright line. --}}
<x-slot:footer>
<x-mail::footer>
Consultfest · Hecho para cineastas independientes
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>