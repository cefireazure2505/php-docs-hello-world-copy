<?php
require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";  // Cambia esto por el nombre de tu contenedor

$blobClient = BlobRestProxy::createBlobService($connectionString);

// Eliminar archivo si se solicita
if (isset($_GET['delete'])) {
    $blobToDelete = $_GET['delete'];
    try {
        $blobClient->deleteBlob($containerName, $blobToDelete);
        echo "<p style='color:green;'>Archivo $blobToDelete eliminado correctamente.</p>";
    } catch (ServiceException $e) {
        echo "<p style='color:red;'>Error al eliminar: " . $e->getMessage() . "</p>";
    }
}

// Subida de archivo ZIP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["zipfile"])) {
    $uploadedFile = $_FILES["zipfile"];
    if ($uploadedFile["type"] !== "application/zip") {
        echo "<p style='color:red;'>Solo se permiten archivos ZIP.</p>";
    } else {
        $blobName = basename($uploadedFile["name"]);
        $content = fopen($uploadedFile["tmp_name"], "r");

        try {
            $blobClient->createBlockBlob($containerName, $blobName, $content);
            echo "<p style='color:green;'>Archivo $blobName subido correctamente.</p>";
        } catch (ServiceException $e) {
            echo "<p style='color:red;'>Error al subir: " . $e->getMessage() . "</p>";
        }
    }
}

// Listar archivos
try {
    $listOptions = new ListBlobsOptions();
    $blobList = $blobClient->listBlobs($containerName, $listOptions);
    $blobs = $blobList->getBlobs();
} catch (ServiceException $e) {
    die("Error al listar archivos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestor de archivos ZIP en Azure Blob</title>
</head>
<body>
    <h1>Archivos ZIP en el contenedor '<?= htmlspecialchars($containerName) ?>'</h1>
    <ul>
        <?php foreach ($blobs as $blob): ?>
            <li>
                <a href="<?= htmlspecialchars($blob->getUrl()) ?>" target="_blank">
                    <?= htmlspecialchars($blob->getName()) ?>
                </a>
                [<a href="?delete=<?= urlencode($blob->getName()) ?>" onclick="return confirm('¿Eliminar este archivo?')">Eliminar</a>]
            </li>
        <?php endforeach; ?>
    </ul>

    <h2>Subir nuevo archivo ZIP</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" accept=".zip" required>
        <button type="submit">Subir</button>
    </form>
</body>
</html>
