<?php
// /directeur/fiche-de-preparation-detaillee/fiche-modele-das.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();

$msg = '';
$error = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') $msg = "Fiche DAS supprimée avec succès.";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'not_found') $error = "La fiche DAS demandée est introuvable.";
}

$action = $_GET['action'] ?? '';
$q = trim((string)($_GET['q'] ?? ''));

// Suppression d'une fiche DAS
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            // Récupérer la pièce jointe pour la supprimer du serveur si elle existe
            $stmtFile = $pdo->prepare("SELECT piece_jointe FROM fiches_das WHERE id = ?");
            $stmtFile->execute([$id]);
            $fileToDelete = $stmtFile->fetchColumn();

            if ($fileToDelete) {
                $path = dirname(__DIR__, 2) . '/uploads/attachement_fiches/' . $fileToDelete;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            // Supprimer les liaisons de classes (géré par CASCADE mais sécurisé ici)
            $stmtLink = $pdo->prepare("DELETE FROM fiche_das_classes WHERE fiche_id = ?");
            $stmtLink->execute([$id]);

            // Supprimer la fiche
            $stmt = $pdo->prepare("DELETE FROM fiches_das WHERE id = ?");
            $stmt->execute([$id]);

            header("Location: fiche-modele-das.php?msg=deleted");
            exit();
        } catch (Throwable $e) {
            $error = "Erreur lors de la suppression de la fiche DAS.";
        }
    }
}

try {
    $sql = "
        SELECT f.*, 
               GROUP_CONCAT(CONCAT(c.description, ' - ', cy.description) SEPARATOR ', ') AS classes_str
        FROM fiches_das f
        LEFT JOIN fiche_das_classes fc ON fc.fiche_id = f.id
        LEFT JOIN classe c ON c.id = fc.classe_id
        LEFT JOIN cycle cy ON cy.id = c.cycle
        WHERE 1=1
    ";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (f.fiche_num LIKE ? OR f.titre LIKE ? OR f.discipline LIKE ? OR f.enseignant LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    $sql .= " GROUP BY f.id ORDER BY f.date_tache DESC, f.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $fiches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $fiches = [];
    $error = "Impossible de charger les fiches DAS : " . $e->getMessage();
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- En-tête -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1 text-dark">📚 Liste des Fiches de Préparation (DAS)</h3>
                <p class="text-muted mb-0 small">Consultez, recherchez et gérez l'ensemble des fiches pédagogiques DAS</p>
            </div>
            <div>
                <span class="badge bg-primary px-3 py-2 fs-6"><?= count($fiches) ?> Fiche(s) DAS enregistrée(s)</span>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3"><?= e($msg) ?><button type="button"
            class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3"><?= e($error) ?><button type="button"
            class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Barre de recherche -->
    <form method="get" class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
        <div class="row g-2 align-items-center">
            <div class="col-md-10">
                <input type="text" name="q" value="<?= e($q) ?>" class="form-control rounded-3"
                    placeholder="Rechercher par N° de fiche, Titre, Discipline ou Enseignant(e)...">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-50 rounded-3">Filtrer</button>
                <a href="fiche-modele-das.php" class="btn btn-outline-secondary w-50 rounded-3">Reset</a>
            </div>
        </div>
    </form>

    <!-- Tableau de la liste -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 ps-4">N° Fiche</th>
                        <th class="py-3">Date & Durée</th>
                        <th class="py-3">Enseignant(e)</th>
                        <th class="py-3">Discipline & Titre</th>
                        <th class="py-3">Classe(s)</th>
                        <th class="py-3 text-center" style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fiches)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted fw-semibold">Aucune fiche DAS trouvée.</td>
                    </tr>
                    <?php else: foreach ($fiches as $f): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-primary">
                            <?= e($f['fiche_num']) ?>
                        </td>
                        <td class="text-nowrap">
                            <div><?= !empty($f['date_tache']) ? date('d/m/Y', strtotime($f['date_tache'])) : '-' ?>
                            </div>
                            <small class="text-muted">⏱️ <?= e($f['duree']) ?: '-' ?></small>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($f['enseignant']) ?: '-' ?></div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($f['discipline']) ?: '-' ?></div>
                            <small class="text-secondary"><?= e($f['titre']) ?: 'Titre non défini' ?></small>
                        </td>
                        <td>
                            <span
                                class="badge bg-light text-dark border"><?= e($f['classes_str']) ?: 'Aucune classe' ?></span>
                        </td>
                        <td class="text-center text-nowrap">
                            <a href="fiche-modele-das-view.php?id=<?= (int)$f['id'] ?>"
                                class="btn btn-sm btn-primary rounded-3 text-white" title="Consulter la fiche en détail">
                                👁️ Voir
                            </a>
                            <a href="fiche-modele-das.php?action=delete&id=<?= (int)$f['id'] ?>"
                                class="btn btn-sm btn-danger rounded-3"
                                onclick="return confirm('Voulez-vous vraiment supprimer cette fiche DAS ?');"
                                title="Supprimer">
                                🗑️ Supprimer
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>