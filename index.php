<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMEA - Association des Étudiants Guinéens au Sénégal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Image Slider CSS -->
    <link rel="stylesheet" href="assets/css/image-slider.css">
    <!-- Styles personnalisés -->
    <style>
        /* Custom color palette */
        :root {
            --dark-blue: #213448;
            --medium-blue: #547792;
            --light-blue: #94B4C1;
            --light-beige: #ECEFCA;
        }

        /* Override Bootstrap colors */
        .bg-primary {
            background-color: var(--medium-blue) !important;
        }

        .bg-dark {
            background-color: var(--dark-blue) !important;
        }

        .text-primary {
            color: var(--medium-blue) !important;
        }

        .btn-primary {
            background-color: var(--medium-blue);
            border-color: var(--medium-blue);
        }

        .btn-primary:hover {
            background-color: #456781;
            border-color: #456781;
        }

        .btn-light {
            background-color: var(--light-beige);
            border-color: var(--light-beige);
            color: var(--dark-blue);
        }

        .btn-light:hover {
            background-color: #dfe1b9;
            border-color: #dfe1b9;
            color: var(--dark-blue);
        }

        .card {
            border-color: var(--light-blue);
        }

        .bg-light {
            background-color: var(--light-beige) !important;
        }

        /* Custom elements */
        .hero {
            background: linear-gradient(135deg, var(--medium-blue) 0%, var(--dark-blue) 100%);
        }

        footer {
            background-color: var(--dark-blue) !important;
        }

        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.8);
        }

        .navbar-dark .navbar-nav .nav-link:hover,
        .navbar-dark .navbar-nav .nav-link.active {
            color: var(--light-beige);
        }

        .feature-icon {
            color: var(--medium-blue);
        }

        a {
            color: var(--medium-blue);
            text-decoration: none;
        }

        a:hover {
            color: var(--dark-blue);
        }

        footer a.text-white:hover {
            color: var(--light-beige) !important;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <!-- En-tête -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-graduation-cap"></i> AMEA
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">Accueil</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">S'enregistrer</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Administration</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Bannière principale -->
    <section class="hero text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1>Bienvenue sur la plateforme AMEA</h1>
                    <p class="lead">Recensement des étudiants guinéens au Sénégal</p>
                    <p>Cette plateforme permet aux étudiants guinéens résidant au Sénégal de s'enregistrer afin de faciliter la communication, l'entraide et la coordination des activités communautaires.</p>
                    <a href="register.php" class="btn btn-light btn-lg mt-3">
                        <i class="fas fa-user-plus"></i> S'enregistrer maintenant
                    </a>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <div id="imageSlider" class="image-slider">
                        <img id="sliderImage" src="assets/img/presidents.jpg" alt="Présidents" class="slider-image">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- À propos de l'association -->
    <section class="py-5" style="background-color: white;">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 style="color: var(--dark-blue);">À propos de l'AMEA</h2>
                    <p class="lead" style="color: var(--medium-blue);">L'Association des Étudiants Guinéens au Sénégal</p>
                    <p>L'AMEA est une organisation qui vise à rassembler et soutenir tous les étudiants guinéens poursuivant leurs études au Sénégal. Notre mission est de faciliter leur intégration, promouvoir l'excellence académique et renforcer les liens de solidarité entre les membres de notre communauté.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Caractéristiques -->
    <section class="bg-light py-5">
        <div class="container">
            <h2 class="text-center mb-4" style="color: var(--dark-blue);">Pourquoi s'enregistrer ?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100" style="border-color: var(--light-blue); box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-3x text-primary mb-3"></i>
                            <h3 class="card-title h5" style="color: var(--dark-blue);">Rejoindre la communauté</h3>
                            <p class="card-text">Faites partie d'un réseau solide d'étudiants guinéens au Sénégal pour partager vos expériences.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100" style="border-color: var(--light-blue); box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <div class="card-body text-center">
                            <i class="fas fa-info-circle fa-3x text-primary mb-3"></i>
                            <h3 class="card-title h5" style="color: var(--dark-blue);">Rester informé</h3>
                            <p class="card-text">Recevez des informations importantes concernant les activités, les opportunités et les événements.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100" style="border-color: var(--light-blue); box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <div class="card-body text-center">
                            <i class="fas fa-hands-helping fa-3x text-primary mb-3"></i>
                            <h3 class="card-title h5" style="color: var(--dark-blue);">Bénéficier de soutien</h3>
                            <p class="card-text">Accédez à des ressources d'aide et d'orientation pour vos études et votre séjour au Sénégal.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pied de page -->
    <footer class="text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>AMEA - Association des Étudiants Guinéens au Sénégal</h5>
                    <p>Une plateforme de recensement et de mise en réseau pour tous les étudiants guinéens au Sénégal.</p>
                </div>
                <div class="col-md-3">
                    <h5>Liens rapides</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Accueil</a></li>
                        <li><a href="register.php" class="text-white">S'enregistrer</a></li>
                        <li><a href="login.php" class="text-white">Administration</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contact</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i> contact@amea.org</li>
                        <li><i class="fas fa-phone me-2"></i> +221 XX XXX XX XX</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2025 AMEA. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Image Slider JS -->
    <script src="assets/js/image-slider.js"></script>
</body>

</html>