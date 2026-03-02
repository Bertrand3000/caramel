<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UploadControllerTest extends WebTestCase
{
    public function testReturnsUploadedFileWhenItExists(): void
    {
        $targetDir = dirname(__DIR__, 2).'/public/uploads/produits';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        if (!is_writable($targetDir)) {
            self::markTestSkipped('Dossier uploads non inscriptible dans cet environnement de test.');
        }

        $file = $targetDir.'/test-upload-controller.jpg';
        $written = file_put_contents($file, 'fake-jpeg-content');
        if ($written === false) {
            self::markTestSkipped('Impossible d écrire le fichier de test dans uploads.');
        }

        $client = static::createClient();
        $client->request('GET', '/uploads/produits/test-upload-controller.jpg');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertStringEndsWith('/public/uploads/produits/test-upload-controller.jpg', $response->getFile()->getPathname());

        @unlink($file);
    }

    public function testReturnsNotFoundWhenUploadedFileIsMissing(): void
    {
        $client = static::createClient();
        $client->request('GET', '/uploads/produits/missing-file.jpg');

        self::assertResponseStatusCodeSame(404);
    }

    public function testReturnsPublicImageFromImagesDirectory(): void
    {
        $client = static::createClient();
        $client->request('GET', '/images/caramel.jpg');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertStringEndsWith('/public/images/caramel.jpg', $response->getFile()->getPathname());
    }
}
