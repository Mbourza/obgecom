<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>CMI Payment Debug - Failed Transaction</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #c0392b; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
        .section { margin-top: 30px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Transaction échouée - Debug CMI</h1>

    <?php
    // Store key
    $storeKey = "TEST1234";

    // Collect POST data
    $postParams = array_keys($_POST);
    natcasesort($postParams);

    // Build hash string
    $hashString = "";
    foreach ($postParams as $param) {
        $paramValue = trim(html_entity_decode($_POST[$param], ENT_QUOTES, 'UTF-8'));
        $escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));
        $lowerParam = strtolower($param);
        if ($lowerParam !== "hash" && $lowerParam !== "encoding") {
            $hashString .= $escapedParamValue . "|";
        }
    }

    $escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $storeKey));
    $hashString .= $escapedStoreKey;

    $calculatedHash = base64_encode(pack('H*', hash('sha512', $hashString)));
    $retrievedHash = isset($_POST["HASH"]) ? $_POST["HASH"] : '';

    $hashStatus = ($retrievedHash === $calculatedHash) ? "<span class='success'>HASH OK</span>" : "<span class='error'>HASH FAILED</span>";

    echo "<p><strong>Status de la sécurité (HASH):</strong> $hashStatus</p>";

    if ($retrievedHash !== $calculatedHash) {
        echo "<p class='error'><strong>Erreur:</strong> La signature digitale (HASH) ne correspond pas. Vérifiez le StoreKey et l’ordre des paramètres.</p>";
    }

    // Show transaction details
    echo "<div class='section'><h2>Détails du POST envoyé par CMI</h2>";
    echo "<table><tr><th>Paramètre</th><th>Valeur</th></tr>";
    foreach ($_POST as $key => $value) {
        echo "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    echo "</table></div>";

    // Extra checks
    $requiredFields = ['oid', 'amount', 'ProcReturnCode'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        echo "<div class='section'><h2>Champs manquants</h2>";
        echo "<p class='error'>Les champs suivants sont manquants ou vides: " . implode(", ", $missingFields) . "</p>";
        echo "</div>";
    }

    // Show ProcReturnCode issue
    if (isset($_POST['ProcReturnCode']) && $_POST['ProcReturnCode'] !== "00") {
        echo "<div class='section'><h2>Code ProcReturnCode</h2>";
        echo "<p class='error'>Le ProcReturnCode retourné est: " . htmlspecialchars($_POST['ProcReturnCode']) . " (Transaction échouée)</p>";
        echo "</div>";
    }

    ?>

    <div class="section">
        <h2>Conseils de débogage</h2>
        <ul>
            <li>Vérifiez que le <strong>StoreKey</strong> utilisé correspond à celui du compte CMI.</li>
            <li>Assurez-vous que le montant <strong>amount</strong> est exactement le même que celui enregistré dans votre base de données.</li>
            <li>Vérifiez que tous les paramètres requis sont présents.</li>
            <li>Testez la page sur un serveur public HTTPS pour que CMI puisse appeler le callback.</li>
        </ul>
    </div>
</div>
</body>
</html>
