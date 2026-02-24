<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ImageProcessorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

final class ImageProcessorServiceTest extends TestCase
{
    public function testProcessProductPhotoStoresJpegAndReturnsPublicPath(): void
    {
        if (!function_exists('imagecreatefromstring')) {
            self::markTestSkipped('Extension GD non disponible.');
        }

        $projectDir = sys_get_temp_dir().'/caramel-image-test-'.bin2hex(random_bytes(6));
        mkdir($projectDir, 0777, true);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $service = new ImageProcessorService($kernel);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        self::assertNotFalse($tmp);
        $img = imagecreatetruecolor(2, 2);
        self::assertNotFalse($img);
        imagepng($img, $tmp);
        imagedestroy($img);
        $uploaded = new UploadedFile($tmp, 'product.png', 'image/png', null, true);

        $publicPath = $service->processProductPhoto($uploaded);

        self::assertStringStartsWith('uploads/produits/', $publicPath);
        self::assertFileExists($projectDir.'/public/'.$publicPath);
    }
}
