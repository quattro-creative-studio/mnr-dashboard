@extends('layouts.app-sidebar')

@section('title', 'Quiz')

@section('content')
    <h1 class="display-4 text-center">Correctif — {{ $quiz->name }}</h1>

    <div class="col-12 mb-5">

        <p class="alert alert-info">
            Cet e-mail est envoyé une seule fois avec le texte ci-dessous. Le texte du quiz n'est pas modifié :
            le bouton « Envoyer rappel » continue d'utiliser le texte d'origine.
        </p>

        @php($pendingCount = $quiz->remindableCount())
        @php($allCount = $quiz->assignments()->count())

        <form method="post" action="{{ route('admin.quiz.amendment.send', [$quiz]) }}"
              onsubmit="return confirm('Envoyer ce correctif ?')">
            @csrf

            <div class="form-group">
                <label>Destinataires</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="recipients" id="recipients_pending" value="pending"
                        {{ old('recipients', 'pending') === 'pending' ? 'checked' : '' }}>
                    <label class="form-check-label" for="recipients_pending">
                        Seulement les classes qui n'ont pas encore répondu ({{ $pendingCount }})
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="recipients" id="recipients_all" value="all"
                        {{ old('recipients') === 'all' ? 'checked' : '' }}>
                    <label class="form-check-label" for="recipients_all">
                        Toutes les classes du quiz ({{ $allCount }})
                    </label>
                </div>
                <div class="invalid-feedback d-block">
                    {{ inputValidationMessages($errors, 'recipients') }}
                </div>
            </div>

            <div class="form-group">
                <label for="subject">Objet</label>
                <input type="text" name="subject" id="subject" class="form-control {{ inputValidationClass($errors, 'subject') }}"
                       value="{{ old('subject', 'Correctif − ' . $quiz->name) }}">
                <div class="invalid-feedback">
                    {{ inputValidationMessages($errors, 'subject') }}
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-lg-6">
                    <div class="form-group">
                        <label for="email_text">Texte Email</label>
                        <textarea name="email_text" id="email_text">{{ old('email_text') ?? $quiz->email_text }}</textarea>
                        <div class="invalid-feedback d-block">
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

            <a class="btn btn-secondary" href="{{ route('admin.quiz.show', [$quiz]) }}">Annuler</a>
            <button type="submit" class="btn btn-warning">
                <i class="fa fa-fw fa-paper-plane"></i> Envoyer le correctif
            </button>
        </form>

    </div>
@endsection

@push('js')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        var placeholders = {!! $placeholders !!};

        function refreshPreview() {
            var text = tinymce.activeEditor.getContent({format: 'string'});
            for (p in placeholders) {
                var o = placeholders[p];
                text = text.replace(new RegExp(o.value, "g"), o.preview);
            }
            $('#preview').html(text);
        }

        tinymce.init({
            selector: '#email_text',
            license_key: 'gpl',
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
                        callback(placeholders);
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
    </script>
@endpush
