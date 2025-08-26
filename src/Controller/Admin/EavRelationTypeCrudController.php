<?php
namespace App\Controller\Admin;

use App\Entity\EavRelationType;
use App\Controller\Admin\BaseCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,TextField,TextareaField,BooleanField,IdField,DateTimeField};

class EavRelationTypeCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return EavRelationType::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('relTypeId')->onlyOnIndex(),
            AssociationField::new('tenant'),
            TextField::new('name'),
            TextareaField::new('description')->hideOnIndex(),
            BooleanField::new('isDirected')->renderAsSwitch(false),
            DateTimeField::new('createdAt')->onlyOnIndex(),
        ];
    }
}
