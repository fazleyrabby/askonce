<x-mail::message>
# A little left to send.

Hi {{ $contactName ?: 'there' }},

{{ $businessName }} is still waiting for a few items for **{{ $title }}**. You can pick up right where you left off.

<x-mail::panel>
**{{ $completed }} of {{ $total }} items completed**

@foreach($missing as $label)
- {{ $label }}
@endforeach
</x-mail::panel>

<x-mail::button :url="$requestUrl">
Continue request
</x-mail::button>

No account needed. Have a question? Reply to reach {{ $businessName }}.

<x-slot:subcopy>
<a href="{{ $stopUrl }}">Stop reminders for this request</a><br><br>
If the button doesn’t work, copy this link into your browser:<br>
<a href="{{ $requestUrl }}">{{ $requestUrl }}</a>
</x-slot:subcopy>
</x-mail::message>
