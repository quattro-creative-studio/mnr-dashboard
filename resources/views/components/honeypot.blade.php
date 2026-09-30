{{--
    Anti-spam fields for public forms, checked by the `honeypot` middleware.

    The trap field is moved off-screen rather than display:none, which some
    bots detect and skip. It is hidden from screen readers and the tab order,
    and autocomplete is off so browsers don't fill it for real users.
--}}
<div style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;" aria-hidden="true">
    <label for="{{ \App\Http\Middleware\Honeypot::FIELD }}">Ne pas remplir ce champ</label>
    <input type="text" name="{{ \App\Http\Middleware\Honeypot::FIELD }}" id="{{ \App\Http\Middleware\Honeypot::FIELD }}"
           value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ \App\Http\Middleware\Honeypot::TIME_FIELD }}" value="{{ encrypt(time()) }}">
