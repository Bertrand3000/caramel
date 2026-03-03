<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AgentEligibleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentEligibleRepository::class)]
#[ORM\Table(name: 'agent_eligible')]
#[ORM\UniqueConstraint(name: 'uniq_agent_eligible_numero_agent', columns: ['numero_agent'])]
class AgentEligible
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'numero_agent', length: 5, unique: true)]
    private string $numeroAgent;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroAgent(): string
    {
        return $this->numeroAgent;
    }

    public function setNumeroAgent(string $numeroAgent): self
    {
        $this->numeroAgent = $numeroAgent;

        return $this;
    }
}
