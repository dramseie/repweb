<?php
namespace App\Controller\Admin;

use App\Entity\EntityType;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Crud, Actions, Action};
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,TextField,TextareaField,IdField,DateTimeField};

class EntityTypeCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return EntityType::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('typeId')->onlyOnIndex(),
            AssociationField::new('tenant'),
            TextField::new('name'),
            TextareaField::new('description')->hideOnIndex(),
            DateTimeField::new('createdAt')->setFormTypeOption('disabled', true)->hideOnForm(),
        ];
    }
}
