<?php

namespace App\Controller\Admin;

use App\Entity\Report;
use App\Entity\User;
use App\Entity\MenuItem as NavMenuItem;
use App\Entity\ReportTile;
use App\Entity\UserTile;

use App\Controller\Admin\ReportCrudController;
use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\MenuItemCrudController;
use App\Controller\Admin\ReportTileCrudController;
use App\Controller\Admin\UserTileCrudController;

use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem as EaMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Templates;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/* 👇 Add these for EAV */
use App\Entity\Tenant;
use App\Entity\EntityType;
use App\Entity\EavEntity;
use App\Entity\Attribute;
use App\Entity\TypeAttribute;
use App\Entity\EavValue;
/* 👇 New: relations */
use App\Entity\EavRelationType;
use App\Entity\EavRelation;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $url = $this->container->get(AdminUrlGenerator::class)
            ->setController(ReportCrudController::class)
            ->setAction('index')
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/images/logo.png" style="max-height:80px;" alt="repweb" />')
            ->setFaviconPath('/images/favicon.ico')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield EaMenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield EaMenuItem::section('Reports');
        yield EaMenuItem::linkToCrud('Reports', 'fa fa-table', Report::class);

        yield EaMenuItem::section('Users');
        yield EaMenuItem::linkToCrud('Users', 'fa fa-user', User::class);

        yield EaMenuItem::section('Navigation');
        yield EaMenuItem::linkToCrud('Menu Items', 'fa fa-bars', NavMenuItem::class);

        yield EaMenuItem::section('Tiles');
        yield EaMenuItem::linkToCrud('Report Tiles', 'fa fa-th', ReportTile::class)
            ->setController(ReportTileCrudController::class);
        yield EaMenuItem::linkToCrud('User Tiles (read-only)', 'fa fa-users', UserTile::class)
            ->setController(UserTileCrudController::class);

        /* 👇 EAV sections */
        yield EaMenuItem::section('EAV Dictionary');
        yield EaMenuItem::linkToCrud('Tenants', 'fa fa-building', Tenant::class);
        yield EaMenuItem::linkToCrud('Types', 'fa fa-layer-group', EntityType::class);
        yield EaMenuItem::linkToCrud('Attributes', 'fa fa-tags', Attribute::class);
        yield EaMenuItem::linkToCrud('Type ↔ Attribute', 'fa fa-sitemap', TypeAttribute::class);

        yield EaMenuItem::section('EAV Data');
        yield EaMenuItem::linkToCrud('Entities (CI)', 'fa fa-cube', EavEntity::class);
        yield EaMenuItem::linkToCrud('Values', 'fa fa-database', EavValue::class);

        /* 👇 New: EAV Relations */
        yield EaMenuItem::section('EAV Relations');
        yield EaMenuItem::linkToCrud('Relation Types', 'fa fa-link', EavRelationType::class);
        yield EaMenuItem::linkToCrud('Relations', 'fa fa-project-diagram', EavRelation::class);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addWebpackEncoreEntry('app');
    }

    public function configureTemplates(): Templates
    {
        return Templates::new()
            ->override('layout', 'admin/layout.html.twig');
    }
}
