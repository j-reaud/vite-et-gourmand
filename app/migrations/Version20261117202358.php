<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117202358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the order status history table for customer tracking.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_status_history (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(30) NOT NULL, changed_at DATETIME NOT NULL, customer_order_id INT NOT NULL, INDEX IDX_471AD77EA15A2E17 (customer_order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_status_history ADD CONSTRAINT FK_471AD77EA15A2E17 FOREIGN KEY (customer_order_id) REFERENCES customer_order (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_status_history DROP FOREIGN KEY FK_471AD77EA15A2E17');
        $this->addSql('DROP TABLE order_status_history');
    }
}
