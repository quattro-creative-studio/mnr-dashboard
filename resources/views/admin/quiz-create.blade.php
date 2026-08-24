@extends('layouts.app-sidebar')

@section('title', 'Quiz')

@section('content')
    <h1 class="display-4 text-center">Quiz</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    <div class="col-12 mb-5">

        <form method="post" action="{{ $quiz->exists ? route('admin.quiz.edit.post', [$quiz]) : route('admin.quiz.create.post') }}">
            @csrf

            <div class="form-group">
                <label for="name">Nom</label>
                <input required type="text" name="name" id="name"
                       class="form-control {{ inputValidationClass($errors, 'name') }}"
                       value="{{ old('name') ?? $quiz->name }}">
                <div class="invalid-feedback">
                    {{ inputValidationMessages($errors, 'name') }}
                </div>
            </div>

            <div class="form-group">
                <label for="max_score">Score maximal</label>
                <input required type="number" name="max_score" id="max_score" min="1"
                       class="form-control {{ inputValidationClass($errors, 'max_score') }}"
                       value="{{ old('max_score') ?? $quiz->max_score }}">
                <div class="invalid-feedback">
                    {{ inputValidationMessages($errors, 'max_score') }}
                </div>
            </div>

            @if(!$quiz->exists)

                @foreach($languages as $language)

                <div class="form-group">
                    <label for="quiz_url[{{ $language }}]">URL Quiz ({{ $language }})</label>
                    <input required type="text" name="quiz_url[{{ $language }}]" id="quiz_url[{{ $language }}]"
                           class="form-control {{ inputValidationClass($errors, "quiz_url.$language") }}"
                           value="{{ old("quiz_url.$language") }}">
                    <div class="invalid-feedback">
                        {{ inputValidationMessages($errors, "quiz_url.$language") }}
                    </div>
                </div>

                @endforeach

            @endif

            <div class="row">
                <div class="col-6">

                    <div class="form-group">
                        <label for="closes_at_date">Date de clôture</label>
                        <input type="text" name="closes_at_date" id="closes_at_date" required
                               placeholder="yyyy-mm-dd" min="{{ date('Y-m-d', strtotime('+1day')) }}"
                               class="form-control datepicker {{ inputValidationClass($errors, 'closes_at') }}"
                               value="{{ old('closes_at_date', optional($quiz->closes_at)->format('Y-m-d')) }}">
                        <div class="invalid-feedback">
                            {{ inputValidationMessages($errors, 'closes_at') }}
                        </div>
                    </div>

                </div>
                <div class="col-6">

                    <div class="form-group">
                        <label for="closes_at_time">Temps de clôture</label>
                        <input type="time" name="closes_at_time" id="closes_at_time" required
                               min="{{ date('Y-m-d', strtotime('+1day')) }}"
                               class="form-control {{ inputValidationClass($errors, 'closes_at') }}"
                               value="{{ old('closes_at_time', optional($quiz->closes_at)->format('H:i') ?? '07:00') }}">
                        <div class="invalid-feedback">
                            {{ inputValidationMessages($errors, 'closes_at') }}
                        </div>
                    </div>

                </div>
            </div>



            <div class="form-group">
                <label for="classes">Classes</label>
                <select required
                        multiple
                        name="classes[]"
                        id="classes"
                        {{ $quiz->exists && $quiz->state !== \App\Quiz::STATE_NEW ? 'disabled' : '' }}
                        class="form-control {{ inputValidationClass($errors, 'classes') }}">
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" {{
                                in_array($class->id, old('classes', [])) || $quiz->assignments()->where('school_class_id', $class->id)->exists()
                                 ? 'selected' : '' }}>
                            {{ sprintf("%s (%s, %s)", $class->name, $class->teacher->full_name, $class->school->name) }}
                        </option>
                    @endforeach
                </select>
                <div class="invalid-feedback">
                    {{ inputValidationMessages($errors, 'classes') }}
                </div>
            </div>

            @if(!$quiz->exists || $quiz->state !== \App\Quiz::STATE_CLOSED)

                <div class="row">

                    <div class="col-12 col-lg-6">

                        <div class="form-group">
                            <label for="email_text">Text Email</label>
                            <textarea name="email_text" id="email_text"
                            >{{ old('email_text') ?? $quiz->email_text }}</textarea>
                            <div class="invalid-feedback">
                                {{ inputValidationMessages($errors, 'email_text') }}
                            </div>
                        </div>

                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card mt-4">
                            <div class="card-header">Aper&ccedil;u</div>
                            <div class="card-body" id="preview"></div>
                        </div>
                    </div>
                </div>

            @else
                <div class="form-group">
                    <label>Text Email</label>
                    <div class="card">
                        <div class="card-body">
                            {!! $quiz->email_text !!}
                        </div>
                    </div>
                </div>
            @endif

            <input type="submit" class="btn btn-primary" value="{{ $quiz->exists ? 'Mettre à jour' : 'Ajouter' }}">

        </form>

    </div>

@endsection

@push('js')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        $('table').dataTable({
            pageLength: 100,
        });
        tinymce.init({
            selector: '#email_text',
            // Mandatory since TinyMCE 7: without it the editor is disabled
            // outright, not merely warned about. 'gpl' is the declaration
            // that this self-hosted copy is used under GPL-2.0-or-later.
            license_key: 'gpl',
            // TinyMCE 6 added a promotional button inside the editor chrome
            // ("Get all features"), which 5.10.9 did not have. An advert has no
            // place in the editor a cancer foundation writes its mail in.
            promotion: false,
            height: 500,
            plugins: 'link',
            toolbar: 'undo redo | styles | bold italic | link | alignleft aligncenter alignright | placeholder',
            setup: function (editor) {
                editor.ui.registry.addSplitButton('placeholder', {
                    text: 'Texte réservé',
                    icon: 'placeholder',
                    onItemAction: function (api, value) {
                        editor.insertContent(value);
                    },
                    onAction: function () {
                    },
                    fetch: function (callback) {
                        callback({!! $placeholders !!});
                    }
                });
                editor.on('keyUp', refreshPreview);
                editor.on('Change', refreshPreview);
                editor.on('init', refreshPreview);
                editor.ui.registry.addIcon('placeholder', '<svg style="width:24px;height:24px" viewBox="0 0 24 24">\n' +
                    '    <path fill="currentColor" d="M20,2A2,2 0 0,1 22,4V16A2,2 0 0,1 20,18H6L2,22V4C2,2.89 2.9,2 4,2H20M4,4V17.17L5.17,16H20V4H4M6,7H18V9H6V7M6,11H15V13H6V11Z" />\n' +
                    '</svg>');
            },
        });

        var placeholders = {!! $placeholders !!};

        function refreshPreview() {
            var text = tinymce.activeEditor.getContent({format: 'string'});
            for (p in placeholders) {
                var o = placeholders[p];
                text = text.replace(new RegExp(o.value, "g"), o.preview);
            }

            $('#preview').html(text);
            $('#text').html(text);
        }
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            startDate: '{{ date('Y-m-d', strtotime('+1day')) }}'
        });
    </script>
@endpush
