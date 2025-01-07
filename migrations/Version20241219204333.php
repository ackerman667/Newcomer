<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241219204333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE temporary_data ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE temporary_data ADD CONSTRAINT FK_FAADEE24A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_FAADEE24A76ED395 ON temporary_data (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE temporary_data DROP FOREIGN KEY FK_FAADEE24A76ED395');
        $this->addSql('DROP INDEX IDX_FAADEE24A76ED395 ON temporary_data');
        $this->addSql('ALTER TABLE temporary_data DROP user_id');
    }
}
