<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cinema booking tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `user` (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE room (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            `rows` INT NOT NULL,
            seats_per_row INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE seat (
            id INT AUTO_INCREMENT NOT NULL,
            room_id INT NOT NULL,
            row_label VARCHAR(2) NOT NULL,
            seat_number INT NOT NULL,
            INDEX IDX_3D5C366654177093 (room_id),
            UNIQUE INDEX unique_seat_in_room (room_id, row_label, seat_number),
            PRIMARY KEY(id),
            CONSTRAINT FK_3D5C366654177093 FOREIGN KEY (room_id) REFERENCES room (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE screening (
            id INT AUTO_INCREMENT NOT NULL,
            room_id INT NOT NULL,
            movie_title VARCHAR(255) NOT NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            INDEX IDX_7A38CC7254177093 (room_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_7A38CC7254177093 FOREIGN KEY (room_id) REFERENCES room (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE reservation (
            id INT AUTO_INCREMENT NOT NULL,
            screening_id INT NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            seats JSON NOT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_42C8495563CE07D3 (screening_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_42C8495563CE07D3 FOREIGN KEY (screening_id) REFERENCES screening (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE screening');
        $this->addSql('DROP TABLE seat');
        $this->addSql('DROP TABLE room');
        $this->addSql('DROP TABLE `user`');
    }
}
