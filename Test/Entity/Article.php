<?php

declare(strict_types=1);

namespace ExeGeseIT\Test\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ExeGeseIT\Test\Repository\ArticleRepository;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'article')]
class Article implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'idarticle', type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'articles', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'deliveryform_iddeliveryform', referencedColumnName: 'iddeliveryform', nullable: false)]
    private ?Deliveryform $deliveryform = null;

    #[ORM\ManyToOne(cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'articlestatus_idarticlestatus', referencedColumnName: 'idarticlestatus', nullable: false)]
    private ?Articlestatus $status = null;

    #[ORM\Column(name: 'referenceArticle', type: Types::STRING, length: 45)]
    private ?string $reference = null;

    #[ORM\Column(name: 'labelArticle', type: Types::STRING, length: 100)]
    private ?string $name = null;

    #[ORM\Column(name: 'quantityArticle', type: Types::INTEGER, options: [
        'default' => 1,
    ])]
    private int $quantity = 1;

    public function __toString(): string
    {
        return $this->getReference() ?? sprintf('ARTICLE-#%d', $this->getId() ?? 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeliveryform(): ?Deliveryform
    {
        return $this->deliveryform;
    }

    public function getStatus(): ?Articlestatus
    {
        return $this->status;
    }

    public function setStatus(Articlestatus $articlestatus): static
    {
        $this->status = $articlestatus;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLabel(): ?string
    {
        return $this->getName();
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
