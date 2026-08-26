@extends('layouts.app-sidebar')

@section('title', 'Quiz')

@section('content')
    <h1 class="display-4 text-center">Quiz</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    <div class="row">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('admin.quiz.create') }}">
                <i class="fa fa-fw fa-plus"></i> Ajouter un Quiz
            </a>
        </div>
    </div>


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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn" data-dismiss="modal">Fermer</button>
                    {{-- The modal is already the confirmation step, so this
                         submits rather than links: deleting must not be a GET. --}}
                    <form id="modalDeleteForm" method="POST" action="" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger text-white">
                            <i class="fa fa-trash-o"></i>
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mt-2">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Max Score</th>
                        <th>Réponses</th>
                        <th>Statut</th>
                        <th>Date de clôture</th>
                        <th>Date de clôture</th>
                        <th>Créée</th>
                        <th>Créée</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($quizzes as $q)
                        <tr>
                            <td>{{ $q->name }}</td>
                            <td>{{ $q->max_score }}</td>
                            <td>
                                {{ $q->responseCountText() }}
                            </td>
                            <td>
                                <span class="badge badge-pill badge-{{ $q->stateColor() }}">
                                    {{ $q->stateText() }}
                                </span>
                            </td>
                            <td>{{ $q->closes_at->format('Y-m-d') }}</td>
                            <td>{{ $q->closes_at->format('U') }}</td>
                            <td>{{ $q->created_at }}</td>
                            <td>{{ $q->created_at->format('U') }}</td>
                            <td>
                                <div class="btn-group">
                                    <a href="{{ route('admin.quiz.show', [$q]) }}" class="btn btn-secondary">
                                        <i class="fa fa-fw fa-eye"></i>
                                    </a>
                                    @if($q->state !== \App\Quiz::STATE_CLOSED)
                                    <a href="{{ route('admin.quiz.edit', [$q]) }}" class="btn btn-primary">
                                        <i class="fa fa-fw fa-pencil"></i>
                                    </a>
                                    @endif
{{--                                    <a class="btn btn-danger text-white" data-action="delete" data-quiz="{{ $q }}">--}}
{{--                                        <i class="fa fa-fw fa-trash-o"></i>--}}
{{--                                    </a>--}}
                                </div>
                            </td>
                        </tr>
                                        @endforeach
                    </tbody>
                </table>

            </div>

        </div>
    </div>

@endsection

@push('js')
    <script>
        $('[data-action=delete]').click(function () {
            var quiz = JSON.parse($(this).attr('data-quiz'));

            var text = "Attention, cette action est irréversible ! Tous les informations associés avec ce quiz seront" +
                " aussi supprimmées. Réponses, texts emails etc. seront tous supprimés.";

            var url = '{{ route('admin.quiz.delete', [':id:']) }}';
            $('#modalDeleteTitle').html('Supprimer &laquo; ' + quiz.name + ' &raquo; ?');
            $('#modalDelete .modal-body').text(text);
            $('#modalDeleteForm').attr('action', url.replace(':id:', quiz.id));
            $('#modalDelete').modal('show');
        });
        $('table').dataTable({
            pageLength: 100,
            order: [
                [4, 'desc'],
            ],
            columnDefs: [
                { targets: 4, orderData: [5] },
                { targets: 5, visible: false },

                { targets: 6, orderData: [7] },
                { targets: 7, visible: false },
            ],
        });
    </script>
@endpush
