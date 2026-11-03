<?php
// /directeur/activity-taches/agenda-autre-role
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

// Récupération du rôle de l'utilisateur connecté ou sélectionné
$user_role_session = $_SESSION['user']['role'] ?? '';
$role_actif = trim((string)($_GET['role'] ?? $user_role_session));

// Action de changement de statut rapide depuis l'index (Réservé au directeur)
if ($action === 'change_status' && isset($isDirecteur) && $isDirecteur) {
    $id = (int)($_GET['id'] ?? 0);
    $new_status = $_GET['status'] ?? '';
    if ($id > 0 && in_array($new_status, ['approuvee', 'rejeter', 'en attente'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE directeur_activite_taches SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            header("Location: agenda-autre-role.php?role=" . urlencode($role_actif) . "&msg=status_updated");
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
            header("Location: agenda-autre-role.php?role=" . urlencode($role_actif) . "&msg=deleted");
            exit();
        } catch (Throwable $e) {
            $error = "Erreur lors de la suppression.";
        }
    }
}

// Récupération des tâches avec jointure sur la table `users` filtrée selon le rôle utilisateur
$filtre_date = trim((string)($_GET['filtre_date'] ?? ''));
try {
    $sql = "
        SELECT t.*, 
               COALESCE(u.username, CONCAT('Utilisateur #', t.user_id), 'Inconnu') AS proprietaire,
               COALESCE(u.role, 'Non défini') AS role_utilisateur
        FROM directeur_activite_taches t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE 1=1
    ";
    $params = [];

    // Filtrer selon le rôle utilisateur (maternelle, primaire, secondaire-humanite)
    if ($role_actif !== '' && $role_actif !== 'tous') {
        $sql .= " AND u.role = ?";
        $params[] = $role_actif;
    }

    if ($filtre_date !== '') {
        $sql .= " AND t.date_tache = ?";
        $params[] = $filtre_date;
    }

    $sql .= " ORDER BY t.date_tache DESC, t.heure_tache DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $taches = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <h3 class="fw-bold mb-1 text-dark">📋 Agenda - Activités & Tâches Journalières</h3>
                <p class="text-muted mb-0 small">Suivi et journal des activités filtré par rôle utilisateur</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="exportTableToWord('tableTaches', 'Agenda_Activites_Taches')"
                    class="btn btn-success rounded-3 px-3 py-2 fw-semibold shadow-sm">
                    📄 Exporter Word / Imprimer
                </button>
                <a href="create-update-activity-taches.php"
                    class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm text-decoration-none">
                    ➕ Nouvelle Tâche
                </a>
            </div>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show rounded-3"><?= e($msg) ?><button
            type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show rounded-3"><?= e($error) ?><button
            type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <!-- BLOCS DE SÉLECTION PAR RÔLE & FILTRE DATE -->
    <div class="row g-3 mb-4">
        <div class="col-md-9">
            <div class="row g-2">
                <div class="col-md-3">
                    <a href="agenda-autre-role.php?role=tous&filtre_date=<?= htmlspecialchars($filtre_date) ?>"
                        class="text-decoration-none">
                        <div
                            class="card border-0 shadow-sm rounded-4 p-2 text-center <?= ($role_actif === 'tous' || $role_actif === '') ? 'bg-primary text-white' : 'bg-white text-dark' ?>">
                            <small class="fw-bold">🏢 Tous</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="agenda-autre-role.php?role=maternelle&filtre_date=<?= htmlspecialchars($filtre_date) ?>"
                        class="text-decoration-none">
                        <div
                            class="card border-0 shadow-sm rounded-4 p-2 text-center <?= $role_actif === 'maternelle' ? 'bg-primary text-white' : 'bg-white text-dark' ?>">
                            <small class="fw-bold">🧸 Maternelle</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="agenda-autre-role.php?role=primaire&filtre_date=<?= htmlspecialchars($filtre_date) ?>"
                        class="text-decoration-none">
                        <div
                            class="card border-0 shadow-sm rounded-4 p-2 text-center <?= $role_actif === 'primaire' ? 'bg-primary text-white' : 'bg-white text-dark' ?>">
                            <small class="fw-bold">🎒 Primaire</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="agenda-autre-role.php?role=secondaire-humanite&filtre_date=<?= htmlspecialchars($filtre_date) ?>"
                        class="text-decoration-none">
                        <div
                            class="card border-0 shadow-sm rounded-4 p-2 text-center <?= $role_actif === 'secondaire-humanite' ? 'bg-primary text-white' : 'bg-white text-dark' ?>">
                            <small class="fw-bold">📚 Secondaire</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <form method="get" class="card border-0 shadow-sm rounded-4 p-1 bg-white">
                <input type="hidden" name="role" value="<?= htmlspecialchars($role_actif) ?>">
                <div class="input-group input-group-sm">
                    <input type="date" name="filtre_date" class="form-control rounded-start-3"
                        value="<?= htmlspecialchars($filtre_date) ?>" onchange="this.form.submit()">
                    <button type="submit" class="btn btn-primary rounded-end-3">Filtrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tableTaches">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 ps-4">Date</th>
                        <th class="py-3">Heure</th>
                        <th class="py-3">Propriétaire</th>
                        <th class="py-3">Description de la tâche</th>
                        <th class="py-3">Observation</th>
                        <th class="py-3 text-danger">Commentaire (DGP)</th>
                        <th class="py-3">Statut</th>
                        <th class="py-3 no-export">Fichier</th>
                        <th class="py-3 text-center no-export" style="width: 200px;">Actions</th>
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
                        <td>
                            <span
                                class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($t['proprietaire']) ?></span>
                            <br><small class="text-muted"><?= htmlspecialchars($t['role_utilisateur']) ?></small>
                        </td>
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
                                            href="agenda-autre-role.php?role=<?= htmlspecialchars($role_actif) ?>&action=change_status&id=<?= $t['id'] ?>&status=approuvee">✅
                                            Approuver</a></li>
                                    <li><a class="dropdown-item text-danger fw-semibold"
                                            href="agenda-autre-role.php?role=<?= htmlspecialchars($role_actif) ?>&action=change_status&id=<?= $t['id'] ?>&status=rejeter">❌
                                            Rejeter</a></li>
                                    <li><a class="dropdown-item text-warning fw-semibold"
                                            href="agenda-autre-role.php?role=<?= htmlspecialchars($role_actif) ?>&action=change_status&id=<?= $t['id'] ?>&status=en attente">⏳
                                            En attente</a></li>
                                </ul>
                            </div>
                            <?php else: ?>
                            <!-- Si ce n'est pas un directeur : Affichage simple du statut -->
                            <span class="badge bg-<?= $badgeBg ?> rounded-pill px-3 py-2"><?= $statusLabel ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="no-export">
                            <?php if (!empty($t['piece_jointe'])): ?>
                            <a href="../../uploads/attachement_activites/<?= urlencode($t['piece_jointe']) ?>"
                                target="_blank" class="btn btn-sm btn-secondary rounded-pill px-3">📎 Voir</a>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-nowrap no-export">
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
                            <a href="agenda-autre-role.php?role=<?= htmlspecialchars($role_actif) ?>&action=delete&id=<?= $t['id'] ?>"
                                class="btn btn-sm btn-danger rounded-3"
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

// FONCTION EXPORTATION WORD / IMPRESSION PROPRE
function exportTableToWord(tableID, filename = '') {
    const table = document.getElementById(tableID);
    const cloneTable = table.cloneNode(true);

    // Supprimer les éléments non exportables (Fichiers et Actions)
    cloneTable.querySelectorAll('.no-export').forEach(el => el.remove());

    const htmlContent = `
        <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
        <head>
            <meta charset='utf-8'>
            <title>Exportation Agenda Tâches</title>
            <style>
                @page Section1 { size: 841.9pt 595.3pt; mso-page-orientation: landscape; margin: 1.0in 1.0in 1.0in 1.0in; }
                div.Section1 { page: Section1; }
                table { border-collapse: collapse; width: 100%; font-family: Calibri, sans-serif; }
                th, td { border: 1px solid #cccccc; padding: 8px; text-align: left; font-size: 11pt; }
                th { background-color: #f2f2f2; font-weight: bold; }
                h2 { font-family: Calibri, sans-serif; color: #333333; }
            </style>
        </head>
        <body>
            <div class="Section1">
                <h2>📋 Agenda - Activités & Tâches Journalières (Rôle: <?= htmlspecialchars($role_actif) ?>)</h2>
                ${cloneTable.outerHTML}
            </div>
        </body>
        </html>
    `;

    const blob = new Blob(['\ufeff', htmlContent], {
        type: 'application/msword'
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = (filename ? filename : 'Tableau_Agenda') + '.doc';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>