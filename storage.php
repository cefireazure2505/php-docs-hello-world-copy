<?php

require __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

use MicrosoftAzure\Storage\Blob\Models\SharedAccessBlobPermissions;
use MicrosoftAzure\Storage\Common\Internal\SharedAccessSignatureHelper;
use MicrosoftAzure\Storage\Common\Internal\Resources;

// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos"; // Cambia esto por el nombre de tu contenedor

$blobClient = BlobRestProxy::createBlobService($connectionString);

// Eliminar archivo si se solicita
if (isset($_GET["delete"])) {
    $blobToDelete = $_GET["delete"];
    try {
        $blobClient->deleteBlob($containerName, $blobToDelete);
        echo "<p style='color:green;'>Archivo $blobToDelete eliminado correctamente.</p>";
    } catch (ServiceException $e) {
        echo "<p style='color:red;'>Error al eliminar: " .
            $e->getMessage() .
            "</p>";
    }
}

// Subida de archivo ZIP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["zipfile"])) {
    $uploadedFile = $_FILES["zipfile"];

    $blobName = basename($uploadedFile["name"]);
    $extension = strtolower(pathinfo($blobName, PATHINFO_EXTENSION));

    if ($extension !== "zip") {
        echo "<p style='color:red;'>Solo se permiten archivos con extensión .zip.</p>";
    } else {
        // (Opcional) Verificación adicional por tipo MIME real:
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uploadedFile["tmp_name"]);
        finfo_close($finfo);

        if (
            $mimeType !== "application/zip" &&
            $mimeType !== "application/x-zip-compressed"
        ) {
            echo "<p style='color:red;'>El archivo no parece ser un ZIP válido (MIME: $mimeType).</p>";
        } else {
            $blobName = basename($uploadedFile["name"]);
            $content = fopen($uploadedFile["tmp_name"], "r");

            try {
                $blobClient->createBlockBlob(
                    $containerName,
                    $blobName,
                    $content
                );
                echo "<p style='color:green;'>Archivo $blobName subido correctamente.</p>";
            } catch (ServiceException $e) {
                echo "<p style='color:red;'>Error al subir: " .
                    $e->getMessage() .
                    "</p>";
            }
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
    <h1>Archivos ZIP en el contenedor '<?= htmlspecialchars(
        $containerName
    ) ?>'</h1>
    <ul>
        <?php
            // Extraer AccountName y AccountKey
            $accountName = null;
            $accountKey = null;

            if (preg_match("/AccountName=([^;]+)/", $connectionString, $matches1)) {
                $accountName = $matches1[1];
            }

            if (preg_match("/AccountKey=([^;]+)/", $connectionString, $matches2)) {
                $accountKey = $matches2[1];
            }

            if (!$accountName || !$accountKey) {
                echo "<p style='color:red;'>No se pudo extraer la clave de acceso de la cadena de conexión.</p>";
            } else {
                try {
                    $sasHelper = new SharedAccessSignatureHelper($accountName, $accountKey);
                } catch (Exception $e) {
                    echo "<p style='color:red;'>Error creando SAS Helper: " . $e->getMessage() . "</p>";
                }
            }
        ?>
        <?php foreach ($blobs as $blob): ?>
            <li>
                <a href="<?= htmlspecialchars(
                    $blob->getUrl()
                ) ?>" target="_blank">
                    <?= htmlspecialchars($blob->getName()) ?>
                </a>
                [<a href="?delete=<?= urlencode(
                    $blob->getName()
                ) ?>" onclick="return confirm('¿Eliminar este archivo?')">Eliminar</a>]
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
