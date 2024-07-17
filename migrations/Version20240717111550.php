<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240717111550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ressources DROP FOREIGN KEY FK_6A2CD5C7899A1D7E');
        $this->addSql('DROP INDEX IDX_6A2CD5C7899A1D7E ON ressources');
        $this->addSql('ALTER TABLE ressources CHANGE demande_id_id demande_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ressources ADD CONSTRAINT FK_6A2CD5C780E95E18 FOREIGN KEY (demande_id) REFERENCES demandes (id)');
        $this->addSql('CREATE INDEX IDX_6A2CD5C780E95E18 ON ressources (demande_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ressources DROP FOREIGN KEY FK_6A2CD5C780E95E18');
        $this->addSql('DROP INDEX IDX_6A2CD5C780E95E18 ON ressources');
        $this->addSql('ALTER TABLE ressources CHANGE demande_id demande_id_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ressources ADD CONSTRAINT FK_6A2CD5C7899A1D7E FOREIGN KEY (demande_id_id) REFERENCES demandes (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_6A2CD5C7899A1D7E ON ressources (demande_id_id)');
    }
}
