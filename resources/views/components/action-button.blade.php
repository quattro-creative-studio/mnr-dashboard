{{--
    A button that performs a state-changing action.

    These used to be plain <a href> links to GET routes. A GET that deletes a
    class -- or mails every eligible teacher -- is performed by a browser
    prefetch, a crawler or a mistyped URL, and carries no CSRF token because
    GET is exempt by design.

    Rendered inline (d-inline) so a row of these still reads as a button group.

    @param action   URL to submit to
    @param method   POST for actions, DELETE for removals
    @param variant  Bootstrap suffix: primary, danger, ...
    @param confirm  Text for a confirmation prompt; omit for harmless actions
    @param disabled Renders the button inert, replacing the old href="#" idiom
--}}
@props([
    'action',
    'method' => 'POST',
    'variant' => 'primary',
    'confirm' => null,
    'disabled' => false,
    'title' => null,
])

<form method="POST"
      action="{{ $action }}"
      class="d-inline"
      @if($confirm) onsubmit="return confirm(@js($confirm))" @endif>
    @csrf
    @unless(strtoupper($method) === 'POST')
        @method($method)
    @endunless
    <button type="submit"
            class="btn btn-{{ $variant }}{{ $disabled ? ' disabled' : '' }}"
            @if($title) title="{{ $title }}" @endif
            {{ $disabled ? 'disabled' : '' }}>
        {{ $slot }}
    </button>
</form>
