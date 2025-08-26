<?php
namespace App\Controller\Admin;

use App\Entity\EavValue;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{
    AssociationField, TextEditorField, IntegerField, NumberField, BooleanField, DateTimeField, IdField, TextField
};

class EavValueCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return EavValue::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('valueId')->onlyOnIndex(),
            AssociationField::new('entity'),
            AssociationField::new('attribute'),
            TextEditorField::new('valueString')->hideOnIndex()->setHelp('string'),
            IntegerField::new('valueInteger')->hideOnIndex()->setHelp('integer'),
            NumberField::new('valueDecimal')->hideOnIndex()->setNumDecimals(6)->setHelp('decimal'),
            BooleanField::new('valueBoolean')->hideOnIndex()->setHelp('boolean'),
            DateTimeField::new('valueDatetime')->hideOnIndex()->setHelp('datetime'),
            TextField::new('valueJson')->hideOnIndex()->setHelp('json raw'),
            DateTimeField::new('createdAt')->onlyOnIndex(),
        ];
    }
}
