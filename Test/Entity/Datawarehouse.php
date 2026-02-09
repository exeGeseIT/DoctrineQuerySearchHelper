<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Test\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ExeGeseIT\DoctrineQuerySearchHelper\Test\Repository\DatawarehouseRepository;

#[ORM\Table(name: 'datawarehouse')]
#[ORM\UniqueConstraint(name: 'U_datawarehouse', fields: ['organizationkey', 'collid', 'docid'])]
#[ORM\Entity(repositoryClass: DatawarehouseRepository::class, readOnly: true)]
final class Datawarehouse
{
    public const CREDIT_LINE = 'C';
    public const DEBIT_LINE = 'D';

    /**
     * @var Collection<int, Datawarehouseaccounting>
     */
    #[ORM\OneToMany(targetEntity: Datawarehouseaccounting::class, mappedBy: 'datawarehouse', orphanRemoval: true)]
    private Collection $accountings;

    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        #[ORM\Column(name: 'iddatawarehouse', type: Types::BIGINT)]
        private ?string $id = null,

        #[ORM\Column(name: 'organization_keyorganization', length: 255)]
        private ?string $organizationkey = null,

        #[ORM\Column(name: 'zeendoc_collid', length: 255)]
        private ?string $collid = null,
        #[ORM\Column(name: 'zeendoc_resid', type: Types::BIGINT)]
        private ?string $docid = null,
        #[ORM\Column(name: 'zeendoc_action', length: 255, nullable: true)]
        private ?string $action = null,
        #[ORM\Column(name: 'doctypeDatawarehouse', length: 255)]
        private ?string $type = null,
        #[ORM\Column(name: 'docstatusDatawarehouse', type: Types::SMALLINT, nullable: true)]
        private ?int $docstatus = null,
        #[ORM\Column(name: 'creditdebitDatawarehouse', length: 1, nullable: true)]
        private ?string $creditdebit = null,
        #[ORM\Column(name: 'buDatawarehouse', length: 255, nullable: true)]
        private ?string $businessunit = null,
        #[ORM\Column(name: 'thirdpartyDatawarehouse', length: 255)]
        private ?string $thirdparty = null,
        #[ORM\Column(name: 'ownerDatawarehouse', length: 255)]
        private ?string $owner = null,
        #[ORM\Column(name: 'docdateDatawarehouse', type: Types::DATE_MUTABLE)]
        private ?\DateTimeInterface $docdate = null,
        #[ORM\Column(name: 'refDatawarehouse', length: 255)]
        private ?string $reference = null,
        #[ORM\Column(name: 'externalrefDatawarehouse', length: 255, nullable: true)]
        private ?string $externalref = null,
        #[ORM\Column(name: 'porefDatawarehouse', length: 255, nullable: true)]
        private ?string $poref = null,
        #[ORM\Column(name: 'currencyDatawarehouse', length: 255)]
        private ?string $currency = null,
        #[ORM\Column(name: 'amounthtDatawarehouse', nullable: true)]
        private ?float $amountht = null,
        #[ORM\Column(name: 'amounttaxDatawarehouse', nullable: true)]
        private ?float $amounttax = null,
        #[ORM\Column(name: 'amountttcDatawarehouse', nullable: true)]
        private ?float $amountttc = null,
        #[ORM\Column(name: 'supervisorDatawarehouse', length: 255, nullable: true)]
        private ?string $supervisor = null,
        #[ORM\Column(name: 'extra1Datawarehouse', length: 255, nullable: true)]
        private ?string $extra1 = null,
        #[ORM\Column(name: 'extra2Datawarehouse', length: 255, nullable: true)]
        private ?string $extra2 = null,
        #[ORM\Column(name: 'extra3Datawarehouse', length: 255, nullable: true)]
        private ?string $extra3 = null,
        #[ORM\Column(name: 'extra4Datawarehouse', length: 255, nullable: true)]
        private ?string $extra4 = null,
        #[ORM\Column(name: 'extra5Datawarehouse', length: 255, nullable: true)]
        private ?string $extra5 = null,
        #[ORM\Column(name: 'extra6Datawarehouse', length: 255, nullable: true)]
        private ?string $extra6 = null,
        #[ORM\Column(name: 'extra7Datawarehouse', length: 255, nullable: true)]
        private ?string $extra7 = null,
        #[ORM\Column(name: 'extra8Datawarehouse', length: 255, nullable: true)]
        private ?string $extra8 = null,
        #[ORM\Column(name: 'extra9Datawarehouse', length: 255, nullable: true)]
        private ?string $extra9 = null,
        #[ORM\Column(name: 'extra10Datawarehouse', length: 255, nullable: true)]
        private ?string $extra10 = null,
        #[ORM\Column(name: 'zeendoc_nstatus', )]
        private ?int $archivestatus = null,
        #[ORM\Column(name: 'docarchiveddateDatawarehouse', type: Types::DATETIME_IMMUTABLE)]
        private ?\DateTimeImmutable $createdate = null,
        #[ORM\Column(name: 'modifieddateDatawarehouse', type: Types::DATETIME_IMMUTABLE, nullable: false, options: [
            'default' => 'CURRENT_TIMESTAMP',
        ])]
        private ?\DateTimeImmutable $modifieddate = null,
    ) {
        $this->accountings = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOrganizationkey(): ?string
    {
        return $this->organizationkey;
    }

    public function getCollid(): ?string
    {
        return $this->collid;
    }

    public function getDocid(): ?string
    {
        return $this->docid;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getDocstatus(): ?int
    {
        return $this->docstatus;
    }

    public function getCreditdebit(): ?string
    {
        return $this->creditdebit;
    }

    public function getBusinessunit(): ?string
    {
        return $this->businessunit;
    }

    public function getThirdparty(): ?string
    {
        return $this->thirdparty;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getExternalref(): ?string
    {
        return $this->externalref;
    }

    public function getPoref(): ?string
    {
        return $this->poref;
    }

    public function getInternalref(): ?string
    {
        return $this->poref;
    }

    public function getAmountht(): float
    {
        return $this->amountht ?? 0.0;
    }

    public function getAmounttax(): float
    {
        return $this->amounttax ?? 0.0;
    }

    public function getAmountttc(): float
    {
        return $this->amountttc ?? 0.0;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getDocdate(): ?\DateTimeInterface
    {
        return $this->docdate;
    }

    public function getSupervisor(): ?string
    {
        return $this->supervisor;
    }

    public function getExtra1(): ?string
    {
        return $this->extra1;
    }

    public function getExtra2(): ?string
    {
        return $this->extra2;
    }

    public function getExtra3(): ?string
    {
        return $this->extra3;
    }

    public function getExtra4(): ?string
    {
        return $this->extra4;
    }

    public function getExtra5(): ?string
    {
        return $this->extra5;
    }

    public function getExtra6(): ?string
    {
        return $this->extra6;
    }

    public function getExtra7(): ?string
    {
        return $this->extra7;
    }

    public function getExtra8(): ?string
    {
        return $this->extra8;
    }

    public function getExtra9(): ?string
    {
        return $this->extra9;
    }

    public function getExtra10(): ?string
    {
        return $this->extra10;
    }

    public function getWorkflowstatus(): int
    {
        return $this->docstatus ?? -1;
    }

    public function getArchivestatus(): ?int
    {
        return $this->archivestatus;
    }

    public function getCreatedate(): ?\DateTimeInterface
    {
        return $this->createdate;
    }

    public function getModifieddate(): ?\DateTimeInterface
    {
        return $this->modifieddate;
    }

    /**
     * @return Collection<int, Datawarehouseaccounting>
     */
    public function getAccountings(): Collection
    {
        return $this->accountings;
    }
}
