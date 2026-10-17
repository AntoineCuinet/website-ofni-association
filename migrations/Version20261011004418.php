<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011004418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Event registration forms: registration_form (built from a form_widget), registration';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE registration (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, form_id INTEGER NOT NULL, user_id INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , answers CLOB NOT NULL --(DC2Type:json)
        , CONSTRAINT FK_62A8A7A75FF69B7D FOREIGN KEY (form_id) REFERENCES registration_form (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_62A8A7A7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_62A8A7A75FF69B7D ON registration (form_id)');
        $this->addSql('CREATE INDEX IDX_62A8A7A7A76ED395 ON registration (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_REGISTRATION_FORM_USER ON registration (form_id, user_id)');
        $this->addSql('CREATE TABLE registration_form (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, instance_id INTEGER NOT NULL, widget_id INTEGER NOT NULL, description CLOB DEFAULT NULL, is_open BOOLEAN NOT NULL, closes_at DATETIME DEFAULT NULL, CONSTRAINT FK_9DB6EA933A51721D FOREIGN KEY (instance_id) REFERENCES asso_event_instance (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9DB6EA93FBE885E2 FOREIGN KEY (widget_id) REFERENCES form_widget (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9DB6EA933A51721D ON registration_form (instance_id)');
        $this->addSql('CREATE INDEX IDX_9DB6EA93FBE885E2 ON registration_form (widget_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE registration');
        $this->addSql('DROP TABLE registration_form');
    }
}
