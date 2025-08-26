#!/usr/bin/env bash
set -euo pipefail

BASE="src/Controller/Admin"
CRUD="$BASE/Crud"

mkdir -p "$BASE" "$CRUD"

# Choices helper
cat > "$CRUD/Choices.php" <<'PHP'
<?php
namespace App\Controller\Admin\Crud;

final class Choices
{
    public const DATA_TYPES = [
        'string'   => 'string',
        'integer'  => 'integer',
        'decimal'  => 'decimal',
        'boolean'  => 'boolean',
        'datetime' => 'datetime',
        'json'     => 'json',
    ];
}
PHP

# TenantCrudController
cat > "$BASE/TenantCrudController.php" <<'PHP'
<?php
namespace App\Controller\Admin;

use App\Entity\Tenant;
use App\Controller\Admin\BaseCrudController; // <- your base
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
PHP

# EntityTypeCrudController
cat > "$BASE/EntityTypeCrudController.php" <<'PHP'
<?php
namespace App\Controller\Admin;

use App\Entity\EntityType;
use App\Controller\Admin\BaseCrudController; // <- your base
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
PHP

# EavEntityCrudController
cat > "$BASE/EavEntityCrudController.php" <<'PHP'
<?php
namespace App\Controller\Admin;

use App\Entity\EavEntity;
use App\Controller\Admin\BaseCrudController; // <- your base
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,TextField,IdField,DateTimeField};

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
PHP

# AttributeCrudController
cat > "$BASE/AttributeCrudController.php" <<'PHP'
<?php
namespace App\Controller\Admin;

use App\Entity\Attribute;
use App\Controller\Admin\BaseCrudController; // <- your base
use App\Controller\Admin\Crud\Choices;
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
PHP

# TypeAttributeCrudController
cat > "$BASE/TypeAttributeCrudController.php" <<'PHP'
<?php
namespace App\Controller\Admin;

use App\Entity\TypeAttribute;
use App\Controller\Admin\BaseCrudController; // <- your base
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
PHP

# EavValueCrudController
cat > "$BASE/EavValueCrudController.php" <<'PHP'
<?php
namespace App\Controller\Admin;

use App\Entity\EavValue;
use App\Controller\Admin\BaseCrudController; // <- your base
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
PHP

echo "✅ EAV CRUD controllers updated to extend BaseCrudController."
