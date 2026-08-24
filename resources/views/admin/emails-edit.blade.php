@extends('layouts.app-sidebar')

@section('title', 'Mise à jour email')

@section('content')
    <h1 class="display-4 text-center">Mise à jour e-mail</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    <div class="alert alert-info">
        <i class="fa fa-fw fa-info-circle"></i> {{ $email->title }}
    </div>



    <form action="{{ route('admin.emails.edit.post', [$email]) }}" method="post">
        @csrf

        <div class="form-group">
            <label for="subject">Sujet</label>
            <input required type="text" name="subject" id="subject"
                   class="form-control {{ inputValidationClass($errors, 'subject') }}"
                   value="{{ old('subject') ?? $email->subject }}">
            <div class="invalid-feedback">
                {{ inputValidationMessages($errors, 'subject') }}
            </div>
        </div>

        <div class="row">

            <div class="col-12 col-lg-6">

                <div class="form-group">
                    <label for="text">Texte</label>
                    <textarea required class="form-control {{ inputValidationClass($errors, 'text') }}" name="text"
                              id="text"
                              rows="13"
                    >{{ old('text') ?? $email->text }}</textarea>
                    <div class="invalid-feedback">
                        {{ inputValidationMessages($errors, 'text') }}
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


        <input type="submit" class="btn btn-primary mt-2" value="Mettre à jour">

    </form>

@endsection

@push('js')
    <script>
        tinymce.init({
            selector: '#text',
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
            var text = tinymce.get('text').getContent();
            for (p in placeholders) {
                var o = placeholders[p];
                text = text.replace(new RegExp(o.value, "g"), o.preview);
            }

            $('#preview').html(text);
            $('#text').html(text);
        }

    </script>
@endpush
