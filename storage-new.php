<?php

require __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

// --- Removed SAS Helper related uses if not generating SAS URLs ---
// use MicrosoftAzure\Storage\Blob\Models\SharedAccessBlobPermissions;
// use MicrosoftAzure\Storage\Common\Internal\SharedAccessSignatureHelper;
// use MicrosoftAzure\Storage\Common\Internal\Resources;
// If you want to use SAS, you'd need:
// use MicrosoftAzure\Storage\Blob\BlobSharedAccessPolicy;


// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos"; // Cambia esto por el nombre de tu contenedor

// --- Mejorar la validación de la cadena de conexión ---
if (empty($connectionString)) {
    die("<p style='color:red;'>Error: La variable de entorno AZURE_STORAGE_CONNECTION_STRING no está configurada. Por favor, asegúrate de que esté definida en tu entorno (por ejemplo, en un archivo .env o en la configuración de App Service).</p>");
}

try {
    $blobClient = BlobRestProxy::createBlobService($connectionString);
} catch (\Exception $e) {
    die("<p style='color:red;'>Error al conectar con Azure Blob Storage: " . $e->getMessage() . "</p>");
}

// --- Manejar la eliminación con POST para mayor seguridad (opcional, pero recomendado) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_blob"])) {
    $blobToDelete = $_POST["delete_blob"];
    try {
        $blobClient->deleteBlob($containerName, $blobToDelete);
        echo "<p style='color:green;'>Archivo '$blobToDelete' eliminado correctamente.</p>";
    } catch (ServiceException $e) {
        echo "<p style='color:red;'>Error al eliminar '$blobToDelete': " .
            $e->getMessage() .
            "</p>";
    }
}

// Subida de archivo ZIP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["zipfile"])) {
    $uploadedFile = $_FILES["zipfile"];

    // Basic upload error check
    if ($uploadedFile["error"] !== UPLOAD_ERR_OK) {
        echo "<p style='color:red;'>Error de subida: " . $uploadedFile["error"] . " - ";
        switch ($uploadedFile["error"]) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                echo "El archivo es demasiado grande.";
                break;
            case UPLOAD_ERR_PARTIAL:
                echo "El archivo fue subido parcialmente.";
                break;
            case UPLOAD_ERR_NO_FILE:
                echo "No se seleccionó ningún archivo.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                echo "Falta una carpeta temporal.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                echo "Fallo al escribir el archivo en el disco.";
                break;
            case UPLOAD_ERR_EXTENSION:
                echo "Una extensión de PHP detuvo la subida del archivo.";
                break;
            default:
                echo "Error desconocido.";
        }
        echo "</p>";
    } else {
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
                // Ensure unique blob names to avoid overwriting existing files
                // $blobName = uniqid() . '-' . $blobName; // uncomment to make blob names unique
                $content = fopen($uploadedFile["tmp_name"], "r");

                try {
                    $blobClient->createBlockBlob(
                        $containerName,
                        $blobName,
                        $content
                    );
                    echo "<p style='color:green;'>Archivo '$blobName' subido correctamente.</p>";
                } catch (ServiceException $e) {
                    echo "<p style='color:red;'>Error al subir '$blobName': " .
                        $e->getMessage() .
                        "</p>";
                } finally {
                    if (is_resource($content)) {
                        fclose($content); // Ensure the file handle is closed
                    }
                }
            }
        }
    }
}

// Listar archivos
try {
    $listOptions = new ListBlobsOptions();
    // Optimizacion: Si tienes muchos blobs, puedes usar $listOptions->setMaxResults(100);
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
        body { font-family: sans-serif; margin: 20px; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .message.green { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.red { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        ul { list-style: none; padding: 0; }
        li { margin-bottom: 8px; }
        a { text-decoration: none; color: #007bff; }
        a:hover { text-decoration: underline; }
        button { padding: 8px 12px; cursor: pointer; }
        input[type="file"] { margin-right: 10px; }
    </style>
</head>
<body>
    <h1>Archivos ZIP en el contenedor '<?= htmlspecialchars($containerName) ?>'</h1>

    <?php
    // --- Removed SAS token generation logic that was causing the error ---
    // If you need SAS URLs, implement the logic correctly as explained above.
    // For now, $blob->getUrl() will give the direct public URL if available.
    // Otherwise, you'll need to configure your container for public access
    // or generate SAS tokens as per Azure SDK documentation.
    ?>

    <ul>
        <?php if (empty($blobs)): ?>
            <li>No hay archivos ZIP en este contenedor.</li>
        <?php else: ?>
            <?php foreach ($blobs as $blob): ?>
                <li>
                    <a href="<?= htmlspecialchars($blob->getUrl()) ?>" target="_blank">
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
