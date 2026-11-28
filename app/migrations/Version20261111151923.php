<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261111151923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the customer order table for customer order history.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE customer_order (id INT AUTO_INCREMENT NOT NULL, menu_name VARCHAR(150) NOT NULL, ordered_at DATETIME NOT NULL, delivery_at DATETIME NOT NULL, number_of_people INT NOT NULL, status VARCHAR(30) NOT NULL, menu_price NUMERIC(10, 2) NOT NULL, discount_amount NUMERIC(10, 2) DEFAULT NULL, delivery_charge NUMERIC(10, 2) NOT NULL, total_price NUMERIC(10, 2) NOT NULL, delivery_address VARCHAR(255) NOT NULL, customer_id INT NOT NULL, menu_id INT NOT NULL, INDEX IDX_3B1CE6A39395C3F3 (customer_id), INDEX IDX_3B1CE6A3CCD7E912 (menu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A39395C3F3 FOREIGN KEY (customer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A3CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A39395C3F3');
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A3CCD7E912');
        $this->addSql('DROP TABLE customer_order');
    }
}
