@extends('layouts.app-sidebar')

@section('title', 'Certificates')

@section('content')
    <h1 class="display-4 text-center">Certificats</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    <div class="row">

        <div class="col-12 col-sm-6 col-xl-6 mb-4">
            <div class="card">
                <div class="card-header">
                    Générer certificats
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-xl-6">
                            <a class="btn btn-primary btn-block" href="{{ route('admin.certificates.generate.all') }}">
                                <i class="fa fa-refresh"></i> Générer tous les certificats
                            </a>
                            <br>
                            Génère des certificats pour toutes les classes éligibles, même si elles ont déjà un certificat.
                            <hr class="d-block d-xl-none">
                        </div>
                        <div class="col-12 col-xl-6">
                            <a class="btn btn-primary btn-block {{ $eligibleMissing->count() == 0 ? 'disabled' : '' }}"
                               href="{{ route('admin.certificates.generate.missing') }}">
                                <i class="fa fa-refresh"></i> Générer certificats manquants
                            </a>
                            <br>
                            <p {{ $eligibleMissing->count() > 0 ? 'hidden' : '' }}>
                                Toutes les classes ont un certificat.
                            </p>
                            Génère des certificats pour toutes les classes éligibles, seulement si elles n'ont pas encore de certificat.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-6 mb-4">
            <div class="card">
                <div class="card-header">
                    Statistiques
                </div>
                <div class="card-body">
                    <span class="badge badge-primary text-white">{{ $eligibleHaving->count() }} / {{ $classesEligible->count() }}</span>
                    Certificats générés / classes éligibles pour recevoir un certificat
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header">
                    Envoyer les Certificats
                </div>
                <div class="card-body">
                    <a href="{{route('admin.certificates.send')}}" class="btn btn-primary">
                        <i class="fa fa-fw fa-envelope"></i>
                    </a>
                    <span class="ml-2">Envoyer les certificats par mail a tous les enseignants éligibles</span>
                </div>
            </div>
        </div>

    </div>

    <div class="table-responsive">

        <table class="table table-bordered table-striped">
            <thead>
            <tr>
                <th>Nom</th>
                <th>N° d'étudiants</th>
                <th>Lycée</th>
                <th>Enseignant titre</th>
                <th>Enseignant prénom</th>
                <th>Enseignant nom</th>
                <th>Éligible pour certificat?</th>
                <th>Géneré à</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse($classes as $class)
                <tr>
                    <td>{{ $class->name }}</td>
                    <td>{{ $class->students }}</td>
                    <td>{{ $class->school->name }}</td>
                    <td>{{ $class->teacher->salutation->long_form }}</td>
                    <td>{{ $class->teacher->first_name }}</td>
                    <td>{{ $class->teacher->last_name }}</td>
                    <td>{{ statusToIcon($class->isEligibleForCertificate() ? 1 : 0) }}</td>
                    <td>{{ optional($class->certificate)->updated_at }}</td>
                    <td>
                        @php($cert = $class->certificate()->exists())
                        <div class="btn-group pull-right">
                            <a href="{{ $cert ? route('admin.certificates.download', [$class->certificate]) : '#' }}"
                               class="btn btn-info text-white {{ !$cert ? 'disabled' : '' }}">
                                <i class="fa fa-fw fa-eye"></i>
                            </a>
                            <a href="{{ route('admin.certificates.generate', [$class]) }}" class="btn btn-primary">
                                <i class="fa fa-fw fa-refresh"></i>
                            </a>
                            <a href="{{ $cert ? route('admin.certificates.delete', [$class->certificate]) : '#' }}" class="btn btn-danger {{ !$cert ? 'disabled' : '' }}">
                                <i class="fa fa-fw fa-trash-o"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">Aucune classe n'est enregistr&eacute;e</td>
                </tr>
            @endforelse
            </tbody>
        </table>

    </div>
@endsection

@push('js')
    <script>
        $('table').dataTable({
            pageLength: 100,
            order: [
                [2, 'asc'],
                [5, 'asc'],
            ],
        });
    </script>
@endpush
