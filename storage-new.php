<?php

require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";

if (!$connectionString) {
    die("La variable AZURE_STORAGE_CONNECTION_STRING no está configurada.");
}

$blobClient = BlobRestProxy::createBlobService($connectionString);

// Eliminar archivo si se envió solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_blob'])) {
    try {
        $blobClient->deleteBlob($containerName, $_POST['delete_blob']);
        echo "<p style='color:green;'>Archivo eliminado: {$_POST['delete_blob']}</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error al eliminar: {$e->getMessage()}</p>";
    }
}

// Subir archivo nuevo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zipfile'])) {
    $file = $_FILES['zipfile'];
    if ($file['error'] === UPLOAD_ERR_OK && mime_content_type($file['tmp_name']) === 'application/zip') {
        $blobName = basename($file['name']);
        try {
            $content = fopen($file['tmp_name'], 'r');
            $blobClient->createBlockBlob($containerName, $blobName, $content);
            echo "<p style='color:green;'>Archivo subido: {$blobName}</p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>Error al subir: {$e->getMessage()}</p>";
        }
    } else {
        echo "<p style='color:red;'>Solo se permiten archivos .zip válidos.</p>";
    }
}

// Listar blobs
try {
    $blobList = $blobClient->listBlobs($containerName, new ListBlobsOptions());
    $blobs = $blobList->getBlobs();
} catch (Exception $e) {
    die("Error al listar blobs: " . $e->getMessage());
}

function generateBlobSasToken($accountName, $accountKey, $containerName, $blobName, $expiryMinutes = 60) {
    $permissions = "r";
    $resource = "b";
    $version = "2020-02-10";
    $expiry = gmdate('Y-m-d\TH:i:s\Z', time() + ($expiryMinutes * 60));
    $canonicalizedResource = "/blob/{$accountName}/{$containerName}/{$blobName}";

    $stringToSign = implode("\n", [
        $permissions,        // signed permissions
        "",                  // signed start time
        $expiry,             // signed expiry time
        $canonicalizedResource,
        "",                  // signed identifier
        "",                  // signed IP
        "",                  // signed protocol
        $version,            // signed version
        "", "", "", "", "", "" // extra fields for newer versions
    ]);

    $decodedKey = base64_decode($accountKey);
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

    $queryParams = [
        'sv' => $version,
        'sr' => $resource,
        'sig' => $signature,
        'se' => $expiry,
        'sp' => $permissions
    ];

    return http_build_query($queryParams);
}


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor de archivos ZIP en Azure Blob</title>
</head>
<body>
    <h1>Archivos ZIP en '<?= htmlspecialchars($containerName) ?>'</h1>

    <ul>
    <?php if (empty($blobs)): ?>
        <li>No hay archivos ZIP.</li>
    <?php else: ?>
        <?php foreach ($blobs as $blob):
            $blobName = $blob->getName();
            $sasToken = generateBlobSasToken($accountName, $accountKey, $containerName, $blobName);
            $url = $blobClient->getBlobUrl($containerName, $blobName) . '?' . $sasToken;
        ?>
            <li>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                    <?= htmlspecialchars($blobName) ?>
                </a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar <?= htmlspecialchars($blobName) ?>?')">
                    <input type="hidden" name="delete_blob" value="<?= htmlspecialchars($blobName) ?>">
                    <button type="submit" style="color:red;">Eliminar</button>
                </form>
            </li>
        <?php endforeach; ?>

    <?php endif; ?>
    </ul>

    <h2>Subir nuevo archivo ZIP</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" accept=".zip" required>
        <button type="submit">Subir</button>
    </form>
</body>
</html>
