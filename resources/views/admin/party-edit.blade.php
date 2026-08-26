@extends('layouts.app-sidebar')

@section('title', 'Inscription Fête de clôture')

@section('content')
    <h1 class="display-4 text-center">Inscription Fête de clôture</h1>

    <p class="text-center">
        <strong>
            Pour le rallye, la classe doit se partager en groupes de 10 personnes maximum. Si votre classe comprend
            jusqu'à 10 élèves, veuillez inscrire 1 groupe, jusqu'à 20 élèves 2 groupes, jusqu'à 30 élèves 3 groupes.
            En pratique, si la classe compte p.ex. 15 élèves, inscrire 2 groupes (7 et 8 élèves).
            Pour une classe de 22, inscrire 3 groupes (7, 7 et 8 élèves), etc...
        </strong>
    </p>

    <form action="{{ route('admin.party.class.post', [$class]) }}" method="post">
        @csrf
        <div class="row">
            <div class="col-xs-12 col-sm-12 mt-4 mb-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <h3>
                            Classe {{ $class->name }}
                            <small class="text-muted text">{{ $class->school->name }}</small>
                            <a class="btn btn-success text-white pull-right btn-add">
                                Ajouter un groupe <i class="fa fa-plus"></i>
                            </a>
                        </h3>
                        Nombre d'élèves : {{ $class->students }}
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            @foreach ([0,1,2] as $i)
                @php
                $hidden = $i > 0 && old('class.' . $i . '.name') == null;
                @endphp
                <div class="col-xs-12 col-sm-6 col-lg-4 mb-2" data-card-id="{{ $i }}" {!! $hidden ? 'style="display:none"' : '' !!} >
                    <div class="card">
                        <div class="card-body">

                            @if($i > 0)
                                <a class="btn btn-sm btn-danger text-white pull-right mb-2 btn-delete">
                                    <i class="fa fa-trash-o"></i>
                                </a>
                            @endif

                            <div class="form-group">
                                <label for="name_{{ $i }}">Nom du groupe</label>
                                <input {{ $i == 0 ? 'required' : '' }} type="text" name="class[{{ $i }}][name]"
                                       id="name_{{ $i }}"
                                       class="form-control {{ inputValidationClass($errors, 'class.' . $i . '.name') }}"
                                       value="{{ old('class.' . $i . '.name', $groups->get($i)['name'] ?? null) }}">
                                <div class="invalid-feedback">
                                    {{ inputValidationMessages($errors, 'class.' . $i . '.name') }}
                                </div>
                                <small id="name_help">
                                    Le nom choisi doit avoir un rapport avec la santé.
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="students_{{ $i }}">Nombre d'élèves</label>
                                <input {{ $i == 0 ? 'required' : '' }} type="number" name="class[{{ $i }}][students]"
                                       id="students_{{ $i }}"
                                       min="3" max="10" step="1"
                                       class="form-control {{ inputValidationClass($errors, 'class.' . $i . '.students') }}"
                                       value="{{ old('class.' . $i . '.students', $groups->get($i)['students'] ?? null) }}">
                                <div class="invalid-feedback">
                                    {{ inputValidationMessages($errors, 'class.' . $i . '.students') }}
                                </div>
                                <small id="name_help">
                                    min. 3, max. 10
                                </small>
                            </div>


                            <div class="form-group">
                                <label for="language_{{ $i }}">Langue souhaitée pour le questionnaire du rallye des
                                    élèves</label>
                                <select {{ $i == 0 ? 'required' : '' }} type="text" name="class[{{ $i }}][language]"
                                        id="language_{{ $i }}"
                                        class="form-control {{ inputValidationClass($errors, 'class.' . $i . '.language') }}">
                                    <option value="DE" {{ old('class.' . $i . '.language', $groups->get($i)['language'] ?? null) === 'DE' ? 'selected' : '' }}>
                                        Allemand
                                    </option>
                                    <option value="FR" {{ old('class.' . $i . '.language', $groups->get($i)['language'] ?? null) === 'FR' ? 'selected' : '' }}>
                                        Fran&ccedil;ais
                                    </option>
                                </select>
                                <div class="invalid-feedback">
                                    {{ inputValidationMessages($errors, 'class.' . $i . '.language') }}
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                @php($i++)
            @endforeach
        </div>
        <div class="row">
            <div class="col-12">
                <input type="submit" class="btn btn-primary btn-lg" value="Valider l'inscription">
                {{-- Points at the form below, which sits outside the registration
                     form: nesting one form inside another is invalid HTML. --}}
                <button type="submit" form="delete-party-class" class="btn btn-lg btn-danger ml-4">Supprimer &laquo; {{ $class->name }} &raquo;</button>
            </div>
        </div>
    </form>

    <form id="delete-party-class" method="POST"
          action="{{ route('admin.party.class.delete', [$class]) }}"
          onsubmit="return confirm('Supprimer l\'inscription de « {{ $class->name }} » ?')">
        @csrf
        @method('DELETE')
    </form>


@endsection

@push("js")
    <script>
        @if($groups->count() > 0)
        var visible = {{ $groups->map(function ($item, $i) {return $i;}) }};
        @else
        var visible = [0];
        @endif

        var maxVisible = {{ $class->getMaxGroups() }};

        $('.btn-add').click(function () {
            if(visible.length < maxVisible)
                showCard();
            updateAddButton()
        });

        $('.btn-delete').click(function () {
            var card = $(this).parent().parent().parent();
            hideCard(card);
            updateAddButton()
        });

        function init() {
            updateAddButton();
            for(var i of visible) {
                var el = $('[data-card-id='+ i +']');
                el.show();
                el.find('input, select').prop('required', true);
            }
        }

        function updateAddButton() {
            if(visible.length === maxVisible)
                $('.btn-add').addClass('btn-secondary').removeClass('btn-success').addClass('disabled');
            else
                $('.btn-add').addClass('btn-success').removeClass('btn-secondary').removeClass('disabled');
        }

        function hideCard(card) {
            card.fadeOut();
            card.find('input, select').prop('required', false);
            card.find('input').val('');
            var id = parseInt(card.attr('data-card-id'));
            visible = removeFromArray(visible, id);
        }

        function showCard() {
            var toShow = [1, 2];
            for(var i of toShow) {
                if(visible.indexOf(i) === -1) {
                    visible.push(i);
                    var el = $('[data-card-id='+ i +']');
                    el.fadeIn();
                    el.find('input, select').prop('required', true);
                    break;
                }
            }
        }

        function removeFromArray(arr, item) {
            for(var i = 0; i < arr.length; i++){
                if ( arr[i] === item) {
                    arr.splice(i, 1);
                }
            }
            return arr;
        }

        init();
    </script>
@endpush
