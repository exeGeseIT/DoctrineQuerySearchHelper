<?php

declare(strict_types=1);

namespace ExeGeseIT\Test\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ExeGeseIT\Test\Repository\DeliveryformstatusRepository;

#[ORM\Entity(repositoryClass: DeliveryformstatusRepository::class)]
#[ORM\Table(name: 'deliveryformstatus')]
class Deliveryformstatus implements \Stringable
{
    final public const WAITING = 'WAITING';

    // # AFTER PREPARATION STEP
    final public const PARTIAL = 'PARTIAL';
    final public const MISSING = 'MISSING';
    final public const PREPARED = 'PREPARED';

    // # AFTER LOADING STEP
    final public const DOCKED = 'DOCKED';
    final public const LOADED = 'LOADED';
    final public const RECEIPT_SIGNING = 'RECEIPT_SIGNING';

    // # AFTER DELIVERING STEP
    final public const ABSENT = 'ABSENT';
    final public const DELIVERED = 'DELIVERED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'iddeliveryformstatus', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'keyDeliveryformstatus', type: Types::STRING, length: 45)]
    private ?string $key = null;

    #[ORM\Column(name: 'nameDeliveryformstatus', type: Types::STRING, length: 255)]
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
