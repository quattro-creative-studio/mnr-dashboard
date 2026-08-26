@extends('layouts.app-sidebar')

@section('title', 'Lycées')

@section('content')
    <h1 class="display-4 text-center">Lycées</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

            <div class="table-responsive">

                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Adresse</th>
                        <th>Code Postal</th>
                        <th>Ville</th>
                        <th>N° Classes</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($schools as $school)
                        <tr>
                            <td>{{ $school->name }}</td>
                            <td>{{ $school->address }}</td>
                            <td>{{ $school->postal_code }}</td>
                            <td>{{ $school->city }}</td>
                            <td>{{ $school->classes()->count() }}</td>
                            <td>
                                <a href="{{ route('admin.schools.edit', [$school->id]) }}" class="btn btn-primary">
                                    <i class="fa fa-fw fa-pencil"></i>
                                </a>
                            </td>
                        </tr>
                                            @endforeach
                    </tbody>
                </table>

            </div>
@endsection

@push('js')
    <script>
        $('table').dataTable({
            pageLength: 100,
        });
    </script>
@endpush