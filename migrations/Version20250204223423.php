<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250204223423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__board AS SELECT id, year_start, year_end, president, tresorier, secretaire, others FROM board');
        $this->addSql('DROP TABLE board');
        $this->addSql('CREATE TABLE board (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, year_start INTEGER NOT NULL, year_end INTEGER NOT NULL, president VARCHAR(255) NOT NULL, tresorier VARCHAR(255) NOT NULL, secretaire VARCHAR(255) NOT NULL, others CLOB DEFAULT NULL --(DC2Type:json)
        )');
        $this->addSql('INSERT INTO board (id, year_start, year_end, president, tresorier, secretaire, others) SELECT id, year_start, year_end, president, tresorier, secretaire, others FROM __temp__board');
        $this->addSql('DROP TABLE __temp__board');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__board AS SELECT id, year_start, year_end, president, tresorier, secretaire, others FROM board');
        $this->addSql('DROP TABLE board');
        $this->addSql('CREATE TABLE board (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, year_start INTEGER NOT NULL, year_end INTEGER NOT NULL, president VARCHAR(255) NOT NULL, tresorier VARCHAR(255) NOT NULL, secretaire VARCHAR(255) NOT NULL, others VARCHAR(1024) NOT NULL)');
        $this->addSql('INSERT INTO board (id, year_start, year_end, president, tresorier, secretaire, others) SELECT id, year_start, year_end, president, tresorier, secretaire, others FROM __temp__board');
        $this->addSql('DROP TABLE __temp__board');
    }
}
