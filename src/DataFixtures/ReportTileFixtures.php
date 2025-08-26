<?php
namespace App\DataFixtures;

use App\Entity\ReportTile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ReportTileFixtures extends Fixture
{
    public function load(ObjectManager $om): void
    {
        $tiles = [
            ['title' => 'OpsRamp CPU Overview', 'type' => 'grafana', 'config' => ['src' => 'https://grafana.example/d/abc?orgId=1']],
            ['title' => 'ServiceNow Incidents', 'type' => 'link', 'config' => ['url' => '/reports/datatables/incidents', 'subtitle' => 'Open incidents']],
            ['title' => 'Capacity Planning', 'type' => 'iframe', 'config' => ['src' => '/reports/capacity']],
            ['title' => 'Welcome Text', 'type' => 'text', 'config' => ['html' => '<p>Welcome to your dashboard.</p>']],
        ];
        foreach ($tiles as $t) {
            $rt = new ReportTile();
            $rt->setTitle($t['title']);
            $rt->setType($t['type']);
            $rt->setConfig($t['config']);
            $om->persist($rt);
        }
        $om->flush();
    }
}
