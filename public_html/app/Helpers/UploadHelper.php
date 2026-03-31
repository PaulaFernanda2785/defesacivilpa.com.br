<?php

class UploadHelper
{
    private const IMAGE_MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function storeImage(
        array $file,
        string $destinationDir,
        string $prefix = 'info_',
        int $maxBytes = 5242880
    ): string {
        self::assertUploadSucceeded($file);
        self::assertFileSize($file, $maxBytes, 'A imagem excede o limite permitido de 5 MB.');

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $imageInfo = @getimagesize($tmpPath);

        if ($imageInfo === false) {
            throw new RuntimeException('Imagem invalida ou corrompida.');
        }

        $mime = $imageInfo['mime'] ?? '';

        if (!isset(self::IMAGE_MIME_TO_EXTENSION[$mime])) {
            throw new RuntimeException('Formato de imagem invalido. Envie JPG, PNG ou WEBP.');
        }

        self::ensureDirectory($destinationDir);

        $fileName = $prefix . time() . '_' . bin2hex(random_bytes(8)) . '.' .
            self::IMAGE_MIME_TO_EXTENSION[$mime];

        $destination = rtrim($destinationDir, '\\/') . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Falha ao salvar a imagem enviada.');
        }

        return $fileName;
    }

    public static function storeKml(
        array $file,
        string $destinationDir,
        string $prefix = 'alerta_',
        int $maxBytes = 10485760
    ): string {
        self::assertUploadSucceeded($file);
        self::assertFileSize($file, $maxBytes, 'O arquivo KML excede o limite permitido de 10 MB.');

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $contents = @file_get_contents($tmpPath);

        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException('Nao foi possivel validar o arquivo KML enviado.');
        }

        self::assertKmlContents($contents);

        self::ensureDirectory($destinationDir);

        $fileName = $prefix . time() . '_' . bin2hex(random_bytes(8)) . '.kml';
        $destination = rtrim($destinationDir, '\\/') . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Falha ao salvar o arquivo KML.');
        }

        return $fileName;
    }

    private static function assertKmlContents(string $contents): void
    {
        $document = new DOMDocument();
        $previousErrorsState = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorsState);

        if (!$loaded || !$document->documentElement) {
            throw new RuntimeException('O arquivo enviado nao contem um KML valido.');
        }

        $rootName = strtolower((string) ($document->documentElement->localName ?: $document->documentElement->nodeName));

        if ($rootName !== 'kml') {
            throw new RuntimeException('O arquivo enviado nao contem um KML valido.');
        }

        $doctype = $document->doctype;

        if (
            $doctype !== null
            && (
                (string) $doctype->systemId !== ''
                || (string) $doctype->publicId !== ''
                || ($doctype->entities !== null && $doctype->entities->length > 0)
            )
        ) {
            throw new RuntimeException('O arquivo KML enviado usa declaracoes XML nao permitidas.');
        }
    }

    public static function decodeBase64Png(string $dataUri, int $maxBytes = 5242880): string
    {
        if (!preg_match('#^data:image/png;base64,#i', $dataUri)) {
            throw new RuntimeException('Formato de imagem do mapa invalido.');
        }

        $base64 = preg_replace('#^data:image/png;base64,#i', '', $dataUri);
        $binary = base64_decode((string) $base64, true);

        if ($binary === false) {
            throw new RuntimeException('Falha ao decodificar a imagem do mapa.');
        }

        if (strlen($binary) > $maxBytes) {
            throw new RuntimeException('A imagem do mapa excede o limite permitido.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary) ?: '';

        if ($mime !== 'image/png') {
            throw new RuntimeException('A imagem do mapa precisa estar em PNG.');
        }

        return $binary;
    }

    public static function ensureDirectory(string $destinationDir): void
    {
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
            throw new RuntimeException('Nao foi possivel preparar o diretorio de armazenamento.');
        }
    }

    private static function assertUploadSucceeded(array $file): void
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errorCode === UPLOAD_ERR_OK) {
            return;
        }

        $messages = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o limite configurado no servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite permitido pelo formulario.',
            UPLOAD_ERR_PARTIAL => 'O upload foi concluido apenas parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretorio temporario indisponivel para upload.',
            UPLOAD_ERR_CANT_WRITE => 'Nao foi possivel gravar o arquivo enviado.',
            UPLOAD_ERR_EXTENSION => 'O upload foi interrompido por uma extensao do PHP.',
        ];

        throw new RuntimeException($messages[$errorCode] ?? 'Falha inesperada no upload do arquivo.');
    }

    private static function assertFileSize(array $file, int $maxBytes, string $message): void
    {
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException($message);
        }
    }
}
