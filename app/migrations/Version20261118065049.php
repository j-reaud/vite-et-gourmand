<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118065049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the employee order modification audit table.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_modification (id INT AUTO_INCREMENT NOT NULL, contact_method VARCHAR(20) NOT NULL, reason LONGTEXT NOT NULL, modified_at DATETIME NOT NULL, previous_data JSON NOT NULL, new_data JSON NOT NULL, customer_order_id INT NOT NULL, modified_by_id INT NOT NULL, INDEX IDX_EBA4B1B8A15A2E17 (customer_order_id), INDEX IDX_EBA4B1B899049ECE (modified_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_modification ADD CONSTRAINT FK_EBA4B1B8A15A2E17 FOREIGN KEY (customer_order_id) REFERENCES customer_order (id)');
        $this->addSql('ALTER TABLE order_modification ADD CONSTRAINT FK_EBA4B1B899049ECE FOREIGN KEY (modified_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_modification DROP FOREIGN KEY FK_EBA4B1B8A15A2E17');
        $this->addSql('ALTER TABLE order_modification DROP FOREIGN KEY FK_EBA4B1B899049ECE');
        $this->addSql('DROP TABLE order_modification');
    }
}
