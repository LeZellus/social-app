<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616184249 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE destination (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, social_account_id INT NOT NULL, name VARCHAR(255) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, settings JSON DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_3EC63EAAA76ED395 (user_id), INDEX IDX_3EC63EAA5538ED78 (social_account_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE destination ADD CONSTRAINT FK_3EC63EAAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE destination ADD CONSTRAINT FK_3EC63EAA5538ED78 FOREIGN KEY (social_account_id) REFERENCES social_account (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE destination DROP FOREIGN KEY FK_3EC63EAAA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE destination DROP FOREIGN KEY FK_3EC63EAA5538ED78
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE destination
        SQL);
    }
}
