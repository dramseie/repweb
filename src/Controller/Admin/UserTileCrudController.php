<?php
// src/Controller/Admin/UserTileCrudController.php
namespace App\Controller\Admin;

use App\Entity\UserTile;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class UserTileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return UserTile::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('User Tiles')
            ->setEntityLabelInSingular('User Tile')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN'); // secure the section
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield AssociationField::new('user');
        yield AssociationField::new('tile')->setLabel('Report Tile');
        yield IntegerField::new('position');
        yield BooleanField::new('pinned');
        yield ArrayField::new('layout')->hideOnIndex();
        yield DateTimeField::new('createdAt');

        // Make CRUD read-only by hiding forms
        if (in_array($pageName, [Crud::PAGE_EDIT, Crud::PAGE_NEW], true)) {
            // Returning no fields prevents form rendering
            return [];
        }
    }
}
