<?php
namespace App\Controller\Admin;

use App\Entity\Tenant;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{IdField,TextField,TextareaField,DateTimeField};

class TenantCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return Tenant::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('tenantId')->onlyOnIndex(),
            TextField::new('name'),
            TextareaField::new('description')->hideOnIndex(),
            DateTimeField::new('createdAt')->setFormTypeOption('disabled', true)->hideOnForm(),
        ];
    }
}
