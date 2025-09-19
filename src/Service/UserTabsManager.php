<?php
namespace App\Service;

use App\Entity\UiWidgetTab;
use App\Repository\UiWidgetTabRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserTabsManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private UiWidgetTabRepository $repo
    ) {}

    public function ensureTabsFor(UserInterface $user): void
    {
        $uid = (int)$user->getId();
        if ($this->repo->countForUser($uid) > 0) return;

        // Seed two tabs with default layouts
        $overview = (new UiWidgetTab())
            ->setOwnerUserId($uid)
            ->setTitle('Overview')
            ->setPosition(1)
            ->setLayoutJson($this->seedOverview());
        $map = (new UiWidgetTab())
            ->setOwnerUserId($uid)
            ->setTitle('World Map')
            ->setPosition(2)
            ->setLayoutJson($this->seedMap());

        $this->em->persist($overview);
        $this->em->persist($map);
        $this->em->flush();
    }

    private function seedOverview(): array
    {
        return [
            'version' => 1,
            'items' => [
                ['id'=>'kpi1','type'=>'kpi','title'=>'KPI Tile','props'=>['label'=>'NEW ORDERS','value'=>0,'sub'=>'today']],
                ['id'=>'md1','type'=>'markdown','title'=>'Markdown Note','props'=>['md'=>"# Note\nAdd your notes here."]],
                ['id'=>'pivot1','type'=>'pivot','title'=>'Pivot Table','props'=>['reportId'=>1234]],
            ],
            'layouts' => [
                'lg' => [
                    ['i'=>'kpi1','x'=>0,'y'=>0,'w'=>4,'h'=>6],
                    ['i'=>'md1','x'=>0,'y'=>6,'w'=>4,'h'=>10],
                    ['i'=>'pivot1','x'=>4,'y'=>0,'w'=>8,'h'=>16],
                ],
            ],
        ];
    }

    private function seedMap(): array
    {
        return [
            'version' => 1,
            'items' => [
                ['id'=>'map1','type'=>'worldmap','title'=>'World Map','props'=>['apiUrl'=>'/api/eav/geo/view','height'=>'520px']],
            ],
            'layouts' => [
                'lg' => [
                    ['i'=>'map1','x'=>0,'y'=>0,'w'=>12,'h'=>40],
                ],
            ],
        ];
    }

	public static function widgetDefs(): array
	{
		return [
			['type'=>'kpi','title'=>'KPI','w'=>3,'h'=>6,'minW'=>2,'minH'=>2,'defaults'=>['label'=>'KPI','value'=>0,'sub'=>'']],
			['type'=>'markdown','title'=>'Markdown','w'=>6,'h'=>8,'defaults'=>['md'=>"# Note\nAdd your notes here."]],
			['type'=>'plotly','title'=>'Plotly Chart','w'=>6,'h'=>18,'defaults'=>['reportId'=>1,'height'=>360]],
			['type'=>'datatable','title'=>'Data Table','w'=>6,'h'=>18,'defaults'=>['reportId'=>1,'pageLength'=>15]],
			['type'=>'pivot','title'=>'Pivot Table','w'=>6,'h'=>18,'defaults'=>['reportId'=>1]],
			['type'=>'grafana','title'=>'Grafana Panel','w'=>6,'h'=>18,'defaults'=>['src'=>'about:blank','height'=>360]],
			['type'=>'worldmap','title'=>'World Map','w'=>12,'h'=>40,'minW'=>2,'minH'=>2,'defaults'=>['apiUrl'=>'/api/eav/geo/view','height'=>'520px']],

			// 👇 NEW: NiFi widget
			['type'=>'nifi','title'=>'NiFi Process Groups','w'=>6,'h'=>18,'minW'=>2,'minH'=>2,
			 'defaults'=>['title'=>'NiFi Process Groups','refreshSec'=>300]],
		];
	}

}
