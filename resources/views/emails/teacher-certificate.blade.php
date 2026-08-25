@extends('emails.layout')

@section("content")
    <p>Chers enseignants,</p>

    <p>Cet e-mail pour vous informer que le concours <i>Mission Nichtrauchen 2025-2026</i> est officiellement terminé.</p>

    <p>La persévérance et le dévouement de vos classes en valaient la peine, et nous vous remercions pour votre participation au concours. En tout, 130 classes du Luxembourg avec 2528 élèves ont participé à cette édition, qui a donc été, grâce à vous, un grand succès !</p>

    <p>Vous trouverez dans votre espace personnel, sous ce <a href="{{ route('certificate.page', ['certificate_uid' => $certificate->uid]) }}">lien</a>, votre certificat de participation.</p>

    <p>Nous avons également le plaisir d’annoncer les trois classes gagnantes de cette année, que nous félicitons très chaleureusement :</p>

    <ul>
        <li> 1<sup>er</sup> prix – 1 000 € :  la classe <i>5ADF</i> du <i>Nordstad Lycée</i> (enseignant : Claudio Marson)</li>
        <li> 2<sup>ème</sup> prix – 500 € : la classe <i>7CI07</i> du <i>Lycée de Garçons de Luxembourg</i> (enseignante : Denise Antunes)</li>
        <li> 3<sup>ème</sup> prix – 250 € : la classe <i>6C2</i> de l’<i>Ecole Privée Fieldgen</i> (enseignante : Claudia Del Fabbro)</li>
    </ul>

    <p>Pour tous ceux qui ont été présents à la fête de clôture mardi 9 juin, qui a été une réussite éclatante grâce à votre engagement, le classement final ainsi que les solutions des stations quiz sont désormais disponibles dans <a href="https://concours.missionnichtrauchen.lu/login" target="_blank">espace personnel</a>.</p>

    <p>Et ne manquez pas non plus de découvrir les photos de l’événement sur le site internet : <a href="https://missionnichtrauchen.lu/medias" target="_blank">https://missionnichtrauchen.lu/medias</a></p>

    <p>En attendant, nous vous souhaitons une agréable fin d’année scolaire et nous vous donnons d’ores et déjà rendez-vous pour l’édition 2026-2027 du concours <i>Mission Nichtrauchen</i> !</p>

    <p>Mat beschte Gréiss,</p>

    <p>D’Ekipp vun der Fondation Cancer</p>
@endsection
