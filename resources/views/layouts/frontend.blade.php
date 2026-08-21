<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
    <link rel="apple-touch-icon" sizes="57x57" href="{{ asset('images/favicon/apple-icon-57x57.png') }}">
    <link rel="apple-touch-icon" sizes="60x60" href="{{ asset('images/favicon/apple-icon-60x60.png') }}">
    <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('images/favicon/apple-icon-72x72.png') }}">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('images/favicon/apple-icon-76x76.png') }}">
    <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('images/favicon/apple-icon-114x114.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('images/favicon/apple-icon-120x120.png') }}">
    <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('images/favicon/apple-icon-144x144.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('images/favicon/apple-icon-152x152.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon/apple-icon-180x180.png') }}">
    <link rel="icon" type="image/png" sizes="192x192"  href="{{ asset('images/favicon/android-icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/favicon/favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('images/favicon/manifest.json') }}">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title')</title>
</head>
<body class="frontend">
<nav class="navbar">
    <a href="http://missionnichtrauchen.lu/" class="logo">
        <img src="{{ asset('images/logo.png') }}">
    </a>
</nav>

<div class="container">

    @yield('content')

</div>

<footer>
    <div class="container">
        <div class="row">
            <div class="col-sm-8">
                <h3>Fondation Cancer</h3>
                <p class="lead text-white">
                    pour vous, avec vous, grâce à vous.
                </p>
                <p class="text-white">
                    Fondée en 1994 au Luxembourg, la Fondation Cancer œuvre inlassablement depuis plus de 25 ans dans le
                    domaine de la lutte contre le cancer. A côté de l’information axée sur la prévention, le dépistage
                    et la vie avec un cancer, une de ses missions consiste à aider les patients et leurs proches.
                    Financer des projets de recherche sur le cancer constitue le troisième volet des missions de la
                    Fondation Cancer qui organise chaque année le grand évènement de solidarité Relais pour la Vie.
                    Toutes ces missions sont possibles grâce à la générosité de nos donateurs.
                </p>
            </div>
            <div class="col-sm-4">
                <h3>Nous contacter</h3>
                <ul class="contact-info text-white">
                    <li><span class="text-dark">Adresse :</span>
                        <br>
                        Fondation Cancer<br>
                        209, route d'Arlon L-1150
                        Luxembourg
                    </li>
                    <li>
                        <span class="text-dark">Téléphone :</span><br>
                        (+352) 45 30 331 - Répondeur 24/7
                    </li>
                    <li>
                        <span class="text-dark">Heures d'Ouverture :</span><br>
                        Lun. - ven. 8h - 17h
                    </li>
                </ul>
                <div class="soc-ico">
                    <p class="assistive-text">Trouvez nous sur :</p>
                    <a title="Facebook" href="https://www.facebook.com/fondationcancer/"
                       target="_blank">
                        <svg class="icon">
                            <use xlink:href="#facebook">
                                <g id="facebook">
                                    <path d="M14.545 11.521l-1.74 0.002l0.052 6.648h-2.453l0.014-6.648H8.824V9.421h1.592l-0.001-1.236 c0-1.713 0.485-2.756 2.592-2.756h1.758V7.53h-1.098c-0.824 0-0.863 0.293-0.863 0.84l-0.004 1.051h1.975L14.545 11.521z"></path>
                                </g>
                            </use>
                        </svg>
                    </a><a title="Twitter" href="https://twitter.com/fondationcancer" target="_blank" class="twitter">
                        <svg class="icon">
                            <use xlink:href="#twitter">
                                <g id="twitter">
                                    <path d="M18.614 6.604c-0.556 0.325-1.171 0.561-1.822 0.688c-0.526-0.551-1.271-0.896-2.099-0.896 c-1.586 0-2.875 1.269-2.875 2.83c0 0.2 0 0.4 0.1 0.646c-2.385-0.119-4.5-1.247-5.916-2.959 C5.729 7.3 5.6 7.8 5.6 8.336c0 1 0.5 1.9 1.3 2.354c-0.47-0.014-0.912-0.141-1.3-0.354c0 0 0 0 0 0 c0 1.4 1 2.5 2.3 2.774c-0.241 0.062-0.495 0.102-0.756 0.102c-0.186 0-0.365-0.02-0.541-0.055 c0.365 1.1 1.4 1.9 2.7 1.971c-0.982 0.756-2.222 1.208-3.567 1.208c-0.232 0-0.461-0.016-0.686-0.04 c1.271 0.8 2.8 1.3 4.4 1.272c5.286 0 8.171-4.312 8.171-8.055c0-0.123-0.003-0.246-0.009-0.367 C18.127 8.8 18.6 8.3 19 7.72c-0.516 0.225-1.068 0.378-1.648 0.446C17.943 7.8 18.4 7.3 18.6 6.604z"></path>
                                </g>
                            </use>
                        </svg>
                    </a><a title="YouTube" href="https://www.youtube.com/user/fondationcancerlux" target="_blank">
                        <svg class="icon">
                            <use xlink:href="#you-tube">
                                <g id="you-tube">
                                    <path d="M18.877 9.35c-0.22-1.924-0.96-2.189-2.438-2.292c-2.101-0.147-6.781-0.147-8.88 0C6.084 7.2 5.3 7.4 5.1 9.3 c-0.163 1.429-0.164 3.9 0 5.298c0.22 1.9 1 2.2 2.4 2.294c2.099 0.1 6.8 0.1 8.9 0 c1.477-0.104 2.217-0.369 2.437-2.294C19.041 13.2 19 10.8 18.9 9.35z M9.69 15.335v-6.65l5.623 3.324L9.69 15.335z"></path>
                                </g>
                            </use>
                        </svg>
                    </a><a title="Instagram" href="https://www.instagram.com/fondationcancerluxembourg/"
                           target="_blank">
                        <svg class="icon">
                            <use xlink:href="#instagram">
                                <g id="instagram">
                                    <rect x="3" y="3" display="none" opacity="0.7" fill="#27AAE1"
                                          enable-background="new    " width="16" height="16"></rect>
                                    <path d="M15.121 11.582l3.023-0.032v4.181c0 1.334-1.095 2.42-2.437 2.42H8.283c-1.344 0-2.434-1.086-2.434-2.42v-4.173h3.097 c-0.08 0.677-0.096 0.745-0.056 1.052c0.233 1.8 1.8 2.6 3.2 2.652c1.672 0.1 2.703-0.996 3.123-2.927 c-0.045-0.729-0.017 0.085-0.017-0.752L15.121 11.582L15.121 11.582z M8.226 5.851C8.246 5.8 8.3 5.8 8.3 5.85h0.393 M8.279 5.85h7.431c1.343 0 2.4 1.1 2.4 2.421l0.002 2.33h-3.375c-0.527-0.672-1.499-1.71-2.784-1.674 c-1.755 0.048-2.28 1.089-2.663 1.727L5.85 10.56V8.271c0-0.816 0.317-2.02 1.821-2.419 M16.739 7.5 c0-0.191-0.155-0.342-0.345-0.342h-1.166c-0.19 0-0.34 0.15-0.34 0.342v1.181c0 0.2 0.1 0.3 0.3 0.343h1.164 c0.188 0 0.345-0.155 0.345-0.343V7.5l0.037 0.039V7.5z M10.207 12.054c0 1 0.8 1.8 1.8 1.9 c0.986 0 1.788-0.891 1.788-1.88c0-0.983-0.802-1.779-1.789-1.779c-1.029 0.011-1.867 0.823-1.867 1.779H10.207z"></path>
                                </g>
                            </use>
                        </svg>
                    </a><a title="Mail" href="mailto:mnr@cancer.lu" target="_top">
                        <svg class="icon">
                            <use xlink:href="#mail">
                                <g id="mail">
                                    <path d="M5 6.984v10.031h0.012h13.954H19V6.984H5z M17.414 8.134l-5.416 4.012L6.586 8.134H17.414 z M6.188 9.289l2.902 2.151L6.188 14.25V9.289z M6.2 15.864l3.842-3.719l1.957 1.45l1.946-1.442l3.834 3.712L6.2 15.864L6.2 15.864z M17.812 14.271l-2.916-2.824l2.916-2.159V14.271z"></path>
                                </g>
                            </use>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-sm-1">
                <img class="logo-fondation" src="{{ asset('images/logo-fondation-cancer-blanc.svg') }}">
            </div>
            <div class="col-sm-11 links">
                <p class="text-white">
                    <a href="http://missionnichtrauchen.lu/protection-des-donnees/">Protection des données</a>
                    |
                    <a href="http://missionnichtrauchen.lu/conditions-utilisation/">Conditions d'utilisation</a>
                    |
                    <a href="http://cancer.lu" target="_blank" rel="noopener noreferrer">Fondation Cancer</a>
                    |
                    <a href="http://relaispourlavie.lu/" target="_blank" rel="noopener noreferrer">Relais pour la Vie</a>
                    |
                    <a href="http://maviesanstabac.lu/" target="_blank" rel="noopener noreferrer">Ma vie sans tabac</a>
                </p>
                <p class="text-white">&copy; Tous droits réservés Fondation Cancer 2018</p>
            </div>
        </div>
    </div>
</footer>

<script src="{{ mix('js/app.js') }}"></script>
@stack('js')
</body>
</html>
