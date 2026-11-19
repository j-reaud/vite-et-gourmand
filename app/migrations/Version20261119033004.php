<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261119033004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfills system-generated numbers for existing employees.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                UPDATE user
                SET employee_number = CONCAT('VG-EMP-', LPAD(id, 6, '0'))
                WHERE employee_number IS NULL
                  AND JSON_CONTAINS(roles, '"ROLE_EMPLOYEE"') = 1
                SQL,
        );

    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                UPDATE user
                SET employee_number = NULL
                WHERE employee_number = CONCAT(
                    'VG-EMP-',
                    LPAD(id, 6, '0')
                )
                  AND JSON_CONTAINS(roles, '"ROLE_EMPLOYEE"') = 1
                SQL,
        );

    }
}
