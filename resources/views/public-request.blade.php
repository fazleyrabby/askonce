<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $clientRequest->title }} · {{ $organization->name }}</title>{{ Illuminate\Support\Facades\Vite::fonts() }}@vite(['resources/css/app.css','resources/js/public-request.js'])</head>
<body><main class="client-content">@if($organization->is_demo)<p class="demo-banner" role="note">This is a sample request from an AskOnce demo. Don’t share real information here.</p>@endif<div class="client-business">{{ $organization->name }}</div><h1>{{ $clientRequest->title }}</h1><p class="intro">Requested by {{ $organization->name }} · <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>
@if($clientRequest->status === 'completed')
<div class="thank-you"><h2>Thank you. Everything’s in.</h2><p>Your request is complete. If you need to make a change, contact {{ $organization->name }}.</p></div>
@elseif(in_array($clientRequest->status,['cancelled','expired']))
<div class="thank-you"><h2>This request was closed.</h2><p>Please contact {{ $organization->name }} if you need help.</p></div>
@else
@if($clientRequest->description)<p class="request-description">{{ $clientRequest->description }}</p>@endif
<div class="client-note">Each answer saves automatically. You can return to this link any time.<br>Don’t paste passwords here.</div>
<div id="submission-message" role="status"></div>
@foreach($clientRequest->items as $item)
<form class="form client-item" data-item-form action="{{ route('public-request.save',['token'=>$clientRequest->token,'item'=>$item->id]) }}" method="post" enctype="multipart/form-data" data-type="{{ $item->type }}">
<label for="item-{{ $item->id }}">{{ $item->label }} <span class="optional">{{ $item->required ? 'Required' : 'Optional' }}</span></label>
@if($item->help_text)<p class="item-help">{{ $item->help_text }}</p>@endif
@if($item->type === 'file')
@if($item->submission?->uploads->isNotEmpty())<ul class="uploaded-files">@foreach($item->submission->uploads as $upload)<li>{{ $upload->original_name }}</li>@endforeach</ul>@endif
<label class="checkbox"><input type="checkbox" name="replace" value="1">Replace existing files with this upload</label>
<input id="item-{{ $item->id }}" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx"><p class="field-help">Up to 50 MB per file, 90 MB per batch. Images, PDFs, text and Office documents.</p>
@elseif($item->type === 'long_text')<textarea id="item-{{ $item->id }}" name="value" rows="5" maxlength="20000">{{ $item->submission?->value }}</textarea>
@elseif($item->type === 'confirmation')<label class="checkbox"><input id="item-{{ $item->id }}" type="checkbox" name="value" value="1" @checked($item->submission?->value)>I confirm</label>
@else<input id="item-{{ $item->id }}" name="value" type="{{ $item->type === 'url' ? 'url' : 'text' }}" value="{{ $item->submission?->value }}" maxlength="{{ $item->type === 'url' ? 2048 : 1000 }}" @if($item->type === 'url') placeholder="https://" @endif>
@endif
<div class="save-row"><span data-save-status role="status">{{ $item->status === 'submitted' ? 'Saved' : 'Not provided yet' }}</span><button type="submit" class="text-button">Save item</button></div>
</form>
@endforeach
<button class="button" id="submit-request" data-url="{{ route('public-request.submit',['token'=>$clientRequest->token]) }}">Submit</button><p class="field-help submit-help">Done for now? Submit even if a few things are still missing.</p>
@endif
<footer class="client-footer">Collected with <span class="wordmark">AskOnce</span></footer></main></body></html>
