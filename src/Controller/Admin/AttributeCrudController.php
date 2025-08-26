<?php
namespace App\Controller\Admin;

use App\Entity\Attribute;
use App\Controller\Admin\Crud\Choices;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,ChoiceField,TextField,TextareaField,IdField,DateTimeField};

class AttributeCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return Attribute::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('attributeId')->onlyOnIndex(),
            AssociationField::new('tenant'),
            TextField::new('name'),
            ChoiceField::new('dataType')->setChoices(Choices::DATA_TYPES),
            TextareaField::new('description')->hideOnIndex(),
            DateTimeField::new('createdAt')->setFormTypeOption('disabled', true)->hideOnForm(),
        ];
    }
}
