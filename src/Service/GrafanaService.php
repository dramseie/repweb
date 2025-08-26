<?php
        namespace App\Service;
        use Doctrine\DBAL\Connection; use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
        class GrafanaService {
          public function __construct(private Connection $db, private ParameterBagInterface $p) {}
          public function listDashboardsForUser(array $roles, ?array $allow): array {
            $rows=$this->db->fetchAllAssociative("SELECT uid,slug,title,allowed_roles FROM grafana_dashboard");
            $out=[]; foreach($rows as $r){ $allowed=json_decode($r['allowed_roles']??'[]',true) ?: [];
              $okRole=empty($allowed) or array_intersect($roles,$allowed);
              $okUser=empty($allow) or in_array($r['uid'],$allow,true);
              if($okRole && $okUser){ $out[]=['uid'=>$r['uid'],'slug'=>$r['slug'],'title'=>$r['title']]; }
            } return $out;
          }
          public function buildEmbedUrl(string $uid): string { return '/grafana/embed/'.urlencode($uid); }
          public function grafanaBase(): string { return $this->p->get('env(GRAFANA_BASE_URL)'); }
          public function grafanaOrg(): string { return (string)$this->p->get('env(GRAFANA_ORG_ID)'); }
        }
        