<x-mail::message>
# {{ $title }}

{{ $updateMessage }}

Open the request to see the latest answers, files, and activity.

<x-mail::button :url="$requestUrl">
View request
</x-mail::button>

<x-slot:subcopy>
If the button doesn’t work, copy this link into your browser:<br>
<a href="{{ $requestUrl }}">{{ $requestUrl }}</a>
</x-slot:subcopy>
</x-mail::message>
