<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class UploadController extends AbstractController
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    #[Route('/uploads/{bucket}/{filename}', name: 'uploaded_asset', methods: ['GET'], requirements: [
        'bucket' => 'produits|inventaire',
        'filename' => '[A-Za-z0-9._-]+',
    ])]
    public function show(string $bucket, string $filename): BinaryFileResponse
    {
        $baseDir = $this->kernel->getProjectDir().'/public/uploads/'.$bucket;
        $path = $baseDir.'/'.$filename;
        $realPath = realpath($path);
        $realBase = realpath($baseDir);

        if ($realPath === false || $realBase === false || !str_starts_with($realPath, $realBase.'/') || !is_file($realPath)) {
            throw new NotFoundHttpException('Fichier introuvable.');
        }

        return new BinaryFileResponse($realPath);
    }
}

