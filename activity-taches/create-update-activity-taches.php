<?php
// /directeur/activity-taches/create-update-activity-taches.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();

$error = '';
$id = (int)($_GET['id'] ?? 0); 

$tache = [
    'date_tache' => date('Y-m-d'),
    'heure_tache' => date('H:i'),
    'description_taches' => '',
    'observation' => '',
    'commentaire' => '',
    'piece_jointe' => null,
    'status' => 'en attente'
];

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM directeur_activite_taches WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $tache = $existing;
        } else {
            header("Location: index.php");
            exit();
        }
    } catch (Throwable $e) {
        $error = "Erreur de chargement de la tâche.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_tache         = trim((string)($_POST['date_tache'] ?? ''));
    $commentaire        = trim((string)($_POST['commentaire'] ?? ''));
    $status             = $_POST['status'] ?? 'en attente';
    
    $heures             = $_POST['heure_tache'] ?? [];
    $descriptions       = $_POST['description_taches'] ?? [];
    $observations       = $_POST['observation'] ?? [];

    $user_id = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? ($_SESSION['id'] ?? null));

    if ($date_tache === '' || empty($descriptions)) {
        $error = "La date et au moins une ligne de description sont obligatoires.";
    } else {
        $currentFile = $tache['piece_jointe'];
        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__, 2) . '/uploads/attachement_activites/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($_FILES['piece_jointe']['name']));
            if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $uploadDir . $filename)) {
                if ($currentFile && file_exists($uploadDir . $currentFile)) {
                    unlink($uploadDir . $currentFile);
                }
                $currentFile = $filename;
            }
        }

        try {
            if ($id > 0) {
                $h = $heures[0] ?? date('H:i');
                $d = trim((string)($descriptions[0] ?? ''));
                $o = trim((string)($observations[0] ?? ''));

                $stmt = $pdo->prepare("
                    UPDATE directeur_activite_taches 
                    SET date_tache = ?, heure_tache = ?, description_taches = ?, observation = ?, commentaire = ?, piece_jointe = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$date_tache, $h, $d, $o, $commentaire, $currentFile, 'en attente', $id]);
                header("Location: index.php?msg=updated");
                exit();
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO directeur_activite_taches (date_tache, heure_tache, user_id, description_taches, observation, commentaire, piece_jointe, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                for ($i = 0; $i < count($descriptions); $i++) {
                    $d = trim((string)($descriptions[$i] ?? ''));
                    if ($d === '') continue; 

                    $h = trim((string)($heures[$i] ?? date('H:i')));
                    $o = trim((string)($observations[$i] ?? ''));

                    $stmt->execute([$date_tache, $h, $user_id, $d, $o, $commentaire, $currentFile, $status]);
                }

                $pdo->commit();
                header("Location: index.php?msg=created");
                exit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = "Échec de l'enregistrement : " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div
                    class="d-flex card-header bg-<?= $id > 0 ? 'warning' : 'primary' ?> text-<?= $id > 0 ? 'dark' : 'white' ?> p-4 border-0 rounded-top-4">
                    <h4 class="fw-bold mb-0">
                        <?= $id > 0 ? '✏️ Modifier la Tâche' : '➕ Nouvelles Tâches Journalières' ?>
                    </h4>
                    <div class=""><a href="index.php" class="btn btn-secondary rounded-3 px-4">← Retour</a></div>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                    <div class="alert alert-danger rounded-3 mb-3"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-danger">Date définie *</label>
                            <input type="date" name="date_tache" class="form-control rounded-3"
                                value="<?= htmlspecialchars($tache['date_tache']) ?>" required>
                        </div>
                        <!-- <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">Statut *</label>
                            <select name="status" class="form-select rounded-3" required>
                                <option value="en attente" <?= ($tache['status'] ?? '') === 'en attente' ? 'selected' : '' ?>>⏳ En attente</option>
                                <option value="approuvee" <?= ($tache['status'] ?? '') === 'approuvee' ? 'selected' : '' ?>>✅ Approuvée</option>
                                <option value="rejeter" <?= ($tache['status'] ?? '') === 'rejeter' ? 'selected' : '' ?>>❌ Rejetée</option>
                            </select>
                        </div> -->

                        <!-- SECTION DES LIGNES DYNAMIQUES -->
                        <div class="col-12 mt-4">
                            <label class="form-label fw-bold text-danger mb-2">Détails des activités (Heure,
                                Description, Observation) :</label>

                            <div id="dynamic-lines-container">
                                <div
                                    class="row g-2 align-items-center border p-3 rounded-3 mb-3 bg-light-subtle dynamic-line">
                                    <div class="row mb-2">
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold text-muted">Heure *</label>
                                            <input type="time" name="heure_tache[]" class="form-control rounded-3"
                                                value="<?= htmlspecialchars($tache['heure_tache']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-muted">Description de la tâche
                                                *</label>
                                            <textarea name="description_taches[]" class="form-control rounded-3"
                                                rows="2" placeholder="Décrivez la tâche..."
                                                required><?= htmlspecialchars($tache['description_taches']) ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">Observation</label>
                                            <textarea name="observation[]" class="form-control rounded-3" rows="2"
                                                placeholder="Observations..."><?= htmlspecialchars($tache['observation'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-md-1 text-center mt-4">
                                            <?php if ($id === 0): ?>
                                            <button type="button"
                                                class="btn btn-outline-danger btn-sm remove-line-btn mt-2"
                                                title="Supprimer cette ligne">❌</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($id === 0): ?>
                            <button type="button" id="add-line-btn"
                                class="btn btn-sm btn-success fw-bold rounded-3 mt-2">➕ Ajouter une autre ligne</button>
                            <?php endif; ?>
                        </div>

                        <?php if ($isDirecteur): ?>

                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-secondary">Commentaire général (zone
                                DGP)</label>
                            <textarea class="form-control rounded-3" name="commentaire" id="commentaire" rows="3"
                                placeholder="Laissez un message"><?= htmlspecialchars($tache['commentaire'] ?? '') ?></textarea>
                        </div>

                        <?php endif ?>

                        <div class="col-12 mt-4">
                            <label class="form-label fw-bold small text-secondary">Pièce jointe (Optionnel)</label>
                            <input type="file" name="piece_jointe" class="form-control rounded-3">
                            <?php if (!empty($tache['piece_jointe'])): ?>
                            <div class="mt-2">
                                <a href="../../uploads/attachement_activites/<?= urlencode($tache['piece_jointe']) ?>"
                                    target="_blank" class="small text-primary fw-bold">📄 Fichier actuellement joint
                                    (Ouvrir)</a>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 d-flex justify-content-between pt-3">
                            <button type="submit"
                                class="btn btn-<?= $id > 0 ? 'warning' : 'primary' ?> rounded-3 px-4 fw-bold">
                                <?= $id > 0 ? 'Mettre à jour' : 'Enregistrer toutes les lignes' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('dynamic-lines-container');
    const addButton = document.getElementById('add-line-btn');

    if (addButton) {
        addButton.addEventListener('click', function() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const currentTime = `${hours}:${minutes}`;

            const lineDiv = document.createElement('div');
            lineDiv.className =
                'row g-2 align-items-center border p-3 rounded-3 mb-3 bg-light-subtle dynamic-line';
            lineDiv.innerHTML = `
                <div class="row mb-2">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Heure *</label>
                        <input type="time" name="heure_tache[]" class="form-control rounded-3" value="${currentTime}" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted">Description de la tâche *</label>
                        <textarea name="description_taches[]" class="form-control rounded-3" rows="2" placeholder="Décrivez la tâche..." required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Observation</label>
                        <textarea name="observation[]" class="form-control rounded-3" rows="2" placeholder="Observations..."></textarea>
                    </div>
                    <div class="col-md-1 text-center mt-4">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-line-btn mt-2" title="Supprimer cette ligne">❌</button>
                    </div>
                </div>
            `;
            container.appendChild(lineDiv);
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-line-btn')) {
            const lines = document.querySelectorAll('.dynamic-line');
            if (lines.length > 1) {
                e.target.closest('.dynamic-line').remove();
            } else {
                alert("Vous devez conserver au moins une ligne.");
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>