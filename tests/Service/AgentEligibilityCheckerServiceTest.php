<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Utilisateur;
use App\Repository\AgentEligibleRepository;
use App\Repository\TeletravailleurListeRepository;
use App\Service\AgentEligibilityCheckerService;
use PHPUnit\Framework\TestCase;

final class AgentEligibilityCheckerServiceTest extends TestCase
{
    public function testTeletravailleurBloqueSiAbsentDeLaListeTeletravailleurs(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('liste teletravailleurs autorisee');

        $teletravailleurs = $this->createMock(TeletravailleurListeRepository::class);
        $teletravailleurs->method('existsByNumeroAgent')->with('12345')->willReturn(false);

        $agentsEligibles = $this->createMock(AgentEligibleRepository::class);
        $agentsEligibles->expects(self::never())->method('existsByNumeroAgent');

        $user = (new Utilisateur())
            ->setLogin('teletravailleur@test.local')
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT', 'ROLE_TELETRAVAILLEUR']);

        (new AgentEligibilityCheckerService($teletravailleurs, $agentsEligibles))
            ->assertAllowed($user, '12345');
    }

    public function testAgentBloqueSiAbsentDeLaListeAgentsEligibles(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('liste des agents autorises');

        $teletravailleurs = $this->createMock(TeletravailleurListeRepository::class);
        $teletravailleurs->expects(self::never())->method('existsByNumeroAgent');

        $agentsEligibles = $this->createMock(AgentEligibleRepository::class);
        $agentsEligibles->method('existsByNumeroAgent')->with('54321')->willReturn(false);

        $user = (new Utilisateur())
            ->setLogin('agent@test.local')
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);

        (new AgentEligibilityCheckerService($teletravailleurs, $agentsEligibles))
            ->assertAllowed($user, '54321');
    }
}
