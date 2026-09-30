<x-mail::message>
# {{ $visitor['name'] }} wil dat we terugkomen op een gesprek met {{ $assistantName }}

**Naam:** {{ $visitor['name'] }}<br>
@if ($visitor['email'])
**E-mail:** {{ $visitor['email'] }}<br>
@endif
@if ($visitor['phone'])
**Telefoon:** {{ $visitor['phone'] }}<br>
@endif
**Gesprek begonnen op:** {{ $page ?? 'onbekend' }}

@if ($visitor['note'])
<x-mail::panel>
{{ $visitor['note'] }}
</x-mail::panel>
@endif

## Het gesprek

@foreach ($messages as $message)
**{{ $message['role'] === 'user' ? $visitor['name'] : $assistantName }}:**<br>
{!! nl2br(e($message['content'])) !!}

@endforeach

---

<small>De bezoeker heeft dit gesprek zelf meegestuurd, inclusief contactgegevens. Behandel het als elke andere lead. Sessienummer in de transcripties: {{ $sessionNumber }}</small>
</x-mail::message>
