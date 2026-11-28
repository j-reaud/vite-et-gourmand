<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110034108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the menu catalogue table.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, image_path VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, minimum_people INT NOT NULL, theme VARCHAR(80) NOT NULL, diet VARCHAR(50) NOT NULL, stock INT NOT NULL, is_published TINYINT NOT NULL, UNIQUE INDEX UNIQ_7D053A93989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE menu');
    }
}
