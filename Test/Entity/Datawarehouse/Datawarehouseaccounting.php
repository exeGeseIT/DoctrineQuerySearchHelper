<?php

declare(strict_types=1);

namespace ExeGeseIT\Test\Entity\Datawarehouse;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ExeGeseIT\Test\Repository\Datawarehouse\DatawarehouseaccountingRepository;

#[ORM\Table(name: 'datawarehouseaccounting')]
#[ORM\Index(name: 'I_datawarehouseaccounting_glaccount_analytics', fields: ['glaccount', 'analytic1', 'analytic2'])]
#[ORM\Entity(repositoryClass: DatawarehouseaccountingRepository::class, readOnly: true)]
final class Datawarehouseaccounting
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        #[ORM\Column(name: 'iddatawarehouseaccounting', type: Types::BIGINT)]
        private ?int $id = null,
        #[ORM\ManyToOne(inversedBy: 'accountings')]
        #[ORM\JoinColumn(name: 'datawarehouse_iddatawarehouse', referencedColumnName: 'iddatawarehouse', nullable: false, onDelete: 'CASCADE')]
        private ?Datawarehouse $datawarehouse = null,
        #[ORM\Column(name: 'glaccountDatawarehouseaccounting', length: 255, nullable: true)]
        private ?string $glaccount = null,
        #[ORM\Column(name: 'analytic1Datawarehouseaccounting', length: 255, nullable: true)]
        private ?string $analytic1 = null,
        #[ORM\Column(name: 'analytic2Datawarehouseaccounting', length: 255, nullable: true)]
        private ?string $analytic2 = null,
        #[ORM\Column(name: 'amounthtDatawarehouseaccounting', nullable: true)]
        private ?float $amountht = null,
        #[ORM\Column(name: 'modifieddateDatawarehouseaccounting', type: Types::DATETIME_IMMUTABLE, nullable: false, options: [
            'default' => 'CURRENT_TIMESTAMP',
        ])]
        private ?\DateTimeImmutable $modifieddate = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatawarehouse(): ?Datawarehouse
    {
        return $this->datawarehouse;
    }

    public function getGlaccount(): ?string
    {
        return $this->glaccount;
    }

    public function getAnalytic1(): ?string
    {
        return $this->analytic1;
    }

    public function getAnalytic2(): ?string
    {
        return $this->analytic2;
    }

    public function getAmountht(): ?float
    {
        return $this->amountht;
    }

    public function getModifieddate(): ?\DateTimeImmutable
    {
        return $this->modifieddate;
    }
}
