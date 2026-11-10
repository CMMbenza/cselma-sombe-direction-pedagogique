<?php
// /directeur/fiche-de-preparation-detaillee/fiche-modele-das-view.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: fiche-modele-das.php");
    exit();
}

$fiche = null;
$classesFiche = [];

try {
    // Récupérer la fiche DAS
    $stmt = $pdo->prepare("SELECT * FROM fiches_das WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $fiche = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fiche) {
        header("Location: fiche-modele-das.php?error=not_found");
        exit();
    }

    // Récupérer les classes associées à cette fiche DAS
    $stmtClasses = $pdo->prepare("
        SELECT c.id, CONCAT(c.description, ' - ', cy.description) AS description_complete
        FROM fiche_das_classes fc
        JOIN classe c ON c.id = fc.classe_id
        JOIN cycle cy ON cy.id = c.cycle
        WHERE fc.fiche_id = ?
        ORDER BY c.description
    ");
    $stmtClasses->execute([$id]);
    $classesFiche = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = "Erreur lors du chargement de la fiche DAS : " . $e->getMessage();
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- En-tête avec boutons d'action (Masqué à l'impression) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white no-print">
        <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1 text-dark">📄 Détail de la Fiche de Préparation (DAS)</h3>
                <p class="text-muted mb-0 small">Fiche N° : <span
                        class="fw-semibold text-primary"><?= e($fiche['fiche_num']) ?></span></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button onclick="window.print();" class="btn btn-danger rounded-3 px-3 py-2 fw-semibold shadow-sm">
                    🖨️ Imprimer / PDF
                </button>
                <a href="fiche-modele-das.php"
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
                <h2 class="fw-bold text-uppercase text-dark mb-1">Fiche de Préparation Pédagogique (DAS)</h2>
                <p class="text-muted mb-0">Référence : <strong><?= e($fiche['fiche_num']) ?></strong></p>
            </div>

            <!-- Informations Générales -->
            <div class="row g-3 mb-4 bg-light p-3 rounded-4 border">
                <div class="row">
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Date :</span>
                        <strong
                            class="text-dark"><?= !empty($fiche['date_tache']) ? date('d/m/Y', strtotime($fiche['date_tache'])) : '-' ?></strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Durée :</span>
                        <strong class="text-danger"><?= e($fiche['duree']) ?: '-' ?></strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Discipline :</span>
                        <strong class="text-dark"><?= e($fiche['discipline']) ?: '-' ?></strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Sous-domaine :</span>
                        <strong class="text-dark"><?= e($fiche['sous_domaine']) ?: '-' ?></strong>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Enseignant(e) :</span>
                        <strong class="text-dark"><?= e($fiche['enseignant']) ?: '-' ?></strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Code :</span>
                        <strong class="text-dark"><?= e($fiche['code']) ?: '-' ?></strong>
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

                <div class="row mt-2">
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Titre :</span>
                        <strong class="text-dark"><?= e($fiche['titre']) ?: '-' ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Sous-titre :</span>
                        <strong class="text-dark"><?= e($fiche['sous_titre']) ?: '-' ?></strong>
                    </div>
                </div>
            </div>

            <!-- Références, Madi & Compétences -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 h-100 bg-white">
                        <h6 class="fw-bold text-dark mb-2">📑 Référence</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['reference'])) ?: '<em class="text-muted">Aucune</em>' ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 h-100 bg-white">
                        <h6 class="fw-bold text-dark mb-2">🛠️ Madi (Matériel didactique)</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['madi'])) ?: '<em class="text-muted">Aucun</em>' ?></p>
                    </div>
                </div>
                <div class="col-md-12 mt-3">
                    <div class="p-3 border rounded-3 bg-white">
                        <h6 class="fw-bold text-primary mb-2">🎯 Compétence</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['competence'])) ?: '<em class="text-muted">Non défini</em>' ?></p>
                    </div>
                </div>
                <div class="col-md-12 mt-3">
                    <div class="p-3 border rounded-3 bg-white">
                        <h6 class="fw-bold text-dark mb-2">💡 Exemple de situation</h6>
                        <p class="mb-0 text-secondary">
                            <?= nl2br(e($fiche['exemple_situation'])) ?: '<em class="text-muted">Aucun</em>' ?></p>
                    </div>
                </div>
            </div>

            <!-- Tableau des activités (Enseignant / Élève) -->
            <h4 class="fw-bold text-dark mb-3 mt-4">📋 Déroulement des Activités</h4>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 20%;">Étapes</th>
                            <th style="width: 40%;">Activités Enseignant(e)</th>
                            <th style="width: 40%;">Activités Élève(s)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold bg-light">Vérification</td>
                            <td><?= nl2br(e($fiche['activite_ens_verif'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['activite_eleve_verif'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Motivation</td>
                            <td><?= nl2br(e($fiche['activite_ens_motivation'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['activite_eleve_motivation'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Principal / Leçon</td>
                            <td><?= nl2br(e($fiche['activite_ens_principal'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['activite_eleve_principal'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Synthèse</td>
                            <td><?= nl2br(e($fiche['activite_ens_synthese'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['activite_eleve_synthese'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Évaluation</td>
                            <td><?= nl2br(e($fiche['activite_ens_eval'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['activite_eleve_eval'])) ?: '-' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Situation similaire</td>
                            <td><?= nl2br(e($fiche['activite_ens_sit_similaire'])) ?: '-' ?></td>
                            <td><?= nl2br(e($fiche['activite_eleve_sit_similaire'])) ?: '-' ?></td>
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
                        <a href="../../../uploads/attachement_fiches/<?= urlencode($fiche['piece_jointe']) ?>"
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
    }

    body {
        background-color: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    body * {
        visibility: hidden;
    }

    .impression,
    .impression * {
        visibility: visible;
    }

    .impression {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

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