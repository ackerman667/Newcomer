<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904140004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes CHANGE missions missions LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE email email VARCHAR(500) NOT NULL, CHANGE fonction fonction LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_autre CHANGE fonction fonction LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes CHANGE missions missions VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE email email VARCHAR(180) NOT NULL, CHANGE fonction fonction VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_autre CHANGE fonction fonction VARCHAR(255) DEFAULT NULL');
    }
}
