<?php

require 'vendor/autoload.php';

use Azure\Storage\Blob\BlobRestProxy;
use Azure\Storage\Blob\BlobClient;
use Azure\Storage\Blob\Models\BlobSasBuilder;
use Azure\Storage\Blob\Models\BlobSasPermissions;
use Azure\Storage\Blob\Models\ListBlobsOptions;

use Azure\Core\Credentials\SharedKeyCredential;

use MicrosoftAzure\Storage\Common\ServicesBuilder;
use MicrosoftAzure\Storage\Common\Internal\Resources;

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";

if (!$connectionString) {
    die("La variable AZURE_STORAGE_CONNECTION_STRING no está configurada.");
}

// Obtener credenciales
preg_match("/AccountName=([^;]+)/", $connectionString, $matchName);
preg_match("/AccountKey=([^;]+)/", $connectionString, $matchKey);
$accountName = $matchName[1] ?? null;
$accountKey = $matchKey[1] ?? null;

if (!$accountName || !$accountKey) {
    die("No se pudo extraer AccountName o AccountKey de la cadena de conexión.");
}

$credential = new SharedKeyCredential($accountName, $accountKey);
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
            $blobClient->uploadBlob($containerName, $blobName, $content);
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
            $sas = new BlobSasBuilder();
            $sas->setContainerName($containerName);
            $sas->setBlobName($blob->getName());
            $sas->setPermissions(BlobSasPermissions::parse('r'));
            $sas->setExpiry((new \DateTime())->add(new \DateInterval('PT1H'))); // 1 hora

            $sasToken = $sas->generateSasQueryParameters($credential)->toString();
            $url = $blobClient->getBlobUrl($containerName, $blob->getName()) . '?' . $sasToken;
        ?>
            <li>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                    <?= htmlspecialchars($blob->getName()) ?>
                </a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar <?= htmlspecialchars($blob->getName()) ?>?')">
                    <input type="hidden" name="delete_blob" value="<?= htmlspecialchars($blob->getName()) ?>">
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
