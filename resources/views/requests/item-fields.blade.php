<fieldset class="builder-item"><legend>Request item</legend><div class="item-fields">
<div><label for="label-{{ $index }}">Label</label><input id="label-{{ $index }}" name="items[{{ $index }}][label]" value="{{ $item['label'] }}" required maxlength="255" placeholder="e.g. Company logo"></div>
<div><label for="type-{{ $index }}">Type</label><select id="type-{{ $index }}" name="items[{{ $index }}][type]">@foreach(['file'=>'File','text'=>'Text','long_text'=>'Long text','url'=>'Link','confirmation'=>'Confirmation'] as $value=>$label)<option value="{{ $value }}" @selected($item['type'] === $value)>{{ $label }}</option>@endforeach</select></div></div>
<label for="help-{{ $index }}">Help text <span class="optional">(optional)</span></label><input id="help-{{ $index }}" name="items[{{ $index }}][help_text]" value="{{ $item['help_text'] ?? '' }}" maxlength="1000">
<div class="form-row"><label class="checkbox"><input type="hidden" name="items[{{ $index }}][required]" value="0"><input type="checkbox" name="items[{{ $index }}][required]" value="1" @checked($item['required'])>Required</label><button class="text-button remove-item" type="button">Remove item</button></div>
</fieldset>
