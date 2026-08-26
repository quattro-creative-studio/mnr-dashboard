@extends('layouts.app-sidebar')

@section('title', 'Quiz - Revoir et envoyer')

@section('content')
    <h1 class="display-4 text-center">Revoir et envoyer</h1>
    <h2 class="text-center">{{ $quiz->name }}</h2>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">

            <div class="row">
                <div class="col">
                    <h3>Informations générales du quiz</h3>
                    <dl>
                        <div class="row">
                            <div class="col-md">
                                <dt>Date de clôture</dt>
                                <dd>{{ $quiz->closes_at }}</dd>
                            </div>
                            <div class="col-md">
                                <dt>Destinataires</dt>
                                <dd>{{ $quiz->assignments()->count() }}</dd>
                            </div>
                            <div class="col-md">
                                <dt>Score maximal</dt>
                                <dd>{{ $quiz->max_score }}</dd>
                            </div>
                            <div class="col-md">
                                <dt>Langues</dt>
                                <dd>{{ $quiz->quizInLanguage->pluck('language')->implode(', ') }}</dd>
                            </div>
                        </div>
                    </dl>

                    <h3>Liens aux quiz</h3>

                    <p>
                        Vérifiez si les liens redirigent vers le propre quiz.<br>
                        <span class="text-danger">
                            <i class="fa fa-fw fa-exclamation-triangle"></i>
                            Ne répondez pas aux quiz avec ces liens test ! Ce sont des lien destinés aux classes.
                            Il sert seulement à vérifier que les propres codes sont appliqués pour les langues.
                        </span>
                    </p>

                    <dl>
                        <div class="row">
                            @foreach($quiz->quizInLanguage as $qil)
                                <div class="col">
                                    <dt>
                                        Lien Test <em>{{ $qil->language }}</em>&nbsp;
                                        <img style="height: 0.8em; width: auto" src="{{ asset("/images/flags/$qil->language.svg") }}" alt="Flag {{ $qil->language }}">
                                    </dt>
                                    <dd>
                                        <a href="{{ $qil->codes()->first()->quiz_maker_url }}" target="_blank">
                                            {{ $qil->codes()->first()->quiz_maker_url }}
                                        </a>
                                    </dd>
                                </div>
                            @endforeach
                        </div>
                    </dl>

                    <h3>API Post</h3>

                    <p>
                        Vérifiez sur le <a href="https://www.quiz-maker.com/Dashboard" target="_blank">dashboard quiz-maker</a>
                        que sur les deux quiz la valeur <em>API Post</em> est bien configuré avec :<br>
                        <code>{{ route('api.webhook.quizmaker') }}</code>
                        <br>
                        Cette configuration se trouve dans Settings > Quiz > API Post.
                    </p>

                    <h3>Destinataires</h3>

                    <table class="table table-sm">
                        <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Email Enseignant</th>
                            <th>Nom Enseignant</th>
                            <th>École</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($quiz->assignments->sortBy('schoolClass.teacher.last_name') as $a)
                            <tr>
                                <td>{{ $a->schoolClass->name }}</td>
                                <td>{{ $a->schoolClass->teacher->user->email }}</td>
                                <td>{{ $a->schoolClass->teacher->full_name }}</td>
                                <td>{{ $a->schoolClass->school->name }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                </div>
            </div>

        </div>
    </div>

    <div class="row">
        <div class="col mt-5">
            <div class="card">
                <div class="card-body">

                    <x-action-button :action="route('admin.quiz.send', [$quiz])" variant="success"
                                     confirm="Envoyer ce quiz à toutes les classes assignées ? Cette action est irréversible.">
                        <i class="fa fa-fw fa-paper-plane"></i>
                        Envoyer
                    </x-action-button>

                </div>
            </div>
        </div>
    </div>

@endsection
