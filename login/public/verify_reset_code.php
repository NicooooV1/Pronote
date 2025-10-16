<?php
// verify_reset_code.php

// Include necessary files and initialize variables
include('config.php');
session_start();

$error = '';

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the submitted code
    $code = $_POST['code'] ?? '';
    if ($code == ($_SESSION['reset_code'] ?? '')) {
        // Code is valid, redirect to password reset page
        header("Location: change_password.php");
        exit();
    } else {
        // Invalid code, set an error message
        $error = "Code de réinitialisation invalide.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifier le code de réinitialisation</title>
    <!-- Include your CSS and JS files here -->
</head>
<body>
    <div class="container">
        <h2>Vérifiez votre code</h2>
        
        <!-- Display error message if exists -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form action="verify_reset_code.php" method="POST">
            <div class="form-group">
                <label for="code">Entrez le code de réinitialisation :</label>
                <input type="text" name="code" id="code" class="form-control" required>
            </div>
            
            <div class="form-group">
                <button type="submit" name="verify_code" class="btn btn-primary">
                    <i class="fas fa-check"></i> Vérifier le code
                </button>
            </div>
            
            <div class="help-links">
                <a href="reset_password.php">Je n'ai pas reçu de code</a>
            </div>
        </form>
    </div>
</body>
</html>