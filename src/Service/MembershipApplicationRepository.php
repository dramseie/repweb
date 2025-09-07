<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

final class MembershipApplicationRepository
{
    public function __construct(private Connection $db) {}

    public function create(string $email, string $token, ?string $ip, ?string $ua): void
    {
        $this->db->insert('ext_comm.membership_applications', [
            'email'      => mb_strtolower(trim($email)),
            'token'      => $token,
            'status'     => 'pending',
            'ip_address' => $ip,
            'user_agent' => mb_substr((string)$ua, 0, 255),
        ]);
    }

    public function byToken(string $token): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM ext_comm.membership_applications WHERE token = ?',
            [$token]
        );
        return $row ?: null;
    }

    public function markVerified(string $token): void
    {
        $this->db->executeStatement(
            "UPDATE ext_comm.membership_applications
             SET status='verified', verified_at=NOW()
             WHERE token = ? AND status='pending'",
            [$token]
        );
    }

    public function approve(string $token): void
    {
        $this->db->executeStatement(
            "UPDATE ext_comm.membership_applications
             SET status='approved', approved_at=NOW()
             WHERE token = ? AND status IN ('pending','verified')",
            [$token]
        );
    }

    public function findOrNullByEmail(string $email): ?array
    {
        $row = $this->db->fetchAssociative(
            "SELECT * FROM ext_comm.membership_applications
             WHERE email = ? ORDER BY id DESC LIMIT 1",
            [mb_strtolower(trim($email))]
        );
        return $row ?: null;
    }
}
