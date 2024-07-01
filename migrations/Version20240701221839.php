<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240701221839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP COLUMN state');
        $this->addSql('ALTER TABLE "order" ADD COLUMN state VARCHAR(255)');
        $this->addSql('ALTER TABLE "order" ADD COLUMN is_paid BOOLEAN');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP COLUMN is_paid');
        $this->addSql('ALTER TABLE "order" DROP COLUMN state');
        $this->addSql('ALTER TABLE "order" ADD COLUMN state JSON');
    }
}
