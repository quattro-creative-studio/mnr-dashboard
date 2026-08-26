@extends('layouts.app-sidebar')

@section('title', 'Documents')

@section('content')
    <div id="overlay" hidden style="position: fixed;top: 0;left: 0;right: 0;bottom: 0;background-color: rgba(240,240,240,0.6);z-index: 100000;">
        <h1 style="position: fixed;top: 45%;left: 45%;">
            <i class="fa fa-spin fa-spinner"></i>
            Chargement...
        </h1>
    </div>

    <h1 class="display-4 text-center">Documents</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    <!-- Button trigger modal -->
    <button type="button" class="btn btn-primary mb-4" data-toggle="modal" data-target="#exampleModal">
        Ajouter un fichier
    </button>

    <!-- Modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ajouter un fichier</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.documents.post') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">

                        <div class="form-group">
                            <label for="title">Nom du fichier</label>
                            <input required type="text" name="title" id="title"
                                   class="form-control {{ inputValidationClass($errors, 'title') }}"
                                   value="{{ old('title') }}">
                            <div class="invalid-feedback">
                                {{ inputValidationMessages($errors, 'title') }}
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" name="description" id="description"
                                   class="form-control {{ inputValidationClass($errors, 'description') }}"
                                   value="{{ old('description') }}">
                            <div class="invalid-feedback">
                                {{ inputValidationMessages($errors, 'description') }}
                            </div>
                        </div>

                        <div class="custom-file">
                            <label for="file">Fichier</label>
                            <input required type="file" name="file" id="file"
                                   class="custom-file-input {{ inputValidationClass($errors, 'file') }}">
                            <label for="file" class="custom-file-label">Fichier</label>
                            <div class="invalid-feedback">
                                {{ inputValidationMessages($errors, 'file') }}
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <a class="btn btn-light" data-dismiss="modal">Fermer</a>
                        <input type="submit" class="btn btn-primary" value="Ajouter">
                    </div>

                </form>
            </div>
        </div>
    </div>

    <div class="table-responsive">

        <table class="table table-bordered table-striped">
            <thead>
            <tr>
                <th></th>
                <th>Nom du fichier</th>
                <th>Description</th>
                <th>Visible pour enseignants ?</th>
                <th>Visible pour fête ?</th>
                <th>Notification ?</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($documents as $document)
                <tr>
                    <td>{{ $document->sort }}</td>
                    <td>{{ $document->title }}</td>
                    <td>{{ $document->description }}</td>
                    <td>
                        <span class="switch">
                            <input data-document-id="{{ $document->id }}" type="checkbox" class="switch-sm" id="toggle-visible-{{ $document->id }}" {{ $document->visible == 1 ? 'checked' : '' }}>
                            <label for="toggle-visible-{{ $document->id }}"></label>
                        </span>
                    </td>
                    <td>
                        <span class="switch">
                            <input data-document-id="{{ $document->id }}" type="checkbox" class="switch-sm" id="toggle-party-{{ $document->id }}" {{ $document->visible_party == 1 ? 'checked' : '' }}>
                            <label for="toggle-party-{{ $document->id }}"><label>
                        </span>
                    </td>
                    <td>
                        <span class="switch">
                            <input data-document-id="{{ $document->id }}" type="checkbox" class="switch-sm" id="toggle-notification-{{ $document->id }}" {{ $document->notification == 1 ? 'checked' : '' }}>
                            <label for="toggle-notification-{{ $document->id }}"><label>
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('admin.documents.download', [$document]) }}" class="btn btn-primary">
                            <i class="fa fa-fw fa-download text-white"></i>
                        </a>
                        <a href="{{ route('admin.documents.edit', [$document]) }}" class="btn btn-primary">
                            <i class="fa fa-fw fa-pencil text-white"></i>
                        </a>
                        <a href="{{ route('admin.documents.moveUp', [$document]) }}" class="btn btn-info">
                            <i class="fa fa-fw fa-arrow-up text-white"></i>
                        </a>
                        <a href="{{ route('admin.documents.moveDown', [$document]) }}" class="btn btn-info">
                            <i class="fa fa-fw fa-arrow-down text-white"></i>
                        </a>
                        <a href="{{ route('admin.documents.delete', [$document]) }}" class="btn btn-danger">
                            <i class="fa fa-fw fa-trash-o text-white"></i>
                        </a>
                    </td>
                </tr>
                        @endforeach
            </tbody>
        </table>

    </div>
    <form hidden method="post" id="form-visibility">
        @csrf
        <input type="hidden" id="visible" name="visible">
        <input type="hidden" id="visible_party" name="visible_party">
        <input type="hidden" id="notification" name="notification">
    </form>
@endsection

@push('js')
    <script>
        $('table').dataTable();
        $(document).on('click', 'input[type=checkbox]', function () {
            var id = $(this).attr('data-document-id');
            var route = '{{ route('admin.documents.toggleVisibility', ':document:') }}'.replace(':document:', id);
            $('#visible').val($('#toggle-visible-' + id).prop('checked') === true ? 1 : 0);
            $('#visible_party').val($('#toggle-party-' + id).prop('checked') === true ? 1 : 0);
            $('#notification').val($('#toggle-notification-' + id).prop('checked') === true ? 1 : 0);
            $('#overlay').prop('hidden', false);
            $('#form-visibility').attr('action', route).submit();
        });
    </script>
@endpush
