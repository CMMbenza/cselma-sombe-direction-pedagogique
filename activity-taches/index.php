<?php
// /directeur/activity-taches/index.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();

$msg = '';
$error = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $msg = "Tâches journalières ajoutées avec succès.";
    if ($_GET['msg'] === 'updated') $msg = "Tâche modifiée avec succès.";
    if ($_GET['msg'] === 'deleted') $msg = "Tâche supprimée avec succès.";
    if ($_GET['msg'] === 'status_updated') $msg = "Statut mis à jour avec succès.";
}

$action = $_GET['action'] ?? '';

// Action de changement de statut rapide depuis l'index (Réservé au directeur)
if ($action === 'change_status' && isset($isDirecteur) && $isDirecteur) {
    $id = (int)($_GET['id'] ?? 0);
    $new_status = $_GET['status'] ?? '';
    if ($id > 0 && in_array($new_status, ['approuvee', 'rejeter', 'en attente'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE directeur_activite_taches SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            header("Location: index.php?msg=status_updated");
            exit();
        } catch (Throwable $e) {
            $error = "Erreur lors de la mise à jour du statut.";
        }
    }
}

// Action de suppression
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmtFile = $pdo->prepare("SELECT piece_jointe FROM directeur_activite_taches WHERE id = ?");
            $stmtFile->execute([$id]);
            $fileToDelete = $stmtFile->fetchColumn();
            
            if ($fileToDelete) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM directeur_activite_taches WHERE piece_jointe = ? AND id <> ?");
                $chk->execute([$fileToDelete, $id]);
                if ($chk->fetchColumn() == 0) {
                    $path = dirname(__DIR__, 2) . '/uploads/attachement_activites/' . $fileToDelete;
                    if (file_exists($path)) { unlink($path); }
                }
            }

            $stmt = $pdo->prepare("DELETE FROM directeur_activite_taches WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: index.php?msg=deleted");
            exit();
        } catch (Throwable $e) {
            $error = "Erreur lors de la suppression.";
        }
    }
}

