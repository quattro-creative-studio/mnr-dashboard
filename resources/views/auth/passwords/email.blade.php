@extends('layouts.frontend')

@section('content')
    <div class="col-sm-6 offset-3">

        <form class="text-center mt-4" method="post" action="{{ route('login.password.recover.post') }}">
            @csrf
            <x-honeypot />
            <h1>Récupération du mot de passe</h1>

            @if(Session::has('message'))
                <div class="alert alert-success">
                    {{ Session::get('message') }}
                </div>
            @endif

            <div class="form-group mt-4">
                <label for="email">Adresse e-mail</label>
                <input required type="email" name="email" id="email"
                       class="form-control {{ inputValidationClass($errors, 'email') }}"
                       value="{{ old('email') }}">
                <div class="invalid-feedback">
                    {{ inputValidationMessages($errors, 'email') }}
                </div>
            </div>
            

            <div class="form-group">
                <input type="submit" class="btn btn-block btn-lg btn-primary" value="Récupérer">
            </div>

            <div>
                <p>
                    <a href="{{ route('login') }}">Login</a>
                </p>
            </div>

        </form>

    </div>
@endsection
