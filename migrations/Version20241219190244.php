<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241219190244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demandes (id INT AUTO_INCREMENT NOT NULL, idutilisateur_id INT DEFAULT NULL, autre_utilisateur_id INT DEFAULT NULL, date DATE DEFAULT NULL, statuts VARCHAR(255) DEFAULT NULL, heure_soumission DATETIME DEFAULT NULL, titre VARCHAR(255) DEFAULT NULL, uid_valideur VARCHAR(255) DEFAULT NULL, token VARCHAR(64) DEFAULT NULL, token_expiration DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', nom_remplacant VARCHAR(255) DEFAULT NULL, prenom_remplacant VARCHAR(255) DEFAULT NULL, telephone_remplacant VARCHAR(255) DEFAULT NULL, depart TINYINT(1) DEFAULT NULL, affectation_remplacant VARCHAR(255) DEFAULT NULL, remplacant TINYINT(1) DEFAULT NULL, commentaire VARCHAR(255) DEFAULT NULL, service VARCHAR(255) DEFAULT NULL, date_validation DATE DEFAULT NULL, infos_complementaires LONGBLOB DEFAULT NULL, missions VARCHAR(500) DEFAULT NULL, infos_personne JSON DEFAULT NULL, autre_personne TINYINT(1) DEFAULT NULL, UNIQUE INDEX UNIQ_BD940CBB5F37A13B (token), INDEX IDX_BD940CBBEAF07004 (idutilisateur_id), INDEX IDX_BD940CBBC2808E16 (autre_utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE historique_demande (id INT AUTO_INCREMENT NOT NULL, demande_id INT DEFAULT NULL, commentaire VARCHAR(255) DEFAULT NULL, statut VARCHAR(255) DEFAULT NULL, date DATETIME DEFAULT NULL, statut_operation VARCHAR(255) DEFAULT NULL, INDEX IDX_448088DA80E95E18 (demande_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ressources (id INT AUTO_INCREMENT NOT NULL, demande_id INT DEFAULT NULL, nom VARCHAR(255) DEFAULT NULL, contenu VARCHAR(255) DEFAULT NULL, INDEX IDX_6A2CD5C780E95E18 (demande_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE temporary_data (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) NOT NULL, action VARCHAR(50) NOT NULL, data JSON NOT NULL, expiration DATETIME NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FAADEE245F37A13B (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, fonction VARCHAR(255) DEFAULT NULL, date_de_naissance DATE DEFAULT NULL, statut_personne VARCHAR(255) DEFAULT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, compte_actif TINYINT(1) DEFAULT NULL, token VARCHAR(64) DEFAULT NULL, token_expiration DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', uid VARCHAR(255) DEFAULT NULL, provenance VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D6495F37A13B (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_autre (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, email VARCHAR(500) DEFAULT NULL, date_de_naissance DATE DEFAULT NULL, statut_personne VARCHAR(255) DEFAULT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, fonction VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE demandes ADD CONSTRAINT FK_BD940CBBEAF07004 FOREIGN KEY (idutilisateur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE demandes ADD CONSTRAINT FK_BD940CBBC2808E16 FOREIGN KEY (autre_utilisateur_id) REFERENCES user_autre (id)');
        $this->addSql('ALTER TABLE historique_demande ADD CONSTRAINT FK_448088DA80E95E18 FOREIGN KEY (demande_id) REFERENCES demandes (id)');
        $this->addSql('ALTER TABLE ressources ADD CONSTRAINT FK_6A2CD5C780E95E18 FOREIGN KEY (demande_id) REFERENCES demandes (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demandes DROP FOREIGN KEY FK_BD940CBBEAF07004');
        $this->addSql('ALTER TABLE demandes DROP FOREIGN KEY FK_BD940CBBC2808E16');
        $this->addSql('ALTER TABLE historique_demande DROP FOREIGN KEY FK_448088DA80E95E18');
        $this->addSql('ALTER TABLE ressources DROP FOREIGN KEY FK_6A2CD5C780E95E18');
        $this->addSql('DROP TABLE demandes');
        $this->addSql('DROP TABLE historique_demande');
        $this->addSql('DROP TABLE ressources');
        $this->addSql('DROP TABLE temporary_data');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_autre');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
