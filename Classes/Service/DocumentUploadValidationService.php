<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

final class DocumentUploadValidationService
{
    public const MAX_UPLOAD_BYTES = 10485760;

    private const ALLOWED_EXTENSIONS = ['txt', 'pdf', 'docx', 'pptx'];
    private const ZIP_MAGIC_HEADERS = ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"];

    /**
     * @return string|null Translation key for the validation error.
     */
    public function validateMetadata(string $originalName, ?int $fileSize): ?string
    {
        $extension = $this->getExtension($originalName);
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return 'error.invalidFileType';
        }

        if ($fileSize === null || $fileSize <= 0) {
            return 'error.invalidUpload';
        }

        if ($fileSize > self::MAX_UPLOAD_BYTES) {
            return 'error.fileTooLarge';
        }

        return null;
    }

    /**
     * @return string|null Translation key for the validation error.
     */
    public function validateFile(string $path, string $originalName, ?int $fileSize): ?string
    {
        $metadataError = $this->validateMetadata($originalName, $fileSize);
        if ($metadataError !== null) {
            return $metadataError;
        }

        if (!is_file($path) || !is_readable($path)) {
            return 'error.invalidUpload';
        }

        $extension = $this->getExtension($originalName);
        if (!$this->hasAllowedMimeType($path, $extension) || !$this->hasExpectedContent($path, $extension)) {
            return 'error.fileContentMismatch';
        }

        return null;
    }

    public function sanitizeOriginalFileName(string $originalName): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalName) ?: '';
        $safeName = trim($safeName, '._-');

        return $safeName !== '' ? $safeName : 'upload.' . ($this->getExtension($originalName) ?: 'bin');
    }

    private function getExtension(string $originalName): string
    {
        return strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    }

    private function hasAllowedMimeType(string $path, string $extension): bool
    {
        if (!function_exists('finfo_open')) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }

        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        if (!is_string($mimeType) || $mimeType === '') {
            return false;
        }

        $allowedMimeTypes = match ($extension) {
            'txt' => ['text/plain'],
            'pdf' => ['application/pdf'],
            'docx' => [
                'application/zip',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'pptx' => [
                'application/zip',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
            default => [],
        };

        return in_array($mimeType, $allowedMimeTypes, true);
    }

    private function hasExpectedContent(string $path, string $extension): bool
    {
        $handle = fopen($path, 'rb');
        if (!is_resource($handle)) {
            return false;
        }

        $prefix = (string)fread($handle, 8);
        rewind($handle);
        $sample = (string)fread($handle, 512);
        fclose($handle);

        return match ($extension) {
            'txt' => !str_contains($sample, "\0"),
            'pdf' => str_starts_with($prefix, '%PDF-'),
            'docx' => $this->hasZipMagic($prefix) && $this->zipContains($path, 'word/document.xml'),
            'pptx' => $this->hasZipMagic($prefix) && $this->zipContains($path, 'ppt/presentation.xml'),
            default => false,
        };
    }

    private function hasZipMagic(string $prefix): bool
    {
        foreach (self::ZIP_MAGIC_HEADERS as $header) {
            if (str_starts_with($prefix, $header)) {
                return true;
            }
        }

        return false;
    }

    private function zipContains(string $path, string $entryName): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            return true;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        $found = $zip->locateName($entryName) !== false;
        $zip->close();

        return $found;
    }
}
