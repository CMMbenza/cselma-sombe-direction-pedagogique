<?php
// /directeur/layout/navbar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/head.php';

$uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$BASE    = defined('BASE_URL') ? BASE_URL : '/directeur';

// Récupération et vérification du rôle Directeur
$userRole    = (string) ($_SESSION['user']['role'] ?? '');
$isDirecteur = (strtolower(trim($userRole)) === 'directeur');

function is_active(array|string $needles, string $haystack): string {
    $needles = is_array($needles) ? $needles : [$needles];
    foreach ($needles as $n) {
        if ($n !== '' && strpos($haystack, $n) !== false) {
            return 'active';
        }
    }
    return '';
}

function aria_current(string $class): string {
    return $class === 'active' ? 'aria-current="page"' : '';
}
?>

<style>
.dropdown-submenu {
    position: relative;
}

.dropdown-submenu>.dropdown-menu {
    display: none;
    position: absolute;
    top: 0;
    left: 100%;
    margin-top: 0;
    min-width: 260px;
    /* Largeur élargie pour éviter les coupures de texte */
    z-index: 1055;
}

.dropdown-submenu.show>.dropdown-menu {
    display: block;
}

.dropdown-submenu>.dropdown-toggle::after {
    float: right;
    margin-top: 8px;
    transform: rotate(-90deg);
}

.dropdown-submenu>.dropdown-toggle.active {
    background-color: #e9ecef;
    color: #0d6efd;
}

