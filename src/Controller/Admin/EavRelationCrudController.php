<?php
namespace App\Controller\Admin;

use App\Entity\EavRelation;
use App\Controller\Admin\BaseCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{
    AssociationField, DateTimeField, TextareaField, IdField
};

class EavRelationCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return EavRelation::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('relId')->onlyOnIndex(),
            AssociationField::new('tenant'),
            AssociationField::new('type'),
            AssociationField::new('parent'),
            AssociationField::new('child'),
            DateTimeField::new('validFrom')->hideOnIndex(),
            DateTimeField::new('validTo')->hideOnIndex(),
            TextareaField::new('notes')->hideOnIndex(),
        ];
    }
}
