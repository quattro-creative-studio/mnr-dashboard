@extends('layouts.app-single-page')

@section('title', 'Quiz')

@section('content')

    <div class="row">
        <div class="col">

            <div class="card">
                <div class="card-body text-center">

                    <h3 class="dislpay-1">
                        Téléchargez votre certificat ici :
                    </h3>

                    <a class="btn btn-primary mt-3" href="{{ route('certificate.download', ['certificate_uid' => $certificate->uid]) }}">certification de participation</a>
                </div>
            </div>
        </div>
    </div>

@endsection
