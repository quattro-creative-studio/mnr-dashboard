@extends('layouts.app-sidebar')

@section('title', 'Ajouter un utilisateur')

@section('content')
    <h1 class="display-5 text-center">
        Ajouter un utilisateur administratif
    </h1>

    <form method="post" action="{{ route('admin.users.add.post') }}">
        @csrf

        <div class="form-group">
            <label for="email">E-mail</label>
            <input required type="email" name="email" id="email"
                   class="form-control {{ inputValidationClass($errors, 'email') }}"
                   value="{{ old('email') }}">
            <div class="invalid-feedback">
                {{ inputValidationMessages($errors, 'email') }}
            </div>
        </div>

        <p class="text-secondary">
            Aucun mot de passe à saisir&nbsp;: la personne invitée recevra un mail
            avec un lien valable 7 jours pour choisir le sien.
        </p>

        <input type="submit" class="btn btn-primary" value="Envoyer l'invitation">

    </form>

@endsection