@extends('layouts.frontend')

@section('content')
    <div class="col-sm-6 offset-3">
        @if(count($errors) > 0)
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <form class="text-center mt-4" method="post" action="{{ route('login.password.reset.post') }}">
            @csrf
            <h1>Réinitialisation du mot de passe</h1>
            <input type="hidden" name="token" value="{{ $token }}">
            
            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input required type="email" name="email" id="email"
                       class="form-control {{ inputValidationClass($errors, 'email') }}"
                       {{-- $email comes from the ?email= parameter Laravel's
                            showResetForm passes through. Prefilling matters for
                            invitations: an invitee typing a different address
                            than the one invited gets a token mismatch and no
                            explanation. --}}
                       value="{{ old('email', $email ?? '') }}">
                <div class="invalid-feedback">
                    {{ inputValidationMessages($errors, 'email') }}
                </div>
            </div>

            <div class="form-group mt-4">
                <label for="password">Nouveau mot de passe</label>
                <input required type="password" name="password" id="password"
                       class="form-control {{ inputValidationClass($errors, 'password') }}"
                       value="{{ old('password') }}">
                <div class="invalid-feedback">
                    {{ inputValidationMessages($errors, 'password') }}
                </div>
            </div>
            
            <div class="form-group">
                <label for="password_confirmation">Confirmation du nouveau mot de passe</label>
                <input required type="password" name="password_confirmation" id="password_confirmation"
                       class="form-control {{ inputValidationClass($errors, 'password') }}"
                       value="{{ old('password_confirmation') }}">
            </div>

            <div class="form-group">
                <input type="submit" class="btn btn-block btn-lg btn-primary" value="Réinitialiser">
            </div>

        </form>

    </div>
@endsection
