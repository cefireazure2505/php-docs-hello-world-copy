<?php
require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

use MicrosoftAzure\Storage\Common\Internal\Resources;
use MicrosoftAzure\Storage\Blob\Models\SharedAccessBlobPermissions;
use MicrosoftAzure\Storage\Blob\Models\SharedAccessSignatureHelper;

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

    $blobName = basename($uploadedFile["name"]);
    $extension = strtolower(pathinfo($blobName, PATHINFO_EXTENSION));

    if ($extension !== 'zip') {
        echo "<p style='color:red;'>Solo se permiten archivos con extensión .zip.</p>";
      
    } else{

        // (Opcional) Verificación adicional por tipo MIME real:
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uploadedFile["tmp_name"]);
        finfo_close($finfo);

        if ($mimeType !== 'application/zip' && $mimeType !== 'application/x-zip-compressed') {
            echo "<p style='color:red;'>El archivo no parece ser un ZIP válido (MIME: $mimeType).</p>";
         
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
<?php
try {
    // Obtener AccountName y AccountKey de la cadena de conexión
    if (!preg_match("/AccountName=([^;]+)/", $connectionString, $accountNameMatch) ||
        !preg_match("/AccountKey=([^;]+)/", $connectionString, $accountKeyMatch)) {
        throw new Exception("No se pudo extraer AccountName o AccountKey de la cadena de conexión.");
    }

    $accountName = $accountNameMatch[1];
    $accountKey = $accountKeyMatch[1];

    $sasHelper = new SharedAccessSignatureHelper($accountName, $accountKey);

    // Listado de blobs con enlace SAS
    foreach ($blobs as $blob):
        $blobName = $blob->getName();

        // Crear SAS con permiso de lectura durante 15 minutos
        $expiry = (new DateTime())->modify('+15 minutes');
        $sasToken = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
            Resources::RESOURCE_TYPE_BLOB,
            "$containerName/$blobName",
            SharedAccessBlobPermissions::READ,
            $expiry
        );

        $secureUrl = $blob->getUrl() . '?' . $sasToken;
?>
    <li>
        <a href="<?= htmlspecialchars($secureUrl) ?>" target="_blank">
            <?= htmlspecialchars($blobName) ?>
        </a>
        [<a href="?delete=<?= urlencode($blobName) ?>" onclick="return confirm('¿Eliminar este archivo?')">Eliminar</a>]
    </li>
<?php
    endforeach;
} catch (Exception $e) {
    echo "<li style='color:red;'>Error al generar enlaces seguros: " . htmlspecialchars($e->getMessage()) . "</li>";
}
?>
</ul>
    <h2>Subir nuevo archivo ZIP</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" accept=".zip" required>
        <button type="submit">Subir</button>
    </form>
</body>
</html>
