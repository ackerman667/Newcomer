<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240925153109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes ADD autre_utilisateur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes ADD CONSTRAINT FK_BD940CBBC2808E16 FOREIGN KEY (autre_utilisateur_id) REFERENCES user_autre (id)');
        $this->addSql('CREATE INDEX IDX_BD940CBBC2808E16 ON demandes (autre_utilisateur_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes DROP FOREIGN KEY FK_BD940CBBC2808E16');
        $this->addSql('DROP INDEX IDX_BD940CBBC2808E16 ON demandes');
        $this->addSql('ALTER TABLE demandes DROP autre_utilisateur_id');
    }
}
