<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260126133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Smartsheet activities report for current and next month.';
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
SELECT country AS Country,
       site_name AS SiteName,
       DATE_FORMAT(Start_Date, '%Y-%m-%d') AS StartDate,
       DATE_FORMAT(End_Date, '%Y-%m-%d') AS EndDate,
       task_name AS TaskName
FROM smartsheet_master_data
WHERE (Start_Date IS NOT NULL OR End_Date IS NOT NULL)
  AND (
    Start_Date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01')
                   AND LAST_DAY(DATE_ADD(CURDATE(), INTERVAL 1 MONTH))
    OR
    End_Date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01')
                 AND LAST_DAY(DATE_ADD(CURDATE(), INTERVAL 1 MONTH))
  )
ORDER BY country, site_name, row_num
SQL;

        $this->addSql(
            'INSERT INTO report (reptype, repshort, reptitle, repdesc, repsql, repparam, repowner, repts, reptenant)
             VALUES (:reptype, :repshort, :reptitle, :repdesc, :repsql, :repparam, :repowner, NOW(), :reptenant)',
            [
                'reptype' => 'sql',
                'repshort' => 'smartsheet_activities',
                'reptitle' => 'Smartsheet Activities (Current + Next Month)',
                'repdesc' => 'Activities with start or end dates in the current or next month.',
                'repsql' => $sql,
                'repparam' => null,
                'repowner' => 'system',
                'reptenant' => json_encode(['tenant' => 'IKEA'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM report WHERE repshort = :repshort', [
            'repshort' => 'smartsheet_activities',
        ]);
    }
}
