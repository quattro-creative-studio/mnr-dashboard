@extends('layouts.app-sidebar')

@section('title', 'Enseignants')

@section('content')
    <h1 class="display-4 text-center">Enseignants</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

            <div class="table-responsive">

                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Prénom</th>
                        <th>Nom</th>
                        <th>Téléphone</th>
                        <th>Adresse e-mail</th>
                        <th>N° de classes</th>
                        <th>Classes</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($teachers as $teacher)
                        <tr>
                            <td>{{ $teacher->salutation->long_form }}</td>
                            <td>{{ $teacher->first_name }}</td>
                            <td>{{ $teacher->last_name }}</td>
                            <td>{{ $teacher->phone }}</td>
                            <td>{{ $teacher->user->email }}</td>
                            <td>{{ $teacher->classes()->count() }}</td>
                            <td>{{ $teacher->classes->pluck('name')->implode(', ') }}</td>
                            <td>
                                <a href="{{ route('admin.teachers.edit', [$teacher]) }}" class="btn btn-primary">
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
            order: [
                [2, 'asc']
            ]
        });
    </script>
@endpush