@props([
    'action' => route('index'),
    'fieldName' => config('gupa.detectors.honeypot.field_name', 'website_url'),
    'buttonText' => 'Submit',
])

<form action="{{ $action }}" method="POST"
    style="position:absolute;left:-9999px" tabindex="-1" aria-hidden="true"
    {{ $attributes->merge(['class' => 'gupa-honeypot']) }}>
    @csrf
    <input type="text" name="{{ $fieldName }}" autocomplete="off" tabindex="-1">
    <button type="submit" tabindex="-1">{{ $buttonText }}</button>
</form>
