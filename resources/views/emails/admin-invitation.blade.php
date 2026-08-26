@extends('emails.layout')

@section("content")
    <p>Bonjour,</p>
    <p>
        {{ $invitedBy }} vous a ouvert un accès administrateur au site
        &laquo;Mission Nichtrauchen&raquo;.
    </p>
    <p>
        Pour l'activer, choisissez votre mot de passe&nbsp;:
    </p>
    <p>
        <a href="{{ route('login.password.reset', ['token' => $token, 'email' => $email]) }}">
            Définir mon mot de passe
        </a>
    </p>
    <p>
        Ce lien est valable <strong>7 jours</strong>. Passé ce délai, demandez à
        {{ $invitedBy }} de vous renvoyer une invitation.
    </p>
    <p>
        Si vous ne vous attendiez pas à cette invitation, ignorez ce message&nbsp;:
        le compte reste inutilisable tant que le mot de passe n'a pas été défini.
    </p>
@endsection
