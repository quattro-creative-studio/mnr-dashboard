@extends('layouts.app-sidebar')

@section('title', 'Classes')

@section('content')
    <h1 class="display-4 text-center">Classes</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    <!-- Delete Modal -->
    <div class="modal fade" id="modalDelete" tabindex="-1" role="dialog" aria-labelledby="modalDeleteTitle" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDeleteTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Attention, cette action est irréversible ! Si cette classe est déjà enregistrée pour la fête de
                    clôture, les inscriptions sont aussi supprimées.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn" data-dismiss="modal">Fermer</button>
                    <a id="modalDeleteLink" class="btn btn-danger text-white" href="">
                        <i class="fa fa-trash-o"></i>
                        Supprimer
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <div class="col-sm-3 mb-4">
            <div class="card">
                <div class="card-header">
                    Exporter fichier des classes
                </div>
                <div class="card-body">
                    @php
                        $countClasses = $classes->count();
                        $countTeacher = $classes->pluck('teacher')->unique()->count();
                        $countSchool = $classes->pluck('school')->unique()->count();
                        $countStudents = $classes->sum('students');
                    @endphp
                    <p>
                        L'export contiendra <strong>{{ $countClasses }} {{ $countClasses > 1 ? 'classes' : 'classe' }}</strong>,
                        <strong>{{ $countTeacher }} {{ $countTeacher > 1 ? 'enseignants' : 'enseignant' }}</strong>,
                        <strong>{{ $countSchool }} {{ $countSchool > 1 ? 'lycées' : 'lycée' }}</strong>,
                        <strong>{{ $countStudents }} {{ $countStudents > 1 ? 'étudiants' : 'étudiant' }}</strong>.
                    </p>
                    <a class="btn btn-primary" href="{{ route('admin.classes.export') }}">
                        Exporter
                    </a>
                </div>
            </div>
        </div>

        <div class="col-sm-5">
            <div class="card">
                <div class="card-header">
                    Légende
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <p>
                                <strong>Icônes :</strong><br>
                                {{ statusToIcon(null) }}: Pas répondu<br>
                                {{ statusToIcon(1) }}: Réponse positive<br>
                                {{ statusToIcon(0) }}: Réponse négative
                            </p>
                        </div>
                        <div class="col-sm-6">
                            <strong>Enseignants :</strong><br>
                            <p class="bg-multiple-classes mb-1 mt-0 p-1">
                                L'enseignant a plusieurs classes
                            </p>
                            <p class="bg-multiple-schools mb-0 p-1">
                                L'enseignant a des classes dans plusieurs lycées
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-4">

            <div class="card">
                <div class="card-header">
                    Envoyer rappels
                </div>
                <div class="card-body">
                    <p>
                        Les rappels seront activés dès que les dates des suivis respectifs sont atteintes.
                    </p>
{{--                    @if($show_january)--}}
{{--                        <a href="{{ route('admin.classes.resend', [\App\SchoolClass::STATUS_JANUARY]) }}" class="btn btn-primary">--}}
{{--                            Janvier--}}
{{--                        </a>--}}
{{--                    @else--}}
{{--                        <span class="d-inline-block" tabindex="0" data-toggle="tooltip" data-placement="bottom"--}}
{{--                              title="Sera activé : {{ \App\EditableDate::find(\App\EditableDate::FOLLOW_UP_1)->toDateString() }}">--}}
{{--                            <button disabled class="btn btn-primary" type="button" style="pointer-events: none;">--}}
{{--                                Janvier--}}
{{--                            </button>--}}
{{--                        </span>--}}
{{--                    @endif--}}
{{--                    @if($show_march)--}}
{{--                        <a href="{{ route('admin.classes.resend', [\App\SchoolClass::STATUS_MARCH]) }}" class="btn btn-primary">--}}
{{--                            Mars--}}
{{--                        </a>--}}
{{--                    @else--}}
{{--                        <span class="d-inline-block" tabindex="0" data-toggle="tooltip" data-placement="bottom"--}}
{{--                              title="Sera activé : {{ \App\EditableDate::find(\App\EditableDate::FOLLOW_UP_2)->toDateString() }}">--}}
{{--                            <button disabled class="btn btn-primary" type="button" style="pointer-events: none;">--}}
{{--                                Mars--}}
{{--                            </button>--}}
{{--                        </span>--}}
{{--                    @endif--}}
                    @if($show_may)
                        <a href="{{ route('admin.classes.resend', [\App\SchoolClass::STATUS_MAY]) }}" class="btn btn-primary">
                            Mai
                        </a>
                        @else
                        <span class="d-inline-block" tabindex="0" data-toggle="tooltip" data-placement="bottom"
                              title="Sera activé : {{ optional(\App\EditableDate::find(\App\EditableDate::FOLLOW_UP_3))->toDateString() ?? 'date non configurée' }}">
                            <button disabled class="btn btn-primary" type="button" style="pointer-events: none;">
                                Mai
                            </button>
                        </span>
                    @endif
                </div>
            </div>

        </div>

    </div>

            <div class="table-responsive">

                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>Nom</th>
                        <th>N° d'étudiants</th>
                        <th>Lycée</th>
                        <th>Enseignant titre</th>
                        <th>Enseignant prénom</th>
                        <th>Enseignant nom</th>
                        @foreach($quizzes as $quiz)
                        <th>
                            {{ $quiz->name }}
                            <br>
                            <span class="badge badge-pill badge-{{ $quiz->stateColor() }}">
                                {{ $quiz->stateText() }}
                            </span>
                        </th>
                        @endforeach
                        <th>
                            Quiz Total
                        </th>
{{--                        <th>Statut janvier</th>--}}
{{--                        <th>Statut janvier</th>--}}
{{--                        <th>Statut mars</th>--}}
{{--                        <th>Statut mars</th>--}}
                        <th>Statut mai</th>
                        <th>Statut mai</th>
                        <th>Statut fête</th>
                        <th>Statut fête</th>
                        <th>Inscription fête complétée</th>
                        <th>Inscription fête complétée</th>
                        <th>Certificat généré</th>
                        <th>Certificat généré</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($classes as $class)
                        @php
                        $css = "";
                        if($class->teacher->has_multiple_classes)
                            $css = "bg-multiple-classes";
                        if($class->teacher->has_multiple_schools)
                            $css = "bg-multiple-schools";
                        @endphp
                        <tr class="{{ $css }}">
                            <td>{{ $class->name }}</td>
                            <td>{{ $class->students }}</td>
                            <td>{{ $class->school->name }}</td>
                            <td>{{ $class->teacher->salutation->long_form }}</td>
                            <td>{{ $class->teacher->first_name }}</td>
                            <td>{{ $class->teacher->last_name }}</td>
                            @foreach($quizzes as $quiz)
                                <td>
                                    @if($quiz->getPointsForClass($class) !== null)
                                    {{ $quiz->getPointsForClass($class) }}
                                    /
                                    {{ $quiz->max_score  }}
                                    @else
                                        /
                                    @endif
                                </td>
                            @endforeach
                            <td>{{ $class->quizResponses()->sum('score') }}  / {{ $quizzes->where('state', '!=', \App\Quiz::STATE_CLOSED)->sum('max_score') }} </td>
{{--                            <td>{{ statusToIcon($class->getStatusJanuary() ) }}</td>--}}
{{--                            <td>{{ $class->getStatusJanuary() === null ? 'E' : ($class->getStatusJanuary() ? 'Y' : 'N') }}</td>--}}
{{--                            <td>{{ statusToIcon($class->getStatusMarch() ) }}</td>--}}
{{--                            <td>{{ $class->getStatusMarch() === null ? 'E' : ($class->getStatusMarch() ? 'Y' : 'N') }}</td>--}}
                            <td>{{ statusToIcon($class->getStatusMay() ) }}</td>
                            <td>{{ $class->getStatusMay() === null ? 'E' : ($class->getStatusMay() ? 'Y' : 'N') }}</td>
                            <td>{{ statusToIcon($class->getStatusParty()) }}</td>
                            <td>{{ $class->getStatusParty() === null ? 'E' : ($class->getStatusParty() ? 'Y' : 'N') }}</td>
                            <td>{{ statusToIcon($class->getStatusPartyGroups() ) }}</td>
                            <td>{{ $class->getStatusPartyGroups() === null ? 'E' : ($class->getStatusPartyGroups() ? 'Y' : 'N') }}</td>
                            @php($cert = $class->certificate()->exists() ? 1 : 0)
                            <td>{{ statusToIcon($cert) }}</td>
                            <td>{{ $cert === null ? 'E' : ($class->getStatusPartyGroups() ? 'Y' : 'N') }}</td>
                            <td>
                                <a href="{{ route('admin.classes.edit', [$class]) }}" class="btn btn-primary">
                                    <i class="fa fa-fw fa-pencil"></i>
                                </a>
                                <a class="btn btn-danger text-white" data-delete-id="{{ $class->id }}" data-delete-label="{{ $class->name }}">
                                    <i class="fa fa-fw fa-trash-o"></i>
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
        var numQuizzes = {{ $quizzes->count() }};
        $('[data-delete-id]').click(function () {
            var className = $(this).attr('data-delete-label');
            var id = $(this).attr('data-delete-id');
            var url = '{{ route('admin.classes.delete', [':id:']) }}';
            $('#modalDeleteTitle').html('Supprimer &laquo; ' + className + ' &raquo; ?');
            $('#modalDeleteLink').attr('href', url.replace(':id:', id));
            $('#modalDelete').modal('show');
        });
        $('table').dataTable({
            pageLength: 100,
            order: [
                [2, 'asc'],
                [5, 'asc'],
            ],
            columnDefs: [
                { targets: numQuizzes + 7, orderData: [numQuizzes + 8] },
                { targets: numQuizzes + 8, visible: false },

                { targets: numQuizzes + 9, orderData: [numQuizzes + 10] },
                { targets: numQuizzes + 10, visible: false },

                { targets: numQuizzes + 11, orderData: [numQuizzes + 12] },
                { targets: numQuizzes + 12, visible: false },

                { targets: numQuizzes + 13, orderData: [numQuizzes + 14] },
                { targets: numQuizzes + 14, visible: false },
                //
                // { targets: 15, orderData: [16] },
                // { targets: 16, visible: false },
                //
                // { targets: 17, orderData: [18] },
                // { targets: 18, visible: false },
            ],
        });
    </script>
@endpush
