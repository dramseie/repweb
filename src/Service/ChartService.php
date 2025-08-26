<?php
        namespace App\Service; use Doctrine\DBAL\Connection;
        class ChartService { public function __construct(private Connection $c){} public function getChartData(string $role): array {
          $sql="SELECT role, COUNT(*) count FROM people"; if($role!=='ROLE_ADMIN'){ $sql.=" WHERE role != 'ROLE_ADMIN'"; } $sql.=" GROUP BY role"; return $this->c->fetchAllAssociative($sql,[]);
        } }
        