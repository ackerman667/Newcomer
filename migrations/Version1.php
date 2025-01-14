<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version1 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove test column from temporary_data table';
    }

    public function up(Schema $schema): void
    {
        // Remove test column from temporary_data table
        $this->addSql('ALTER TABLE temporary_data DROP test');
    }

    public function down(Schema $schema): void
    {
        // Add test column back to temporary_data table
        $this->addSql('ALTER TABLE temporary_data ADD test VARCHAR(255) DEFAULT NULL');
    }
}