<?php
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification admin
requireRole('administrateur');

$user = getCurrentUser();
$etablissementService = app()->make('API\Services\EtablissementService');

$error = '';
$success = '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info'])) {
        if ($etablissementService->updateInfo($_POST)) {
            $success = "Informations de l'établissement mises à jour.";
        } else {
            $error = "Erreur lors de la mise à jour.";
        }
    } elseif (isset($_POST['add_classe'])) {
        if ($etablissementService->addClasse($_POST['niveau'], $_POST['nom'])) {
            $success = "Classe ajoutée avec succès.";
        } else {
            $error = "Erreur lors de l'ajout de la classe.";
        }
    } elseif (isset($_POST['add_matiere'])) {
        if ($etablissementService->addMatiere($_POST['code'], $_POST['nom'], $_POST['couleur'])) {
            $success = "Matière ajoutée avec succès.";
        } else {
            $error = "Erreur lors de l'ajout de la matière.";
        }
    } elseif (isset($_POST['configure_periodes'])) {
        $periodes = [];
        $count = intval($_POST['periode_count']);
        
        for ($i = 0; $i < $count; $i++) {
            $periodes[] = [
                'nom' => $_POST["periode_nom_$i"],
                'date_debut' => $_POST["periode_debut_$i"],
                'date_fin' => $_POST["periode_fin_$i"]
            ];
        }
        
        if ($etablissementService->configurePeriodes($_POST['type_periode'], $periodes)) {
            $success = "Périodes configurées avec succès.";
        } else {
            $error = "Erreur lors de la configuration des périodes.";
        }
    }
}

$data = $etablissementService->getData();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Établissement - PRONOTE</title>
    <link rel="stylesheet" href="../assets/css/pronote-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar (identique aux autres pages admin) -->
        <div class="sidebar">
            <!-- ...navigation... -->
        </div>

        <div class="main-content">
            <div class="top-header">
                <h1>Configuration de l'Établissement</h1>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Onglets de configuration -->
            <div class="tabs">
                <button class="tab-button active" data-tab="info">Informations</button>
                <button class="tab-button" data-tab="classes">Classes</button>
                <button class="tab-button" data-tab="matieres">Matières</button>
                <button class="tab-button" data-tab="periodes">Périodes</button>
            </div>

            <!-- Contenu des onglets -->
            <div class="tab-content active" id="info">
                <form method="post">
                    <div class="form-group">
                        <label>Nom de l'établissement</label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($data['info']['nom'] ?? '') ?>" required>
                    </div>
                    <!-- ...autres champs... -->
                    <button type="submit" name="update_info" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>

            <div class="tab-content" id="classes">
                <form method="post">
                    <div class="form-group">
                        <label>Niveau</label>
                        <select name="niveau" class="form-control" required>
                            <option value="primaire">Primaire</option>
                            <option value="college">Collège</option>
                            <option value="lycee">Lycée</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nom de la classe</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                    <button type="submit" name="add_classe" class="btn btn-primary">Ajouter la classe</button>
                </form>

                <h3>Classes existantes</h3>
                <?php foreach ($data['classes'] as $niveau => $classes): ?>
                    <h4><?= ucfirst($niveau) ?></h4>
                    <ul>
                        <?php foreach ($classes as $classe): ?>
                            <li><?= htmlspecialchars($classe) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div>

            <div class="tab-content" id="periodes">
                <form method="post" id="periodesForm">
                    <div class="form-group">
                        <label>Type de période</label>
                        <select name="type_periode" class="form-control" id="typePeriode" required>
                            <option value="trimestre">Trimestres (3)</option>
                            <option value="semestre">Semestres (2)</option>
                        </select>
                    </div>

                    <div id="periodesContainer"></div>
                    
                    <input type="hidden" name="periode_count" id="periodeCount" value="3">
                    <button type="submit" name="configure_periodes" class="btn btn-primary">Enregistrer les périodes</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Gestion des onglets
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                
                document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Gestion des périodes
        document.getElementById('typePeriode').addEventListener('change', function() {
            const count = this.value === 'trimestre' ? 3 : 2;
            document.getElementById('periodeCount').value = count;
            generatePeriodeFields(count, this.value);
        });

        function generatePeriodeFields(count, type) {
            const container = document.getElementById('periodesContainer');
            container.innerHTML = '';
            
            const labels = type === 'trimestre' 
                ? ['1er Trimestre', '2ème Trimestre', '3ème Trimestre']
                : ['1er Semestre', '2ème Semestre'];
            
            for (let i = 0; i < count; i++) {
                container.innerHTML += `
                    <div class="periode-group">
                        <h4>${labels[i]}</h4>
                        <input type="hidden" name="periode_nom_${i}" value="${labels[i]}">
                        <div class="form-group">
                            <label>Date de début</label>
                            <input type="date" name="periode_debut_${i}" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Date de fin</label>
                            <input type="date" name="periode_fin_${i}" class="form-control" required>
                        </div>
                    </div>
                `;
            }
        }

        // Initialiser les champs de périodes
        generatePeriodeFields(3, 'trimestre');
    </script>
</body>
</html>
