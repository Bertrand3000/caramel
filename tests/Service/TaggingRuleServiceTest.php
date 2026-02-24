<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\RegleTagger;
use App\Repository\RegleTaggerRepository;
use App\Service\TaggingRuleService;
use PHPUnit\Framework\TestCase;

final class TaggingRuleServiceTest extends TestCase
{
    public function testResolveTagForLibelleRetourneNullQuandAucuneRegle(): void
    {
        $repository = $this->createMock(RegleTaggerRepository::class);
        $repository->method('findLatestMatchingRule')->with('Table bureau')->willReturn(null);

        $service = new TaggingRuleService($repository);

        self::assertNull($service->resolveTagForLibelle('Table bureau'));
    }

    public function testResolveTagForLibelleRetourneTagDeLaRegleTrouvee(): void
    {
        $regle = (new RegleTagger())
            ->setLibelleContains('caisson')
            ->setTagTeletravailleur(true);

        $repository = $this->createMock(RegleTaggerRepository::class);
        $repository->method('findLatestMatchingRule')->with('Caisson mobile')->willReturn($regle);

        $service = new TaggingRuleService($repository);

        self::assertTrue($service->resolveTagForLibelle('Caisson mobile'));
    }
}
