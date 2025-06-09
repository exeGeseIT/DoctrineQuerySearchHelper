<?php

declare(strict_types=1);

namespace ExeGeseIT\Test\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ExeGeseIT\Test\Repository\ArticlestatusRepository;

#[ORM\Table(name: 'articlestatus')]
#[ORM\Entity(repositoryClass: ArticlestatusRepository::class)]
#[ORM\UniqueConstraint(name: 'U_articlestatus', columns: ['keyArticlestatus'])]
class Articlestatus implements \Stringable
{
    final public const WAITING = 'WAITING';
    final public const MISSING = 'MISSING';
    final public const CHECKED = 'CHECKED';
    final public const NOT_ON_DOCK = 'NOT_ON_DOCK';
    final public const DOCKED = 'DOCKED';
    final public const LOADED = 'LOADED';
    final public const REFUSED = 'REFUSED';
    final public const DELIVERED = 'DELIVERED';
    final public const REMOVAL_MISSING = 'REMOVAL_MISSING';
    final public const RETURNED = 'RETURNED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'idarticlestatus', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'keyArticlestatus', type: Types::STRING, length: 45)]
    private ?string $key = null;

    #[ORM\Column(name: 'nameArticlestatus', type: Types::STRING, length: 255)]
    private ?string $name = null;

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
