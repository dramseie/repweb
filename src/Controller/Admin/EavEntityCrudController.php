<?php
namespace App\Controller\Admin;

use App\Entity\EavEntity;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,TextField,IdField,DateTimeField};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Crud, Actions, Action};

class EavEntityCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return EavEntity::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('entityId')->onlyOnIndex(),
            AssociationField::new('tenant'),
            AssociationField::new('type'),
            TextField::new('name'),
            DateTimeField::new('createdAt')->setFormTypeOption('disabled', true)->hideOnForm(),
        ];
    }

}
