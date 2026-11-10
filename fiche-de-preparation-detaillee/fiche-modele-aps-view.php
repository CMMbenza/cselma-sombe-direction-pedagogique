<?php
// /directeur/fiche-de-preparation-detaillee/fiche-modele-aps-view.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: fiche-modele-aps.php");
    exit();
}

$fiche = null;
$classesFiche = [];

try {
    // Récupérer la fiche APS
    $stmt = $pdo->prepare("SELECT faps.*, CONCAT (ag.nom ,' ', ag.postnom ,' ', ag.prenom) AS noms FROM fiches_aps faps INNER JOIN agent ag ON ag.id = faps.noms WHERE faps.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $fiche = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fiche) {
        header("Location: fiche-modele-aps.php?error=not_found");
        exit();
    }

    // Récupérer les classes associées à cette fiche
    $stmtClasses = $pdo->prepare("
        SELECT c.id, CONCAT(c.description, ' - ', cy.description) AS description_complete
        FROM fiche_classes fc
        JOIN classe c ON c.id = fc.classe_id
        JOIN cycle cy ON cy.id = c.cycle
        WHERE fc.fiche_id = ?
        ORDER BY c.description
    ");
    $stmtClasses->execute([$id]);
    $classesFiche = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = "Erreur lors du chargement de la fiche : " . $e->getMessage();
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- En-tête avec boutons d'action (Masqué à l'impression) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white no-print">
        <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1 text-dark">📄 Détail de la Fiche de Préparation (APS)</h3>
                <p class="text-muted mb-0 small">Fiche N° : <span
                        class="fw-semibold text-primary"><?= e($fiche['fiche_num']) ?></span></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button onclick="window.print();" class="btn btn-danger rounded-3 px-3 py-2 fw-semibold shadow-sm">
                    🖨️ Imprimer / PDF
                </button>
                <a href="fiche-modele-aps.php"
                    class="btn btn-dark rounded-3 px-4 py-2 fw-semibold shadow-sm text-decoration-none">
                    ⬅️ Retour à la liste
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger rounded-3"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- CONTENU DE LA FICHE STYLE OFFICIEL -->
    <div class="card border-0 shadow-lg rounded-4 bg-white p-4 p-md-5 mb-5" id="printableArea">
        <div class="impression">
            <!-- En-tête du document -->
            <div class="text-center border-bottom pb-4 mb-4">
                <h2 class="fw-bold text-uppercase text-dark mb-1">Fiche de Préparation Pédagogique (APS)</h2>
                <p class="text-muted mb-0">Référence : <strong><?= e($fiche['fiche_num']) ?></strong></p>
            </div>

            <!-- Informations Générales -->
            <div class="row g-3 mb-4 bg-light p-3 rounded-4 border">
                <div class="row">
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Date/Heure :</span>
                        <strong
                            class="text-primary"><?= !empty($fiche['date_tache']) ? date('d/m/Y', strtotime($fiche['date_tache'])) : '-' ?>
                            - <span class="text-danger"><?= e($fiche['heure']) ?: '-' ?></span> </strong>
                    </div>

                    <div class="col-md-3">
                        <span class="text-muted small d-block">Branche :</span>
                        <strong class="text-dark"><?= e($fiche['branche']) ?: '-' ?></strong>
                    </div>

                    <div class="col-md-3">
                        <span class="text-muted small d-block">Enseignant(e) / Noms :</span>
                        <strong class="text-dark"><?= e($fiche['noms']) ?: '-' ?></strong>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Fonction :</span>
                        <strong class="text-dark"><?= e($fiche['fonction']) ?: '-' ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Classe(s) concernée(s) :</span>
                        <strong class="text-primary">
                            <?php 
                        if (!empty($classesFiche)) {
                            $nomsClasses = array_column($classesFiche, 'description_complete');
                            echo e(implode(', ', $nomsClasses));
                        } else {
                            echo 'Aucune classe assignée';
                        }
                        ?>
                        </strong>
                    </div>
                </div>
            </div>

            <!-- Compétences et Sujets -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 h-100 bg-white">
                        <h6 class="fw-bold text-primary mb-2">🎯 Compétence Intégrée</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['competence_integree'])) ?: '<em class="text-muted">Non défini</em>' ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 h-100 bg-white">
                        <h6 class="fw-bold text-primary mb-2">🎯 Compétence Visée</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['competence_visee'])) ?: '<em class="text-muted">Non défini</em>' ?></p>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="p-3 border rounded-3 h-100 bg-white">
                        <h6 class="fw-bold text-dark mb-2">📖 Sujet de Révision</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['sujet_revision'])) ?: '<em class="text-muted">Aucun</em>' ?></p>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="p-3 border rounded-3 h-100 bg-white">
                        <h6 class="fw-bold text-dark mb-2">📚 Sujet de la Leçon</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['sujet_lecon'])) ?: '<em class="text-muted">Non défini</em>' ?></p>
                    </div>
                </div>
            </div>

            <!-- Objectifs & Matériel -->
            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <div class="p-3 border rounded-3 bg-white">
                        <h6 class="fw-bold text-dark mb-2">📌 Objectifs Pédagogiques</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['objectifs_pedagogiques'])) ?: '<em class="text-muted">Non défini</em>' ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="p-3 border rounded-3 h-100 bg-white">
                        <h6 class="fw-bold text-dark mb-2">🛠️ Matériel Didactique</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['materiel_didactique'])) ?: '<em class="text-muted">Aucun</em>' ?></p>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="p-3 border rounded-3 h-100 bg-white">
                        <h6 class="fw-bold text-dark mb-2">📑 Référence Bibliographique</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['reference_bibliographique'])) ?: '<em class="text-muted">Aucune</em>' ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tableau des étapes de la leçon (Méthodes & Matières) -->
            <h4 class="fw-bold text-dark mb-3 mt-4">📋 Déroulement de la Leçon</h4>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 20%;">Étapes</th>
                            <th style="width: 35%;">Méthodes & Stratégies</th>
                            <th style="width: 45%;">Matière / Contenu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold bg-light">Rappel</td>
                            <td><?= nl2br(e($fiche['methode_rappel'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['matiere_rappel'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Motivation</td>
                            <td><?= nl2br(e($fiche['methode_motivation'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['matiere_motivation'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Annonce du sujet</td>
                            <td><?= nl2br(e($fiche['methode_annonce'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['matiere_annonce'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Développement</td>
                            <td><?= nl2br(e($fiche['methode_developpement'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['matiere_developpement'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Synthèse</td>
                            <td><?= nl2br(e($fiche['methode_synthese'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['matiere_synthese'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Application</td>
                            <td><?= nl2br(e($fiche['methode_application'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['matiere_application'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Tâche / Devoir</td>
                            <td><?= nl2br(e($fiche['methode_tache'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['matiere_tache'])) ?: '-' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Commentaires & Pièce jointe -->
            <div class="row g-3 mt-3">
                <?php if (!empty($fiche['commentaires'])): ?>
                <div class="col-12">
                    <div class="p-3 border rounded-3 bg-light">
                        <h6 class="fw-bold text-danger mb-2">💬 Commentaires / Observations</h6>
                        <p class="mb-0 text-dark"><?= nl2br(e($fiche['commentaires'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($fiche['piece_jointe'])): ?>
                <div class="col-12 mt-3 no-print">
                    <div class="p-3 border rounded-3 bg-white d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-dark">📎 Pièce jointe associée :</span>
                        <a href="../../../uploads/attachement_fiche_detaillee_pedagogique/<?= urlencode($fiche['piece_jointe']) ?>"
                            target="_blank" class="btn btn-sm btn-primary rounded-pill px-4">
                            Ouvrir le fichier joint
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div> <!-- Fin de la div .impression -->
    </div>
</div>

<style>
@media print {
    @page {
        margin: 5mm;
        /* Réduit l'espace vide des marges de la page d'impression */
    }

    body {
        background-color: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Masquer tout le contenu de la page par défaut */
    body * {
        visibility: hidden;
    }

    /* Rendre visible uniquement le conteneur .impression et ses enfants */
    .impression,
    .impression * {
        visibility: visible;
    }

    /* Forcer le bloc d'impression à remonter tout en haut et prendre toute la largeur */
    .impression {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Supprimer les contraintes visuelles des conteneurs parents */
    .card,
    .container-fluid {
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
}
</style>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>