// Récupération des tâches avec jointure sur la table `users`
$filtre_date = trim((string)($_GET['filtre_date'] ?? ''));
try {
    $sql = "
        SELECT t.*, 
               COALESCE(u.role, CONCAT('Utilisateur #', t.user_id), 'Inconnu') AS proprietaire
        FROM directeur_activite_taches t
        LEFT JOIN users u ON u.id = t.user_id
    ";

    if ($filtre_date !== '') {
        $sql .= " WHERE t.date_tache = ? ORDER BY t.date_tache DESC, t.heure_tache DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$filtre_date]);
        $taches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql .= " ORDER BY t.date_tache DESC, t.heure_tache DESC LIMIT 100";
        $taches = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $taches = [];
    $error = "Impossible de charger les tâches : " . $e->getMessage();
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1 text-dark">📋 Activités & Tâches Journalières</h3>
                <p class="text-muted mb-0 small">Suivi et journal des activités du Directeur (lignes dynamiques)</p>
            </div>
            <a href="create-update-activity-taches.php"
                class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm text-decoration-none">
                ➕ Nouvelle Tâche
            </a>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show rounded-3"><?= e($msg) ?><button
            type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show rounded-3"><?= e($error) ?><button
            type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Filtrer par date :</label>
                    <input type="date" name="filtre_date" class="form-control rounded-3"
                        value="<?= htmlspecialchars($filtre_date) ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 fw-semibold">Filtrer</button>
                    <?php if ($filtre_date !== ''): ?>
                    <a href="index.php" class="btn btn-secondary rounded-3">Voir tout</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 ps-4">Date</th>
                        <th class="py-3">Heure</th>
                        <?php if (isset($isDirecteur) && $isDirecteur): ?><th class="py-3">Propriétaire</th>
                        <?php endif; ?>
                        <th class="py-3">Description de la tâche</th>
                        <th class="py-3">Observation</th>
                        <th class="py-3 text-danger">Commentaire (DGP)</th>
                        <th class="py-3">Statut</th>
                        <th class="py-3">Fichier</th>
                        <th class="py-3 text-center" style="width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($taches)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted fw-semibold">Aucune tâche enregistrée.</td>
                    </tr>
                    <?php else: foreach ($taches as $t): ?>
                    <tr>
                        <td class="ps-4 text-nowrap fw-bold text-dark">
                            <?= date('d/m/Y', strtotime($t['date_tache'])) ?>
                        </td>
                        <td class="text-nowrap text-muted">
                            🕒 <?= htmlspecialchars($t['heure_tache']) ?>
                        </td>
                        <?php if (isset($isDirecteur) && $isDirecteur): ?>
                        <td>
                            <span
                                class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($t['proprietaire']) ?></span>
                        </td>
                        <?php endif; ?>
                        <td>
                            <div class="fw-bold text-dark"><?= nl2br(htmlspecialchars($t['description_taches'])) ?>
                            </div>
                        </td>
                        <td>
                            <span class="text-secondary"><?= nl2br(htmlspecialchars($t['observation'] ?? '-')) ?></span>
                        </td>
                         
                        <td>
                            <span
                                class="text-danger italic"><?= nl2br(htmlspecialchars($t['commentaire'] ?? '-')) ?></span>
                        </td>
                        
                        <td>
                            <?php 
                                $badgeBg = 'warning text-dark';
                                $statusLabel = 'En attente';
                                if ($t['status'] === 'approuvee') { $badgeBg = 'success'; $statusLabel = 'Approuvée'; }
                                elseif ($t['status'] === 'rejeter') { $badgeBg = 'danger'; $statusLabel = 'Rejetée'; }
                            ?>

                            <?php if (isset($isDirecteur) && $isDirecteur): ?>
                            <!-- Si c'est un directeur : Affichage interactif avec menu déroulant pour modifier -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-<?= $badgeBg ?> dropdown-toggle rounded-pill px-3 fw-bold"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?= $statusLabel ?>
                                </button>
                                <ul class="dropdown-menu shadow-sm border-0 rounded-3">
                                    <li><a class="dropdown-item text-success fw-semibold"
                                            href="index.php?action=change_status&id=<?= $t['id'] ?>&status=approuvee">✅
                                            Approuver</a></li>
                                    <li><a class="dropdown-item text-danger fw-semibold"
                                            href="index.php?action=change_status&id=<?= $t['id'] ?>&status=rejeter">❌
                                            Rejeter</a></li>
                                    <li><a class="dropdown-item text-warning fw-semibold"
                                            href="index.php?action=change_status&id=<?= $t['id'] ?>&status=en attente">⏳
                                            En attente</a></li>
                                </ul>
                            </div>
                            <?php else: ?>
                            <!-- Si ce n'est pas un directeur : Affichage simple du statut -->
                            <span class="badge bg-<?= $badgeBg ?> rounded-pill px-3 py-2"><?= $statusLabel ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($t['piece_jointe'])): ?>
                            <a href="../../uploads/attachement_activites/<?= urlencode($t['piece_jointe']) ?>"
                                target="_blank" class="btn btn-sm btn-secondary rounded-pill px-3">📎 Voir</a>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-nowrap">
                            <button type="button" class="btn btn-sm btn-info rounded-3 view-btn"
                                data-date="<?= date('d/m/Y', strtotime($t['date_tache'])) ?>"
                                data-heure="<?= htmlspecialchars($t['heure_tache']) ?>"
                                data-user="<?= htmlspecialchars($t['proprietaire']) ?>"
                                data-description="<?= htmlspecialchars($t['description_taches'], ENT_QUOTES) ?>"
                                data-observation="<?= htmlspecialchars($t['observation'] ?? '', ENT_QUOTES) ?>"
                                data-commentaire="<?= htmlspecialchars($t['commentaire'] ?? '', ENT_QUOTES) ?>"
                                data-status="<?= htmlspecialchars($t['status']) ?>"
                                data-file="<?= htmlspecialchars($t['piece_jointe'] ?? '', ENT_QUOTES) ?>"
                                data-bs-toggle="modal" data-bs-target="#viewModal" title="Voir les détails">👁️
                                View</button>
                            <a href="create-update-activity-taches.php?id=<?= $t['id'] ?>"
                                class="btn btn-sm btn-warning rounded-3" title="Modifier">✏️</a>
                            <a href="index.php?action=delete&id=<?= $t['id'] ?>" class="btn btn-sm btn-danger rounded-3"
                                onclick="return confirm('Voulez-vous vraiment supprimer cette ligne ?');"
                                title="Supprimer">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL VIEW DETAILS -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-info text-white p-4 border-0">
                <h5 class="modal-title fw-bold">👁️ Détails de la Tâche</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Date :</label>
                    <div id="view_date" class="fw-bold text-dark"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Heure :</label>
                    <div id="view_heure" class="fw-bold text-dark"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Propriétaire :</label>
                    <div id="view_user" class="fw-bold text-dark"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Statut :</label>
                    <div id="view_status" class="fw-bold"></div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Description des tâches :</label>
                    <div id="view_description" class="p-3 bg-light rounded-3 text-dark"></div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Observation :</label>
                    <div id="view_observation" class="p-3 bg-light rounded-3 text-dark"></div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Commentaire (DGP) :</label>
                    <div id="view_commentaire" class="p-3 bg-light rounded-3 text-dark"></div>
                </div>
                <div class="col-12" id="view_file_container" style="display: none;">
                    <label class="form-label fw-bold small text-secondary">Pièce jointe :</label>
                    <div>
                        <a id="view_file_link" href="#" target="_blank"
                            class="btn btn-sm btn-primary rounded-pill px-3">📎 Ouvrir le fichier joint</a>
                    </div>
                </div>
            </div>
            <div class="modal-footer p-3 border-0 bg-light-subtle">
                <button type="button" class="btn btn-secondary rounded-3 px-4" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-btn');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            document.getElementById('view_date').textContent = data.date;
            document.getElementById('view_heure').textContent = data.heure;
            document.getElementById('view_user').textContent = data.user;

            let statusBadge = '';
            if (data.status === 'approuvee') statusBadge =
                '<span class="badge bg-success">Approuvée</span>';
            else if (data.status === 'rejeter') statusBadge =
                '<span class="badge bg-danger">Rejetée</span>';
            else statusBadge = '<span class="badge bg-warning text-dark">En attente</span>';
            document.getElementById('view_status').innerHTML = statusBadge;

            document.getElementById('view_description').innerHTML = data.description ? data
                .description.replace(/\n/g, '<br>') : '-';
            document.getElementById('view_observation').innerHTML = data.observation ? data
                .observation.replace(/\n/g, '<br>') : '-';
            document.getElementById('view_commentaire').innerHTML = data.commentaire ? data
                .commentaire.replace(/\n/g, '<br>') : '-';

            const fileContainer = document.getElementById('view_file_container');
            const fileLink = document.getElementById('view_file_link');
            if (data.file && data.file.trim() !== '') {
                fileLink.href = '../../uploads/attachement_activites/' + encodeURIComponent(data
                    .file);
                fileContainer.style.display = 'block';
            } else {
                fileContainer.style.display = 'none';
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>