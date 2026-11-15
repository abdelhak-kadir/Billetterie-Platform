<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class EventImageStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/uploads/events/%kernel.environment%')] private readonly string $directory) {}

    public function store(UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw new FileException('Format de visuel non pris en charge.'),
        };
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new FileException('Enregistrement du visuel impossible.');
        }
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->directory, $filename);

        return $filename;
    }

    public function path(string $filename): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $filename)) {
            throw new \InvalidArgumentException('Nom de visuel invalide.');
        }
        return $this->directory.'/'.$filename;
    }

    public function remove(?string $filename): void
    {
        if ($filename !== null) { @unlink($this->path($filename)); }
    }
}
