<?php

require __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
// NEW: Add these for SAS
use MicrosoftAzure\Storage\Blob\Models\SharedAccessBlobPermissions;
use MicrosoftAzure\Storage\Blob\BlobSharedAccessPolicy;
use MicrosoftAzure\Storage\Common\SharedAccessSignatureHelper; // This might be the correct public class now


// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";

if (empty($connectionString)) {
    die("<p style='color:red;'>Error: La variable de entorno AZURE_STORAGE_CONNECTION_STRING no está configurada.</p>");
}

try {
    $blobClient = BlobRestProxy::createBlobService($connectionString);
} catch (\Exception $e) {
    die("<p style='color:red;'>Error al conectar con Azure Blob Storage: " . $e->getMessage() . "</p>");
}

// ... (Your existing delete and upload logic) ...

// Listar archivos
try {
    $listOptions = new ListBlobsOptions();
    $blobList = $blobClient->listBlobs($containerName, $listOptions);
    $blobs = $blobList->getBlobs();
} catch (ServiceException $e) {
    die("<p style='color:red;'>Error al listar archivos: " . $e->getMessage() . "</p>");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestor de archivos ZIP en Azure Blob</title>
    <style>
        /* ... (Your existing CSS) ... */
    </style>
</head>
<body>
    <h1>Archivos ZIP en el contenedor '<?= htmlspecialchars($containerName) ?>'</h1>

    <?php
        // Extract AccountName and AccountKey from connection string
        // This is necessary for manual SAS generation, but using $blobClient->generateBlobSharedAccessSignature is better.
        // Let's rely on the BlobClient for SAS generation if possible.
        $accountName = null;
        if (preg_match("/AccountName=([^;]+)/", $connectionString, $matches1)) {
             $accountName = $matches1[1];
        }

        // Check if account name is available (needed for SAS)
        if (!$accountName) {
            echo "<p style='color:red;'>No se pudo extraer el AccountName de la cadena de conexión. No se generarán URLs SAS.</p>";
        }
    ?>

    <ul>
        <?php if (empty($blobs)): ?>
            <li>No hay archivos ZIP en este contenedor.</li>
        <?php else: ?>
            <?php foreach ($blobs as $blob):
                $downloadUrl = $blob->getUrl(); // Default to direct URL

                if ($accountName) {
                    try {
                        // Define the policy for the SAS token
                        $permission = SharedAccessBlobPermissions::READ; // Only allow reading
                        // Set expiry to 1 hour from now. Adjust as needed.
                        $expiryTime = (new \DateTime())->add(new \DateInterval('PT1H'));

                        $sasPolicy = new BlobSharedAccessPolicy();
                        $sasPolicy->setPermissions($permission);
                        $sasPolicy->setExpiryTime($expiryTime);

                        // Generate the SAS token using the BlobClient
                        $sasToken = $blobClient->generateBlobSharedAccessSignature(
                            $containerName,
                            $blob->getName(),
                            $sasPolicy
                        );

                        // Construct the full SAS URL
                        $downloadUrl = $blob->getUrl() . '?' . $sasToken;

                    } catch (\Exception $e) {
                        echo "<p style='color:red;'>Error generando SAS para '{$blob->getName()}': " . $e->getMessage() . "</p>";
                        // Fallback to direct URL if SAS generation fails, but it might still not work
                    }
                }
            ?>
                <li>
                    <a href="<?= htmlspecialchars($downloadUrl) ?>" target="_blank">
                        <?= htmlspecialchars($blob->getName()) ?>
                    </a>
                    <form method="POST" style="display:inline; margin-left: 10px;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar '<?= htmlspecialchars($blob->getName()) ?>'?')">
                        <input type="hidden" name="delete_blob" value="<?= htmlspecialchars($blob->getName()) ?>">
                        <button type="submit" style="color: red; border: none; background: none; cursor: pointer; text-decoration: underline;">Eliminar</button>
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
