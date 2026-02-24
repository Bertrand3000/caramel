<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

class ImageProcessorService
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function processProductPhoto(UploadedFile $file): string
    {
        return $this->saveAs($file, '/public/uploads/produits', 'uploads/produits', 'jpg', true);
    }

    public function processInventoryPhoto(UploadedFile $file): string
    {
        return $this->saveAs($file, '/public/uploads/inventaire', 'uploads/inventaire', 'png', false);
    }

    private function saveAs(UploadedFile $file, string $path, string $publicPrefix, string $extension, bool $jpeg): string
    {
        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            throw new \RuntimeException('Impossible de lire le fichier image.');
        }
        $image = imagecreatefromstring($content);
        if ($image === false) {
            throw new \RuntimeException('Image invalide.');
        }

        $targetDir = $this->kernel->getProjectDir().$path;
        $this->ensureWritableDirectory($targetDir);

        $filename = sprintf('%s.%s', bin2hex(random_bytes(12)), $extension);
        $targetPath = $targetDir.'/'.$filename;
        $ok = $jpeg ? imagejpeg($image, $targetPath, 80) : imagepng($image, $targetPath);
        imagedestroy($image);

        if ($ok !== true) {
            throw new \RuntimeException('Impossible de sauvegarder le fichier image.');
        }

        return $publicPrefix.'/'.$filename;
    }

    private function ensureWritableDirectory(string $targetDir): void
    {
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier d\'upload: %s', $targetDir));
        }

        if (!is_writable($targetDir)) {
            throw new \RuntimeException(sprintf('Le dossier d\'upload n\'est pas inscriptible: %s', $targetDir));
        }
    }
}
