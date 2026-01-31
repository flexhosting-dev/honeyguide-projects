<?php

namespace App\Service;

use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'doc', 'docx', 'txt', 'rtf',
        'xls', 'xlsx', 'csv',
        'zip', 'rar', '7z',
    ];

    private string $uploadDir;

    public function __construct(string $kernelProjectDir)
    {
        $this->uploadDir = $kernelProjectDir . '/var/uploads/attachments';
    }

    public function upload(UploadedFile $file): array
    {
        $this->validate($file);

        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getClientMimeType();
        $fileSize = $file->getSize();
        $storedName = Uuid::uuid4()->toString() . '.' . $extension;

        $year = date('Y');
        $month = date('m');
        $relativePath = $year . '/' . $month;
        $targetDir = $this->uploadDir . '/' . $relativePath;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $file->move($targetDir, $storedName);

        return [
            'originalName' => $originalName,
            'storedName' => $storedName,
            'mimeType' => $mimeType,
            'fileSize' => $fileSize,
            'path' => $relativePath . '/' . $storedName,
        ];
    }

    public function delete(string $path): void
    {
        $fullPath = $this->uploadDir . '/' . $path;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function getAbsolutePath(string $path): string
    {
        return $this->uploadDir . '/' . $path;
    }

    private function validate(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File size exceeds the 10MB limit.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('File type "' . $extension . '" is not allowed.');
        }
    }
}
