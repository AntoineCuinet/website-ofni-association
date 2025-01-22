<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250120164502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE asso_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description CLOB NOT NULL, description_mini CLOB NOT NULL, image_name VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE asso_event_instance (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_event_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, date DATE NOT NULL, CONSTRAINT FK_CA7BE30DEE3A445A FOREIGN KEY (parent_event_id) REFERENCES asso_event (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_CA7BE30DEE3A445A ON asso_event_instance (parent_event_id)');
        $this->addSql('CREATE TABLE asso_event_instance_sponsor (asso_event_instance_id INTEGER NOT NULL, sponsor_id INTEGER NOT NULL, PRIMARY KEY(asso_event_instance_id, sponsor_id), CONSTRAINT FK_9EC98C8ADC1D5E32 FOREIGN KEY (asso_event_instance_id) REFERENCES asso_event_instance (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9EC98C8A12F7FB51 FOREIGN KEY (sponsor_id) REFERENCES sponsor (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9EC98C8ADC1D5E32 ON asso_event_instance_sponsor (asso_event_instance_id)');
        $this->addSql('CREATE INDEX IDX_9EC98C8A12F7FB51 ON asso_event_instance_sponsor (sponsor_id)');
        $this->addSql('CREATE TABLE sponsor (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, website VARCHAR(511) DEFAULT NULL, permanent BOOLEAN NOT NULL, logo_name VARCHAR(255) DEFAULT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE asso_event');
        $this->addSql('DROP TABLE asso_event_instance');
        $this->addSql('DROP TABLE asso_event_instance_sponsor');
        $this->addSql('DROP TABLE sponsor');
    }
}
