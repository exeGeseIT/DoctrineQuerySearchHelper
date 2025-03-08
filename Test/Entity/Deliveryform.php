<?php

namespace ExeGeseIT\Test\Entity;

use ExeGeseIT\Test\Repository\DeliveryformRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryformRepository::class)]
#[ORM\Table(name: 'deliveryform')]
class Deliveryform 
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'iddeliveryform', type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'deliveryformstatus_iddeliveryformstatus', referencedColumnName: 'iddeliveryformstatus', nullable: false)]
    private ?Deliveryformstatus $status = null;

    #[ORM\Column(name: 'referenceDeliveryform', type: Types::STRING, length: 45)]
    private ?string $reference = null;

    #[ORM\Column(name: 'externalrefDeliveryform', type: Types::STRING, length: 45, nullable: true)]
    private ?string $externalref = null;

    #[ORM\Column(name: 'isRemovalDeliveryform', type: Types::BOOLEAN, options: [
        'default' => 0,
    ])]
    private bool $isremoval = false;

    #[ORM\Column(name: 'dealerDeliveryform', type: Types::STRING, length: 45, nullable: true)]
    private ?string $dealer = null;

    #[ORM\Column(name: 'orderDeliveryform', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $order = 0;

    #[ORM\Column(name: 'appointmentDeliveryform', type: Types::STRING, length: 45, nullable: true)]
    private ?string $appointment = null;

    #[ORM\Column(name: 'mainContactDeliveryform', type: Types::STRING, length: 255)]
    private ?string $contact = null;

    #[ORM\Column(name: 'mainContactPhoneDeliveryform', type: Types::STRING, length: 45, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'mainContactEmailDeliveryform', type: Types::STRING, length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'address1Deliveryform', type: Types::STRING, length: 255)]
    private ?string $address1 = null;

    #[ORM\Column(name: 'address2Deliveryform', type: Types::STRING, length: 255, nullable: true)]
    private ?string $address2 = null;

    #[ORM\Column(name: 'address3Deliveryform', type: Types::STRING, length: 255, nullable: true)]
    private ?string $address3 = null;

    #[ORM\Column(name: 'zipCodeDeliveryform', type: Types::STRING, length: 5)]
    private ?string $zipcode = null;

    #[ORM\Column(name: 'cityDeliveryform', type: Types::STRING, length: 45)]
    private ?string $city = null;

    #[ORM\Column(name: 'isVipDeliveryform', type: Types::BOOLEAN, options: [
        'default' => 0,
    ])]
    private bool $isvip = false;

    #[ORM\Column(name: 'isCradleDeliveryform', type: Types::BOOLEAN, options: [
        'default' => 0,
    ])]
    private bool $iscradle = false;

    #[ORM\Column(name: 'isAssemblyDeliveryform', type: Types::BOOLEAN, options: [
        'default' => 0,
    ])]
    private bool $isassembly = false;

    #[ORM\Column(name: 'noticeDeliveryform', type: Types::STRING, length: 255, nullable: true)]
    private ?string $notice = null;

    /**
     * @var Collection<Article>
     */
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'deliveryform', cascade: ['persist'])]
    private readonly Collection $articles;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s/%s',
            $this->getReference() ?? '-?-',
            $this->getExternalref() ?? '---'
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?Deliveryformstatus
    {
        return $this->status;
    }

    public function setStatus(Deliveryformstatus $deliveryformstatus): self
    {
        $this->status = $deliveryformstatus;

        return $this;
    }

    public function setOrder(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getExternalref(): ?string
    {
        return $this->externalref;
    }

    public function isRemoval(): bool
    {
        return $this->getIsremoval();
    }

    public function getIsremoval(): bool
    {
        return $this->isremoval;
    }

    public function getDealer(): ?string
    {
        return $this->dealer;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getAppointment(): ?string
    {
        return $this->appointment;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getEmail(): ?string
    {
        return $this->emaill;
    }

    public function getAddress1(): ?string
    {
        return $this->address1;
    }

    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    public function getAddress3(): ?string
    {
        return $this->address3;
    }

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function isVip(): bool
    {
        return $this->getIsvip();
    }

    public function getIsvip(): bool
    {
        return $this->isvip;
    }

    public function isCradle(): bool
    {
        return $this->getIscradle();
    }

    public function getIscradle(): bool
    {
        return $this->iscradle;
    }

    public function isAssembly(): bool
    {
        return $this->getIsassembly();
    }

    public function getIsassembly(): bool
    {
        return $this->isassembly;
    }

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }
}
