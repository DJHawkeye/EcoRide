<?php include 'includes/header.php'; ?>

<main>

  <div id="hero-container">
    <div id="bg1" class="bg-slide"></div>
    <div id="bg2" class="bg-slide"></div>

    <div id="hero-inner">
      <section class="welcome-section text-center">
        <h1 class="display-4">Bienvenue sur EcoRide</h1>
        <p class="lead">La plateforme de covoiturage écologique pour vos déplacements.</p>
      </section>

      <section class="search-form-container card p-4">
        <h2 class="text-center mb-3">Rechercher un covoiturage</h2>
        <form action="covoiturages.php" method="GET">
          <div class="mb-3">
            <input type="text" name="departure" class="form-control" placeholder="Ville de départ" required />
          </div>
          <div class="mb-3">
            <input type="text" name="arrival" class="form-control" placeholder="Ville d'arrivée" required />
          </div>
          <div class="mb-3">
            <?php $today = date('Y-m-d'); ?>
              <input
                type="date"
                name="date"
                class="form-control"
                required
                min="<?= $today ?>"
                value="<?= $date ?>"
              />
          </div>
          <div class="text-center">
            <button type="submit" class="btn btn-success">Rechercher</button>
          </div>
        </form>
      </section>
    </div>
  </div>

</main>

<section id="solid-section" class="py-5" style="background-color: #f0f9f4;">
  <div class="container">
    <h2 class="text-center mb-5" style="color: #2a5d34;">
      Pourquoi choisir <span style="color:#3ca54d;">EcoRide</span> ?
    </h2>

    <div class="row g-4">
      <!-- Card 1 -->
      <div class="col-12 col-md-4 col-lg-4 text-center">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <i class="fas fa-leaf fa-3x mb-3" style="color:#3ca54d;"></i>
            <h5 class="card-title">Covoiturage écologique</h5>
            <p class="card-text text-muted">
              Réduisez votre empreinte carbone en partageant vos trajets avec des conducteurs utilisant des véhicules écologiques.
            </p>
          </div>
        </div>
      </div>

      <!-- Card 2 -->
      <div class="col-12 col-md-4 col-lg-4 text-center">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <i class="fas fa-users fa-3x mb-3" style="color:#3ca54d;"></i>
            <h5 class="card-title">Communauté conviviale</h5>
            <p class="card-text text-muted">
              Rejoignez une communauté de voyageurs responsables et partagez bien plus que des trajets.
            </p>
          </div>
        </div>
      </div>

      <!-- Card 3 -->
      <div class="col-12 col-md-4 col-lg-4 text-center">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <i class="fas fa-wallet fa-3x mb-3" style="color:#3ca54d;"></i>
            <h5 class="card-title">Tarifs abordables</h5>
            <p class="card-text text-muted">
              Profitez de tarifs compétitifs qui rendent le covoiturage accessible à tous.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 justify-content-center mt-3">
      <!-- Card 4 -->
      <div class="col-12 col-md-4 col-lg-4 text-center">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <i class="fas fa-shield-alt fa-3x mb-3" style="color:#3ca54d;"></i>
            <h5 class="card-title">Sécurité garantie</h5>
            <p class="card-text text-muted">
              Tous nos conducteurs sont vérifiés et notés pour assurer un trajet en toute confiance.
            </p>
          </div>
        </div>
      </div>

      <!-- Card 5 -->
      <div class="col-12 col-md-4 col-lg-4 text-center">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <i class="fas fa-mobile-alt fa-3x mb-3" style="color:#3ca54d;"></i>
            <h5 class="card-title">Interface intuitive</h5>
            <p class="card-text text-muted">
              Réservez facilement vos trajets grâce à notre plateforme simple et rapide.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="about-us" class="py-5" style="background-color: #e6f2e9; color: #2a5d34;">
  <div class="container">
    <h2 class="text-center mb-5 fw-bold" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
      Notre histoire &amp; nos objectifs
    </h2>

    <p class="lead text-center mx-auto mb-5" style="max-width: 700px; font-size: 1.25rem; line-height: 1.6; font-weight: 500;">
      Fondée en 2023, EcoRide est née d’une passion pour un transport plus responsable et accessible à tous. 
      Nous croyons que le covoiturage écologique peut transformer la manière dont nous voyageons, en réduisant notre impact environnemental tout en créant du lien social.
    </p>

    <div class="row justify-content-center gx-5">
      <div class="col-md-6 col-lg-5 mb-4">
        <div class="about-box p-4 h-100" style="background: white; border-radius: 12px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);">
          <h4 class="mb-3 fw-semibold" style="color: #1f4324;">Notre mission</h4>
          <p style="line-height: 1.5; font-weight: 500;">
            Offrir une plateforme conviviale et sécurisée où conducteurs et passagers peuvent se rencontrer pour partager leurs trajets, tout en privilégiant des véhicules écologiques.
          </p>
        </div>
      </div>

      <div class="col-md-6 col-lg-5 mb-4">
        <div class="about-box p-4 h-100" style="background: white; border-radius: 12px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);">
          <h4 class="mb-3 fw-semibold" style="color: #1f4324;">Nos objectifs</h4>
          <ul style="line-height: 1.5; font-weight: 500; padding-left: 1.25rem;">
            <li>Réduire les émissions de CO₂ en favorisant le partage de véhicules propres.</li>
            <li>Encourager une communauté responsable, basée sur la confiance et le respect mutuel.</li>
            <li>Faciliter l’accès au covoiturage grâce à une interface intuitive et efficace.</li>
            <li>Promouvoir l’économie circulaire et solidaire dans le secteur du transport.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>


<?php include 'includes/footer.php'; ?>
