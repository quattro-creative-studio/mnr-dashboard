@extends('layouts.app-sidebar')

@section('title', 'Fête de clôture')

@section('content')
    <h1 class="display-4 text-center">Inscription fête de clôture</h1>

    <div class="row">
        <div class="col-sm-3 mb-4">
            <div class="card">
                <div class="card-header">
                    Exporter fichier des classes
                </div>
                <div class="card-body">
                    @php
                        $countGroups = $groups->count();
                        $countClasses = $groups
                            ->pluck('schoolClass.name')
                            ->unique()
                            ->count();
                        $countTeacher = $groups
                            ->pluck('schoolClass.teacher')
                            ->unique()
                            ->count();
                        $countSchool = $groups
                            ->pluck('schoolClass.school.name')
                            ->unique()
                            ->count();
                        $countStudents = $groups->sum('students');
                    @endphp
                    <p>
                        L'export contiendra <strong>{{ $countGroups }}
                            {{ $countGroups == 1 ? 'groupe' : 'groupes' }}</strong>,
                        <strong>{{ $countTeacher }} {{ $countTeacher == 1 ? 'enseignant' : 'enseignants' }}</strong>,
                        <strong>{{ $countSchool }} {{ $countSchool == 1 ? 'lycée' : 'lycées' }}</strong>,
                        <strong>{{ $countStudents }} {{ $countStudents == 1 ? 'étudiant' : 'étudiants' }}</strong>,
                        <strong>{{ $countClasses }} {{ $countClasses == 1 ? 'classe' : 'classes' }}</strong>.
                    </p>
                    <a class="btn btn-primary" href="{{ route('admin.party.export') }}">
                        Exporter
                    </a>
                </div>
            </div>
        </div>
        <div class="col-sm-3 mb-4">
            <div class="card">
                <div class="card-header">
                    Ajouter groupes
                </div>
                <div class="card-body">
                    @if ($eligibleForParty->count() > 0)
                        <form id="party-create-form">

                            <div class="form-group">
                                <label for="class">Choisir une classe</label>
                                <select name="class" id="class" class="form-control">
                                    <option value=""></option>
                                    @foreach ($eligibleForParty as $class)
                                        <option value="{{ $class->id }}">{{ $class->name }}
                                            ({{ $class->teacher->full_name }}) [{{ $class->school->name }}]</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">
                                    {{ inputValidationMessages($errors, 'class') }}
                                </div>
                            </div>

                            <input type="submit" class="btn btn-primary" value="Ajouter groupes" disabled>
                        </form>
                    @else
                        <p>
                            Toutes les classes ont des groupes pour la fête de clôture.
                        </p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-sm-3 mb-4">
            <div class="card">
                <div class="card-header">
                    Recap
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Classes éligibles sans group
                            <span class="badge badge-primary badge-pill">{{ $eligibleForParty->count() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Nombre de groupes
                            <span class="badge badge-primary badge-pill">{{ $groups->count() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Nombre de groupes FR
                            <span class="badge badge-primary badge-pill">{{ $groups->filter(function($item) { return $item->language === 'FR'; })->count() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Nombre de groupes DE
                            <span class="badge badge-primary badge-pill">{{ $groups->filter(function($item) { return $item->language === 'DE'; })->count() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Nombre total d'élèves
                            <span class="badge badge-primary badge-pill">{{ $groups->sum('students') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Nom du groupe</th>
                <th>Nombre d'élèves du groupe</th>
                <th>Lycée</th>
                <th>Classe</th>
                <th>Langue souhaitée</th>
                <th>Enseignant titre</th>
                <th>Enseignant prénom</th>
                <th>Enseignant nom</th>
                <th>Télephone prof</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($groups as $group)
                <tr>
                    <td>{{ $group->name }}</td>
                    <td>{{ $group->students }}</td>
                    <td>{{ $group->schoolClass->school->name }}</td>
                    <td>{{ $group->schoolClass->name }}</td>
                    <td>{{ $group->language }}</td>
                    <td>{{ $group->schoolClass->teacher->salutation->long_form }}</td>
                    <td>{{ $group->schoolClass->teacher->first_name }}</td>
                    <td>{{ $group->schoolClass->teacher->last_name }}</td>
                    <td>{{ $group->schoolClass->teacher->phone }}</td>
                    <td>
                        <a href="{{ route('admin.party.class', [$group->schoolClass]) }}"
                            class="btn btn-primary text-white">
                            <i class="fa fa-pencil"></i>
                        </a>
                        <x-action-button :action="route('admin.party.class.delete', [$group])"
                                         method="DELETE" variant="danger"
                                         confirm="Supprimer ce groupe ?">
                            <i class="fa fa-trash-o"></i>
                        </x-action-button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

@endsection

@push('js')
    <script>
        $('table').DataTable({
            pageLength: 100,
            order: [
                [2, "asc"]
            ]
        });

        $('#party-create-form select').change(partyCreateFormChanged);

        function partyCreateFormChanged() {
            $form = $(this).parent().parent();
            var id = $(this).val();
            var url = '{{ route('admin.party.class', [':class']) }}'.replace(':class', id);
            $form.attr('action', url);
            $form.find('input[type=submit]').prop('disabled', isNaN(parseInt(id)));
        }
    </script>
@endpush
