<?php

declare(strict_types=1);

namespace App\Order\Domain;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'order_products')]
#[ORM\UniqueConstraint(name: 'uniq_order_products_order_position', columns: ['order_id', 'position'])]
final class OrderProduct
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) Uuid $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Order $order;

    #[ORM\Column(type: Types::SMALLINT)]
    public private(set) int $position;

    #[ORM\Column(length: 64)]
    public private(set) string $productId;

    #[ORM\Column(length: 255)]
    public private(set) string $name;

    #[ORM\Column(type: Types::DECIMAL, precision: DecimalAmount::PRECISION, scale: DecimalAmount::SCALE)]
    public private(set) string $price;

    #[ORM\Column]
    public private(set) int $quantity;

    public function __construct(Order $order, int $position, ProductLine $line)
    {
        $this->id = Uuid::v7();
        $this->order = $order;
        $this->position = $position;
        $this->productId = $line->productId;
        $this->name = $line->name;
        $this->price = $line->price->value;
        $this->quantity = $line->quantity;
    }
}
