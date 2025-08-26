<?php
        namespace App\Service;
        use Doctrine\DBAL\Connection;
        class DynamicQueryService {
          public function __construct(private Connection $connection) {}
          public function getData(string $role, ?string $search, int $start, int $length, int $orderCol, string $orderDir): array {
            $columns=['id','name','email','role']; $orderBy=$columns[$orderCol]??'id';
            $where=[]; $params=['start'=>$start,'length'=>$length];
            if($role!=='ROLE_ADMIN'){ $where[]="role != 'ROLE_ADMIN'"; }
            if($search){ $where[]="(name LIKE :search OR email LIKE :search OR role LIKE :search)"; $params['search']='%'.$search.'%'; }
            $whereSql=$where?(' WHERE '.implode(' AND ',$where)):'';
            $sql="SELECT id,name,email,role FROM people $whereSql ORDER BY $orderBy $orderDir LIMIT :start, :length";
            $stmt=$this->connection->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAllAssociative();
            $total=(int)$this->connection->fetchOne("SELECT COUNT(*) FROM people");
            $filtered=(int)$this->connection->fetchOne("SELECT COUNT(*) FROM people $whereSql", $search?['search'=>$params['search']]:[]);
            return ['draw'=>rand(1,9999),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows];
          }
          public function getAllData(string $role, ?string $search): array {
            $where=[]; $params=[];
            if($role!=='ROLE_ADMIN'){ $where[]="role != 'ROLE_ADMIN'"; }
            if($search){ $where[]="(name LIKE :search OR email LIKE :search OR role LIKE :search)"; $params['search']='%'.$search.'%'; }
            $whereSql=$where?(' WHERE '.implode(' AND ',$where)):'';
            $stmt=$this->connection->prepare("SELECT id,name,email,role FROM people $whereSql"); $stmt->execute($params);
            return $stmt->fetchAllAssociative();
          }
        }
        