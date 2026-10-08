<x-mail::message>
# {{ $title }}

Hi {{ $contactName ?: 'there' }},

{{ $businessName }} needs a few things from you to move this request forward. Your files, answers, and links all go in one place.

<x-mail::button :url="$requestUrl">
Open request
</x-mail::button>

**No account needed.** Save your progress and come back to the same link whenever you’re ready.

Questions? Reply to this email to reach {{ $businessName }}.

<x-slot:subcopy>
If the button doesn’t work, copy this link into your browser:<br>
<a href="{{ $requestUrl }}">{{ $requestUrl }}</a>
</x-slot:subcopy>
</x-mail::message>
