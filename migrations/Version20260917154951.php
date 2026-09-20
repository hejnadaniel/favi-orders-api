<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917154951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create orders and order_products with the (partner_id, order_id) uniqueness guard';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE orders (id UUID NOT NULL, partner_id VARCHAR(64) NOT NULL, order_id VARCHAR(64) NOT NULL, expected_delivery_date DATE NOT NULL, total_value NUMERIC(14, 2) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_orders_partner_order ON orders (partner_id, order_id)');
        $this->addSql('CREATE TABLE order_products (id UUID NOT NULL, position SMALLINT NOT NULL, product_id VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, price NUMERIC(14, 2) NOT NULL, quantity INT NOT NULL, order_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_order_products_order_position ON order_products (order_id, position)');
        $this->addSql('CREATE INDEX IDX_5242B8EB8D9F6D38 ON order_products (order_id)');
        $this->addSql('ALTER TABLE order_products ADD CONSTRAINT FK_5242B8EB8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_products DROP CONSTRAINT FK_5242B8EB8D9F6D38');
        $this->addSql('DROP TABLE order_products');
        $this->addSql('DROP TABLE orders');
    }
}
