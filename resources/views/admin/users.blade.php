@extends('layouts.app-sidebar')

@section('title', 'Utilisateurs administratifs')

@section('content')
    <h1 class="display-4 text-center">Utilisateurs administratifs</h1>

    @if(Session::has('message'))
        <div class="alert alert-success">
            {{ Session::get('message') }}
        </div>
    @endif

    {{-- The controller refuses self-deletion and removal of the last
         administrator; without this block those refusals would be silent. --}}
    @if(Session::has('error'))
        <div class="alert alert-danger">
            {{ Session::get('error') }}
        </div>
    @endif

    <div class="col-sm-6 mb-2">
        <div class="card">
            <div class="card-header">
                Ajoutez un utilisateur
            </div>
            <div class="card-body">
                <p class="card-text">
                    Ajoutez un utilisateur qui aura accès à toutes les fonctions administratives.
                </p>
                <a href="{{ route('admin.users.add') }}" class="card-link btn btn-primary">
                    Inviter un administrateur
                </a>
            </div>
        </div>
    </div>

    <div class="table-responsive">

        <table class="table table-bordered table-striped">
            <thead>
            <tr>
                <th>E-mail</th>
                <th>État</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td>
                        {{ $user->email }}
                        @if($user->id === Auth::id())
                            <span class="badge badge-secondary ml-1">vous</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if($user->password_set_at)
                            <span class="badge badge-success">actif</span>
                            <span class="text-secondary ml-1">depuis le {{ $user->password_set_at->format('d/m/Y') }}</span>
                        @else
                            <span class="badge badge-warning">invitation en attente</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        {{-- Deliberately never disabled. For a pending account it
                             re-sends the invitation; for an active one it sends a
                             fresh set-password link, which is a legitimate thing to
                             need and takes nothing away -- the existing password
                             keeps working until the link is used. Only the wording
                             changes, so the button never lies about what it does. --}}
                        <x-action-button :action="route('admin.users.resend', [$user])"
                                         :confirm="$user->password_set_at
                                            ? 'Envoyer un lien de réinitialisation à '.$user->email.' ? Son mot de passe actuel reste valable.'
                                            : 'Renvoyer l\'invitation à '.$user->email.' ?'"
                                         :title="$user->password_set_at ? 'Envoyer un lien de réinitialisation' : 'Renvoyer l\'invitation'">
                            <i class="fa fa-fw fa-envelope"></i>
                        </x-action-button>
                        {{-- Deleting yourself, or the last administrator, is
                             refused by the controller as well: hiding the button
                             is a courtesy, not the guard. --}}
                        <x-action-button :action="route('admin.users.delete', [$user])"
                                         method="DELETE" variant="danger"
                                         :disabled="$user->id === Auth::id() || $users->count() <= 1"
                                         confirm="Supprimer définitivement {{ $user->email }} ?"
                                         title="Supprimer">
                            <i class="fa fa-fw fa-trash-o"></i>
                        </x-action-button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

    </div>
@endsection

@push('js')
    <script>
        $('table').dataTable();
    </script>
@endpush