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
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">

                        <div class="col-12">
                            <h2 class="text-center">
                                {{ $quiz->name }}
                            </h2>

                            <dl>
                                <div class="row">
                                    <div class="col-md-6 col-lg-3">
                                        <dt>Statut</dt>
                                        <dd>
                                            <span class="badge badge-pill badge-{{ $quiz->stateColor() }}">
                                                {{ $quiz->stateText() }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <dt>Date de clôture</dt>
                                        <dd>{{ $quiz->closes_at }}</dd>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <dt>Destinataires</dt>
                                        <dd>{{ $quiz->assignments()->count() }}</dd>
                                    </div>
                                    <div class="col-md">
                                        <dt>Réponses</dt>
                                        <dd>
                                            {{ $quiz->responseCountText() }}
                                        </dd>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <dt>Moyenne des points</dt>
                                        <dd>
                                            @if($quiz->responses()->count())
                                                {{ sprintf("%.2f", $quiz->getResponseAverage()) }}
                                            @else
                                                <span class="text-muted">indisponible</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <dt>Max points</dt>
                                        <dd>{{ $quiz->max_score }}</dd>
                                    </div>
                                    @if($quiz->state !== \App\Quiz::STATE_NEW && $quiz->sent_at != null)
                                        <div class="col-md-6 col-lg-3">
                                            <dt>Date d'envoi</dt>
                                            <dd>{{ $quiz->sent_at }}</dd>
                                        </div>
                                    @endif
                                </div>
                                <div class="row">
                                    <div class="col-md">
                                        <dt>Langues</dt>
                                        <dd>
                                            @foreach($quiz->quizInLanguage as $ql)
                                                <a href="{{ $ql->url }}">
                                                    <img style="height: 0.8em; width: auto;" src="{{ asset('/images/flags/' . $ql->language . '.svg') }}" alt="flag {{ $ql->language }}">
                                                    {{ $ql->url }}
                                                    <br>
                                                </a>
                                            @endforeach
                                        </dd>
                                    </div>
                                </div>
                            </dl>

                            <div class="row">
                                <div class="col">

                                    <a
                                       class="btn btn-info {{ $quiz->assignments()->count() === 0 ? 'disabled' : '' }}"
                                       href="{{ route('admin.quiz.review-mail', [$quiz]) }}"
                                    >
                                        <i class="fa fa-fw fa-eye"></i>
                                        Aperçu de l'e-mail
                                    </a>

                                    @if($quiz->assignments()->count() === 0)
                                    <span class="text text-danger">
                                        Vous devez ajouter au moins une classe pour afficher l'aperçu
                                    </span>
                                    @endif

                                </div>
                            </div>

                            <div class="row">
                                <div class="col mt-3">

                                    @if(!$quiz->hasEnoughCodes())
                                            <p class="alert alert-warning">Ce quiz n'a pas assez de codes uniques enregistrés pour que tous les classes puissent avoir un.</p>
                                    @endif

                                    <a class="btn btn-primary {{ $quiz->state === \App\Quiz::STATE_CLOSED ? 'disabled' : '' }}" href="{{ route('admin.quiz.edit', [$quiz]) }}">
                                        <i class="fa fa-fw fa-pencil"></i> Mettre à jour
                                    </a>

                                    <a class="btn btn-primary {{ $quiz->state !== \App\Quiz::STATE_NEW ? 'disabled' : '' }}" href="{{ route('admin.quiz.show.codes', [$quiz]) }}">
                                        <i class="fa fa-fw fa-key"></i>
                                        Ajouter les codes
                                    </a>

                                </div>
                            </div>

                            <div class="row">
                                <div class="col mt-3">

                                    <a class="btn btn-success {{ !empty($quiz->validate()) ? 'disabled' : '' }}" href="{{ route('admin.quiz.review', [$quiz]) }}">
                                        <i class="fa fa-fw fa-paper-plane"></i>
                                        Revoir et envoyer
                                    </a>

                                    <x-action-button :action="route('admin.quiz.send-reminder', [$quiz])"
                                                     variant="success"
                                                     :disabled="$quiz->remindableCount() == 0 || $quiz->state !== \App\Quiz::STATE_RUNNING"
                                                     confirm="Envoyer un rappel aux classes qui n'ont pas encore répondu ?">
                                        <i class="fa fa-fw fa-clock-o"></i>
                                        Envoyer rappel
                                        @if($quiz->state == \App\Quiz::STATE_RUNNING)
                                            {{ $quiz->remindableCount() > 0 ? sprintf('à %d classes', $quiz->remindableCount()): '' }}
                                        @endif
                                    </x-action-button>

                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mt-4">

            <div class="card">
                <div class="card-header">
                    Réponses
                </div>
                <div class="card-body">
                    <div class="table-responsive">

                        <table class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th>Nom classe</th>
                                <th>Nom enseignant</th>
                                <th>Points</th>
                                <th>Date répondue</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($quiz->assignments as $a)
                                <tr>
                                    <td>{{ $a->schoolClass->name }}</td>
                                    <td>{{ $a->schoolClass->teacher->full_name }}</td>
                                    <td>{{ optional($a->response)->score }}</td>
                                    <td>{{ optional($a->response)->responded_at }}</td>
                                    <td>
                                        @if($quiz->state == \App\Quiz::STATE_RUNNING)
                                        <a class="btn btn-info" onclick="$('#modalLinkTextArea').val('{{ route('external.quiz.show', [$a->uuid]) }}'); $('#modalLink').modal('show')">
                                            <i class="fa fa-fw fa-chain"></i>
                                        </a>
                                        @endif
                                    </td>
                                </tr>
                                                        @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="modalLink" tabindex="-1" role="dialog" aria-labelledby="modalLinkTitle" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lien Quiz</h5>
                </div>
                <div class="modal-body">
                    <textarea id="modalLinkTextArea" class="form-control col-12"
                              onclick="this.focus();this.select();"
                    ></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>


@endsection

@push('js')
    <script>
        $('table').dataTable({
            pageLength: 100,
        });
        $('#modalLink').on('shown.bs.modal', function (e) {
            console.log("showing");
            $('#modalLinkTextArea').focus().select();
        });
    </script>
@endpush