@media (max-width: 991.98px) {
    .dropdown-submenu>.dropdown-menu {
        position: static;
        margin-left: 15px;
        box-shadow: none;
        border: 0;
    }

    .dropdown-submenu>.dropdown-toggle::after {
        transform: rotate(0deg);
    }
}
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-3">
    <div class="container-fluid">

        <a class="navbar-brand fw-bold" href="<?= $BASE ?>/dashboard.php">
            Profil Directeur
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="Basculer la navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div id="mainNav" class="collapse navbar-collapse">

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- Tableau de bord -->
                <?php $a = is_active(['/dashboard.php'], $uriPath); ?>

                <li class="nav-item">
                    <a class="nav-link <?= $a ?>" <?= aria_current($a) ?> href="<?= $BASE ?>/dashboard.php">
                        Tableau de bord
                    </a>
                </li>

                <!-- Ménages / Elèves -->
                <?php
                $scholarNeedles = [
                    '/menages/',
                    '/classe/',
                    '/paiements/',
                    '/paiements_divers/'
                ];

                $activeScholar = is_active($scholarNeedles, $uriPath) ? 'active' : '';
                ?>

                <li class="nav-item dropdown">

                    <a class="nav-link dropdown-toggle <?= $activeScholar ?>" href="#" id="scolariteDropdown"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Ménages/Elèves
                    </a>

                    <ul class="dropdown-menu" aria-labelledby="scolariteDropdown">

                        <?php $a = is_active(['/menages/'], $uriPath); ?>

                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/menages/">
                                Ménages (Famille)
                            </a>
                        </li>

                        <?php $a = is_active(['/menages/eleves.php'], $uriPath); ?>

                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/menages/eleves.php">
                                Liste des élèves
                            </a>
                        </li>

                    </ul>
                </li>

                <!-- Annonces -->
                <?php $a = is_active(['/annonces/'], $uriPath); ?>

                <li class="nav-item">
                    <a class="nav-link <?= $a ?>" <?= aria_current($a) ?> href="<?= $BASE ?>/annonces/">
                        Faire un communiqué
                    </a>
                </li>

                <!-- Gestion classe -->
                <?php $a = is_active(['/classe/'], $uriPath); ?>

                <li class="nav-item">
                    <a class="nav-link <?= $a ?>" <?= aria_current($a) ?> href="<?= $BASE ?>/classe/">
                        Gest. classe
                    </a>
                </li>

                <!-- Prof / Enseignant & Sous-menus -->
                <?php
                $addProfNeedles = [
                    '/agents/',
                    '/quiz/',
                    '/affectations/',
                    '/cours/',
                    '/palmares/',
                    '/journal_classe/',
                    '/cours_chapitre_lecon_resume/',
                    '/horaires/',
                    '/previsions_des_matieres/',
                    '/fiche-de-preparation-detaillee/'
                ];

                $activeAddProf = is_active($addProfNeedles, $uriPath) ? 'active' : '';
                ?>

                <li class="nav-item dropdown">

                    <a class="nav-link dropdown-toggle <?= $activeAddProf ?>" href="#" id="ajouterProfDropdown"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Prof/Enseignant
                    </a>

                    <ul class="dropdown-menu" aria-labelledby="ajouterProfDropdown">

                        <?php if ($isDirecteur): ?>

                        <!-- Section Administration -->
                        <li>
                            <a class="dropdown-item <?= is_active(['/agents/'], $uriPath) ?>"
                                href="<?= $BASE ?>/agents/">
                                <i class="bi bi-people"></i> Agent
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item <?= is_active(['/affectations/'], $uriPath) ?>"
                                href="<?= $BASE ?>/affectations/">
                                <i class="bi bi-diagram-3"></i> Affectation
                            </a>
                        </li>

                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <?php endif; ?>

                        <li>
                            <a class="dropdown-item <?= is_active(['/quiz/'], $uriPath) ?>" href="<?= $BASE ?>/quiz/">
                                Quiz
                            </a>
                        </li>

                        <!-- Sous-menu Fiches de préparation (DAS & APS) -->
                        <?php
                        $fichesNeedles = [
                            '/fiche-de-preparation-detaillee/fiche-modele-das.php',
                            '/fiche-de-preparation-detaillee/fiche-modele-aps.php'
                        ];

                        $activeFiches = is_active($fichesNeedles, $uriPath);
                        ?>

                        <li class="dropdown-submenu position-relative">

                            <a class="dropdown-item dropdown-toggle <?= $activeFiches ?>" href="#" id="fichesDropdown"
                                role="button" aria-expanded="false">

                                <i class="bi bi-file-earmark-text"></i>
                                Fiches de détaillée

                            </a>

                            <ul class="dropdown-menu" id="fichesSubmenu">

                                <li>
                                    <a class="dropdown-item <?= is_active('/fiche-modele-das.php', $uriPath) ?>"
                                        href="<?= $BASE ?>/fiche-de-preparation-detaillee/fiche-modele-das.php">
                                        Fiche modèle DAS
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item <?= is_active('/fiche-modele-aps.php', $uriPath) ?>"
                                        href="<?= $BASE ?>/fiche-de-preparation-detaillee/fiche-modele-aps.php">
                                        Fiche modèle APS
                                    </a>
                                </li>

                            </ul>
                        </li>

                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>
                            <a class="dropdown-item <?= is_active(['/cours/'], $uriPath) ?>" href="<?= $BASE ?>/cours/">
                                Cours
                            </a>
                        </li>

                        <!-- Sous-menu Les Palmares (Primaire & Secondaire) -->
                        <?php
                        $palmaresNeedles = [
                            '/palmares/',
                            '/palmares-sec-hum/'
                        ];

                        $activePalmares = is_active($palmaresNeedles, $uriPath);
                        ?>

                        <li class="dropdown-submenu position-relative">

                            <a class="dropdown-item dropdown-toggle <?= $activePalmares ?>" href="#"
                                id="palmaresDropdown" role="button" aria-expanded="false">

                                <i class="bi bi-award"></i>
                                Les Palmares

                            </a>

                            <ul class="dropdown-menu" id="palmaresSubmenu">

                                <li>
                                    <a class="dropdown-item <?= is_active(['/palmares/'], $uriPath) ?>"
                                        href="<?= $BASE ?>/palmares/">
                                        Primaire
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item <?= is_active(['/palmares-sec-hum/'], $uriPath) ?>"
                                        href="<?= $BASE ?>/palmares-sec-hum/">
                                        Sec. - Humanité
                                    </a>
                                </li>

                            </ul>
                        </li>

                        <li>
                            <a class="dropdown-item <?= is_active(['/journal_classe/'], $uriPath) ?>"
                                href="<?= $BASE ?>/journal_classe/">
                                Journal de classe
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item <?= is_active(['/cours_chapitre_lecon_resume/'], $uriPath) ?>"
                                href="<?= $BASE ?>/cours_chapitre_lecon_resume/">
                                Gest. cours (Résumé)
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item <?= is_active(['/horaires/'], $uriPath) ?>"
                                href="<?= $BASE ?>/horaires/">
                                Horaires
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item <?= is_active(['/previsions_des_matieres/'], $uriPath) ?>"
                                href="<?= $BASE ?>/previsions_des_matieres/">
                                Previsions des matières
                            </a>
                        </li>

                    </ul>
                </li>

                <!-- Paramètres -->
                <li class="nav-item dropdown">

                    <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Paramètres
                    </a>

                    <ul class="dropdown-menu" aria-labelledby="settingsDropdown">

                        <?php $a = is_active(['/activity-taches/'], $uriPath); ?>

                        <li>
                            <a class="dropdown-item <?= $a ?>" <?= aria_current($a) ?>
                                href="<?= $BASE ?>/<?= $isDirecteur ? 'activity-taches/' : 'activity-taches/agenda-autre-role.php' ?>">
                                Agenda
                            </a>
                        </li>

                        <?php if ($isDirecteur): ?>

                        <?php $a = is_active(['/settings/blocage.php'], $uriPath); ?>

                        <li>
                            <a class="dropdown-item <?= $a ?>" <?= aria_current($a) ?>
                                href="<?= $BASE ?>/settings/blocage.php">
                                Blocage d'accès
                            </a>
                        </li>

                        <?php endif; ?>

                    </ul>
                </li>

            </ul>

            <!-- Profil & Déconnexion -->
            <div class="d-flex align-items-center gap-2">

                <div class="text-end me-2">

                    <div class="fw-semibold small">
                        <?= e($_SESSION['user']['username'] ?? '') ?>
                    </div>

                    <div class="text-muted small">
                        <?= e($_SESSION['user']['role'] ?? '') ?>
                    </div>

                </div>

                <a class="btn btn-outline-danger btn-sm" href="<?= $BASE ?>/logout.php">
                    Déconnexion
                </a>

            </div>

        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion générique des sous-menus pour tous les éléments possédant la classe dropdown-toggle dans un sous-menu
    document.querySelectorAll('.dropdown-submenu > a.dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const parent = this.closest('.dropdown-submenu');
            if (!parent) return;

            const isOpen = parent.classList.contains('show');

            // Fermer les autres sous-menus ouverts du même niveau
            document.querySelectorAll('.dropdown-submenu.show').forEach(function(menu) {
                if (menu !== parent && !menu.contains(parent)) {
                    menu.classList.remove('show');
                    const t = menu.querySelector(':scope > .dropdown-toggle');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            });

            if (!isOpen) {
                parent.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
            } else {
                parent.classList.remove('show');
                this.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Fermer les sous-menus en cliquant en dehors
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-submenu')) {
            document.querySelectorAll('.dropdown-submenu.show').forEach(function(menu) {
                menu.classList.remove('show');
                const toggle = menu.querySelector(':scope > .dropdown-toggle');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        }
    });
});
</script>

<script type="importmap">
    {
  "imports": {
    "@floating-ui/dom": "https://cdn.jsdelivr.net/npm/@floating-ui/dom@1.7.6/dist/floating-ui.dom.esm.min.js",
    "vanilla-calendar-pro": "https://cdn.jsdelivr.net/npm/vanilla-calendar-pro@3.4.0/index.mjs"
  }
}
</script>
<script type="module" src="https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/js/bootstrap.min.js"
    integrity="sha384-BJf/nQgxZJU68GxBse97Vdlc8Rh+z9PzzXiS3/BjV+1nQPR7IKn+iJnD3DJvCMaU" crossorigin="anonymous">
</script>