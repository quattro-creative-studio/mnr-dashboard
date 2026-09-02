@extends('layouts.app-sidebar')

@section('title', 'Emails')

@section('content')
    <h1 class="display-4 text-center">E-mails</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    @if(Session::has('error'))
        <div class="alert alert-danger">
            {{ Session::get('error') }}
        </div>
    @endif

    {{-- A date left blank fails AdminDateUpdateRequest and comes back here.
         Without this block the page would simply redisplay unchanged and the
         administrator would think the save had worked. --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- The send dates are edited inside the same rows as the action buttons,
         and those buttons render a <form> of their own. Nested forms are
         invalid HTML and browsers drop the inner one, so this form is declared
         empty here and each date input joins it through the HTML5 form=
         attribute. Its submit button sits at the bottom of the page, outside
         the table, for the same reason. --}}
    <form id="dates-form" action="{{ route('admin.dates.post') }}" method="post">@csrf</form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
            <tr>
                <th>Libell&eacute;</th>
                <th>Sujet</th>
                <th>Aper&ccedil;u du texte</th>
                <th>Date d'envoi</th>
                <th>&Eacute;tat</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($emails as $email)
                @php($date = $email->scheduleDate())
                <tr class="{{ $email->isScheduled() && !$email->enabled ? 'text-muted' : '' }}">
                    <td>
                        {{-- A mail is not required to have a send date: the
                             follow-up mails kept from the previous rules have
                             none, and a freshly migrated database seeds only 11
                             of the 23 date keys. Falling back to the mail's own
                             title keeps the row readable instead of fatal. --}}
                        <div class="font-weight-bold">{{ optional($date)->label ?? $email->title }}</div>
                        <div class="font-italic text-secondary">{{ optional($date)->description }}</div>
                    </td>
                    <td>{{ $email->subject }}</td>
                    <td>{{ \Illuminate\Support\Str::words(html_entity_decode(strip_tags($email->text)), 15) }}</td>
                    <td class="text-nowrap" style="width: 12rem;">
                        @if($email->isTransactional())
                            {{-- Sent by an action, not by the calendar. Showing
                                 the linked date here would be a lie: this mail
                                 leaves the moment a teacher registers. --}}
                            <span class="text-secondary font-italic">&agrave; l'inscription</span>
                        @elseif($date === null)
                            <span class="text-secondary">&mdash;</span>
                        @else
                            <input type="hidden" form="dates-form"
                                   name="dates[{{ $loop->index }}][key]" value="{{ $date->key }}">
                            <input type="text" form="dates-form"
                                   name="dates[{{ $loop->index }}][value]"
                                   class="datepicker form-control"
                                   value="{{ optional($date->value)->format('Y-m-d') }}">
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if($email->isTransactional())
                            <span class="badge badge-info">Transactionnel</span>
                        @elseif($email->enabled)
                            <span class="badge badge-success">Actif</span>
                        @else
                            <span class="badge badge-secondary">D&eacute;sactiv&eacute;</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.emails.edit', [$email]) }}" class="btn btn-primary"
                           title="Modifier le contenu">
                            <i class="fa fa-fw fa-pencil"></i>
                        </a>

                        {{-- A transactional mail has no schedule to switch
                             off, so the control is inert rather than absent:
                             the row keeps the same shape as its neighbours,
                             and the tooltip says why. --}}
                        @if($email->isTransactional())
                            <x-action-button :action="route('admin.emails.toggle', [$email])"
                                             variant="secondary"
                                             :disabled="true"
                                             title="Envoy&eacute; sur action, pas par le calendrier">
                                <i class="fa fa-fw fa-power-off"></i>
                            </x-action-button>
                        @elseif($email->enabled)
                            <x-action-button :action="route('admin.emails.toggle', [$email])"
                                             variant="success"
                                             :confirm="'Désactiver « '.$email->title.' » ? Il ne sera plus envoyé automatiquement, sa date est conservée.'"
                                             title="D&eacute;sactiver cet envoi">
                                <i class="fa fa-fw fa-power-off"></i>
                            </x-action-button>
                        @else
                            <x-action-button :action="route('admin.emails.toggle', [$email])"
                                             variant="outline-secondary"
                                             title="R&eacute;activer cet envoi">
                                <i class="fa fa-fw fa-power-off"></i>
                            </x-action-button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Aucun e-mail disponible</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($unusedEmails->isNotEmpty())
        {{-- Folded away rather than deleted. The January/March follow-up family
             and the unused newsletters are toggled back on between editions, so
             their text has to stay reachable -- but showing them in the main
             list would put nine mails that go nowhere among ten that do. --}}
        <details class="mb-4">
            <summary class="text-secondary" style="cursor: pointer;">
                E-mails non utilis&eacute;s cette ann&eacute;e ({{ $unusedEmails->count() }})
            </summary>

            <p class="text-secondary mt-2">
                Aucun automatisme ne les envoie actuellement. Leur contenu est conserv&eacute; et
                reste modifiable, pour le jour o&ugrave; le m&eacute;canisme de suivi sera r&eacute;activ&eacute;.
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Libell&eacute;</th>
                        <th>Sujet</th>
                        <th>Aper&ccedil;u du texte</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($unusedEmails as $email)
                        <tr class="text-muted">
                            <td>{{ $email->title }}</td>
                            <td>{{ $email->subject }}</td>
                            <td>{{ \Illuminate\Support\Str::words(html_entity_decode(strip_tags($email->text)), 15) }}</td>
                            <td>
                                <a href="{{ route('admin.emails.edit', [$email]) }}" class="btn btn-primary"
                                   title="Modifier le contenu">
                                    <i class="fa fa-fw fa-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @if($otherDates->isNotEmpty())
        <h2 class="h4 mt-4">Dates du concours</h2>
        <p class="text-secondary">
            Ces dates ne d&eacute;clenchent aucun e-mail&nbsp;: elles ouvrent et ferment le formulaire d'inscription.
        </p>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>Libell&eacute;</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                @foreach($otherDates as $date)
                    <tr>
                        <td>
                            <div class="font-weight-bold">{{ $date->label }}</div>
                            <div class="font-italic text-secondary">{{ $date->description }}</div>
                        </td>
                        <td style="width: 12rem;">
                            {{-- Offset past the e-mail rows above: both tables
                                 post into the same dates[] array, so the keys
                                 must not collide. --}}
                            <input type="hidden" form="dates-form"
                                   name="dates[{{ $emails->count() + $loop->index }}][key]" value="{{ $date->key }}">
                            <input type="text" form="dates-form"
                                   name="dates[{{ $emails->count() + $loop->index }}][value]"
                                   class="datepicker form-control"
                                   value="{{ optional($date->value)->format('Y-m-d') }}">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mb-4">
        <button type="submit" form="dates-form" class="btn btn-primary btn-block">
            Actualiser les dates
        </button>
    </div>
@endsection

@push('js')
    <script>
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
        });
    </script>
@endpush
