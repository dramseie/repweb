<?php
        namespace App\Entity;
        use Doctrine\ORM\Mapping as ORM;
        #[ORM\Entity] #[ORM\Table(name:'app_user')]
        class AppUser {
          #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column(type:'integer')] private ?int $id=null;
          #[ORM\Column(type:'string',length:180,unique:true)] private string $email;
          #[ORM\Column(type:'json')] private array $roles=[];
          #[ORM\Column(type:'string',nullable:true)] private ?string $password=null;
          #[ORM\Column(type:'json',nullable:true)] private ?array $datatable_columns=null;
          #[ORM\Column(type:'json',nullable:true)] private ?array $grafana_dashboards=null;
          #[ORM\Column(type:'string',nullable:true)] private ?string $grafana_token=null;
          public function getId(): ?int {return $this->id;}
          public function getEmail(): string {return $this->email;} public function setEmail(string $e): self {$this->email=$e; return $this;}
          public function getRoles(): array {return $this->roles;} public function setRoles(array $r): self {$this->roles=$r; return $this;}
          public function getPassword(): ?string {return $this->password;} public function setPassword(?string $p): self {$this->password=$p; return $this;}
          public function getDatatableColumns(): ?array {return $this->datatable_columns;} public function setDatatableColumns(?array $c): self {$this->datatable_columns=$c; return $this;}
          public function getGrafanaDashboards(): ?array {return $this->grafana_dashboards;} public function setGrafanaDashboards(?array $d): self {$this->grafana_dashboards=$d; return $this;}
          public function getGrafanaToken(): ?string {return $this->grafana_token;} public function setGrafanaToken(?string $t): self {$this->grafana_token=$t; return $this;}
        }
        