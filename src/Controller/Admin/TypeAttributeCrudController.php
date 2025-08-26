<?php
namespace App\Controller\Admin;

use App\Entity\TypeAttribute;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,BooleanField,IntegerField,IdField};

class TypeAttributeCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return TypeAttribute::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('type'),
            AssociationField::new('attribute'),
            BooleanField::new('isRequired'),
            IntegerField::new('sortOrder'),
        ];
    }
}
