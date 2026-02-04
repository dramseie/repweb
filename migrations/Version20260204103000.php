<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260204103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create IKEA user and scope menu items to ROLE_IKEA.';
    }

    public function up(Schema $schema): void
    {
        $email = 'ikea@ramseier.com';
        $password = 'Ikea@2026-Menu!';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->addSql(
            'INSERT INTO `user` (email, roles, password, totp_secret, totp_enabled, widget_layout)
             SELECT :email, :roles, :password, NULL, 0, NULL
             WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE email = :email)',
            [
                'email' => $email,
                'roles' => json_encode(['ROLE_IKEA'], JSON_UNESCAPED_UNICODE),
                'password' => $hash,
            ]
        );

        $this->addSql(
            'UPDATE menu_item
             SET roles = :roles
             WHERE (mega_group LIKE :ikea OR label LIKE :ikea)
               AND (roles IS NULL OR roles = "" OR roles = "[]")',
            [
                'roles' => json_encode(['ROLE_IKEA'], JSON_UNESCAPED_UNICODE),
                'ikea' => '%IKEA%',
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM `user` WHERE email = :email', [
            'email' => 'ikea@ramseier.com',
        ]);

        $this->addSql(
            'UPDATE menu_item
             SET roles = NULL
             WHERE roles = :roles AND (mega_group LIKE :ikea OR label LIKE :ikea)',
            [
                'roles' => json_encode(['ROLE_IKEA'], JSON_UNESCAPED_UNICODE),
                'ikea' => '%IKEA%',
            ]
        );
    }
}
