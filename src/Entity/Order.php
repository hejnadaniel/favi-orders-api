<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\InvalidOrderException;
use App\ValueObject\DecimalAmount;
use App\ValueObject\ProductLine;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\UniqueConstraint(name: 'uniq_orders_partner_order', columns: ['partner_id', 'order_id'])]
final class Order
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) Uuid $id;

    #[ORM\Column(length: 64)]
    public private(set) string $partnerId;

    #[ORM\Column(length: 64)]
    public private(set) string $orderId;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public private(set) DateTimeImmutable $expectedDeliveryDate;

    #[ORM\Column(type: Types::DECIMAL, precision: DecimalAmount::PRECISION, scale: DecimalAmount::SCALE)]
    public private(set) string $totalValue;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    public private(set) DateTimeImmutable $updatedAt;

    /** @var Collection<int, OrderProduct> */
    #[ORM\OneToMany(targetEntity: OrderProduct::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $products;

    /**
     * @param list<ProductLine> $products
     *
     * @throws InvalidOrderException
     */
    public function __construct(
        string $partnerId,
        string $orderId,
        DateTimeImmutable $expectedDeliveryDate,
        DecimalAmount $totalValue,
        array $products,
        DateTimeImmutable $createdAt,
    ) {
        if ($products === []) {
            throw new InvalidOrderException('An order must contain at least one product.');
        }

        $this->id = Uuid::v7();
        $this->partnerId = $partnerId;
        $this->orderId = $orderId;
        $this->expectedDeliveryDate = $expectedDeliveryDate;
        $this->totalValue = $totalValue->value;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
        $this->products = new ArrayCollection();

        foreach ($products as $position => $productLine) {
            $this->products->add(new OrderProduct($this, $position, $productLine));
        }
    }

    public function changeExpectedDeliveryDate(DateTimeImmutable $expectedDeliveryDate, DateTimeImmutable $changedAt): void
    {
        $this->expectedDeliveryDate = $expectedDeliveryDate;
        $this->updatedAt = $changedAt;
    }

    /**
     * @return list<OrderProduct>
     */
    public function getProducts(): array
    {
        return $this->products->getValues();
    }
}
