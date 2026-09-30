@extends('layouts.frontend')

<?php
/** @var \Illuminate\Support\ViewErrorBag $errors */
?>

@section('title', 'Inscription enseignants')

@section('content')
    <h1 class="display-4 text-center">Inscription enseignants</h1>

    <form method="post" action="{{ route('teacher.registerPost') }}">
        @csrf
        <x-honeypot />

        <div class="form-group">
            <label for="teacher_salutation">Titre</label>
            <select required name="teacher_salutation" id="teacher_salutation"
                    class="form-control {{ inputValidationClass($errors, 'teacher_salutation') }}">
                @foreach($salutations as $s)
                    <option value="{{ $s->id }}" {{ old('teacher_salutation') == $s->id ? 'selected' : '' }}>{{ $s->long_form }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback">
                {{ inputValidationMessages($errors, 'teacher_salutation') }}
            </div>
        </div>

        <div class="form-group">
            <label for="teacher_last_name">Nom</label>
            <input required type="text" name="teacher_last_name" id="teacher_last_name"
                   class="form-control {{ inputValidationClass($errors, 'teacher_last_name') }}"
                   value="{{ old('teacher_last_name') }}">
            <div class="invalid-feedback">
                {{ inputValidationMessages($errors, 'teacher_last_name') }}
            </div>
        </div>

        <div class="form-group">
            <label for="teacher_first_name">Prénom</label>
            <input required type="text" name="teacher_first_name" id="teacher_first_name"
                   class="form-control {{ inputValidationClass($errors, 'teacher_first_name') }}"
                   value="{{ old('teacher_first_name') }}">
            <div class="invalid-feedback">
                {{ inputValidationMessages($errors, 'teacher_first_name') }}
            </div>
        </div>

        <div class="form-group">
            <label for="teacher_email">Adresse e-mail</label>
            <input required type="email" name="teacher_email" id="teacher_email"
                   class="form-control {{ inputValidationClass($errors, 'teacher_email') }}"
                   value="{{ old('teacher_email') }}">
            <div class="invalid-feedback">
                {{ inputValidationMessages($errors, 'teacher_email') }}
            </div>
        </div>

        <div class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    <label for="teacher_password">Mot de passe</label>
                    <input required type="password" name="teacher_password" id="teacher_password"
                           class="form-control {{ inputValidationClass($errors, 'teacher_password') }}">
                    <div class="invalid-feedback">
                        {{ inputValidationMessages($errors, 'teacher_password') }}
                    </div>
                </div>
            </div>

            <div class="col-sm-6">
                <div class="form-group">
                    <label for="teacher_password_confirmation">Confirmation mot de passe</label>
                    <input required type="password" name="teacher_password_confirmation"
                           id="teacher_password_confirmation"
                           class="form-control {{ inputValidationClass($errors, 'teacher_password') }}">
                    <div class="invalid-feedback">
                        {{ inputValidationMessages($errors, 'teacher_password') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="teacher_phone">Numéro de téléphone portable</label>
            <input required type="text" name="teacher_phone" id="teacher_phone"
                   class="form-control {{ inputValidationClass($errors, 'teacher_phone') }}"
                   value="{{ old('teacher_phone') }}" aria-describedby="phone_help">
            <small id="phone_help" class="form-text text-muted">Selon ce format: +352 621123456</small>
            <div class="invalid-feedback">
                {{ inputValidationMessages($errors, 'teacher_phone') }}
            </div>
        </div>

        <div class="form-group text-left ml-4">
            <input type="checkbox" name="data_protection" id="data_protection"
                   class="custom-control-input {{ inputValidationClass($errors, 'data_protection') }}"
                    {{ old('data_protection') != null ? 'checked' : '' }}>
            <label for="data_protection" class="custom-control-label">
                J’ai lu et j’accepte la
                <a href="http://missionnichtrauchen.lu/protection-des-donnees/" target="_blank">
                    politique de confidentialité
                </a>
            </label>
            <div class="invalid-feedback">
                {{ inputValidationMessages($errors, 'data_protection') }}
            </div>
        </div>

        <input type="submit" class="btn btn-primary" value="Je m'inscris">

    </form>
@endsection