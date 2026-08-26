@extends('layouts.app-sidebar')

@section('title', 'Emails')

@section('content')
    <h1 class="display-4 text-center">E-mails</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    <div class="table-responsive">

        <table class="table table-bordered table-striped">
            <thead>
            <tr>
                <th>Libellé</th>
                <th>Sujet</th>
                <th>Aper&ccedil;u du texte</th>
                <th>Date d'envoi</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($emails as $email)
                <tr>
                    <td>
                        {{-- A mail is not required to have a send date: the
                             follow-up mails kept from the previous rules have
                             none, and a freshly migrated database seeds only 11
                             of the 23 date keys. Falling back to the mail's own
                             title keeps the row readable instead of fatal. --}}
                        @php($firstDate = $email->dates->first())
                        <div class="font-weight-bold">{{ optional($firstDate)->label ?? $email->title }}</div>
                        <div class="font-italic text-secondary">{{ optional($firstDate)->description }}</div>
                    </td>
                    <td>{{ $email->subject }}</td>
                    <td>{{ \Illuminate\Support\Str::words(html_entity_decode(strip_tags($email->text)), 15) }}</td>
                    <td class="text-nowrap">{{ optional(optional($email->dates()->first())->value)->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        <a href="{{ route('admin.emails.edit', [$email]) }}" class="btn btn-primary">
                            <i class="fa fa-fw fa-pencil"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">Aucune email disponible</td>
                </tr>
            @endforelse
            </tbody>
        </table>

    </div>


    <h1 class="display-4 text-center">Dates</h1>

    <form action="{{ route('admin.dates.post') }}" method="post">
        @csrf
        <div class="row">
            <div class="col-sm-12">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($dates as $date)
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $date->label }}</div>
                                    <div class="font-italic text-secondary">{{ $date->description }}</div>
                                </td>
                                <td>
                                    <input type="hidden" name="dates[{{ $loop->index }}][key]" value="{{ $date->key }}">
                                    <input type="text" name="dates[{{ $loop->index }}][value]"
                                           class="datepicker form-control" value="{{ $date->value->format('Y-m-d') }}">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 mb-4">

                <input type="submit" class="btn btn-primary btn-block" value="Actualiser">

            </div>
        </div>
    </form>
@endsection

@push('js')
    <script>
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
        });
    </script>
@endpush