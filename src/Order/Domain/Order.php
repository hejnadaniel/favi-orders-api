<?php

declare(strict_types=1);

namespace App\Order\Domain;

use App\Order\Domain\Exception\InvalidOrderException;
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
    /**
     * UUID v7: time-ordered, so new rows append to the end of the primary key
     * index instead of scattering across it like v4 would.
     */
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) Uuid $id;

    #[ORM\Column(length: 64)]
    public private(set) string $partnerId;

    #[ORM\Column(length: 64)]
    public private(set) string $orderId;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public private(set) DateTimeImmutable $expectedDeliveryDate;

    /**
     * Stored exactly as the partner sent it. Not recomputed from the product
     * lines and not verified against them (assignment: "store raw data").
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    public private(set) string $totalValue;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    public private(set) DateTimeImmutable $updatedAt;

    /** @var Collection<int, OrderProduct> */
    #[ORM\OneToMany(targetEntity: OrderProduct::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $products;

    private function __construct(
        string $partnerId,
        string $orderId,
        DateTimeImmutable $expectedDeliveryDate,
        DecimalAmount $totalValue,
        DateTimeImmutable $now,
    ) {
        $this->id = Uuid::v7();
        $this->partnerId = $partnerId;
        $this->orderId = $orderId;
        $this->expectedDeliveryDate = $expectedDeliveryDate;
        $this->totalValue = $totalValue->value;
        $this->products = new ArrayCollection();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * @param list<ProductLine> $products
     *
     * @throws InvalidOrderException
     */
    public static function place(
        string $partnerId,
        string $orderId,
        DateTimeImmutable $expectedDeliveryDate,
        DecimalAmount $totalValue,
        array $products,
        DateTimeImmutable $now,
    ): self {
        if ($products === []) {
            throw InvalidOrderException::noProducts();
        }

        $order = new self($partnerId, $orderId, $expectedDeliveryDate, $totalValue, $now);
        foreach ($products as $position => $line) {
            $order->products->add(new OrderProduct($order, $position, $line));
        }

        return $order;
    }

    public function changeExpectedDeliveryDate(DateTimeImmutable $expectedDeliveryDate, DateTimeImmutable $now): void
    {
        $this->expectedDeliveryDate = $expectedDeliveryDate;
        $this->updatedAt = $now;
    }

    /**
     * @return list<OrderProduct>
     */
    public function products(): array
    {
        return $this->products->getValues();
    }
}
