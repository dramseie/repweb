<?php
// src/Controller/Admin/MenuItemCrudController.php

namespace App\Controller\Admin;

use App\Entity\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

final class MenuItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MenuItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Menu Item')
            ->setEntityLabelInPlural('Menu Items')
            ->setDefaultSort(['parent' => 'ASC', 'position' => 'ASC', 'label' => 'ASC'])
            ->showEntityActionsInlined(); // inline "Edit / Delete" instead of the 3-dots menu
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::NEW, Action::EDIT, Action::DELETE, Action::DETAIL]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('id')->onlyOnIndex();

        yield TextField::new('label')->setColumns(6)->setRequired(true);

		yield AssociationField::new('parent')
			->setLabel('Parent')
			->setRequired(false)
			->autocomplete()
			->setFormTypeOption('attr', [
				'data-ea-placeholder' => '— none —',
			])
			->setColumns(6);


        yield IntegerField::new('position')->setColumns(4);
        yield BooleanField::new('dividerBefore')->setColumns(4);
        yield BooleanField::new('isActive')->setColumns(4);

        yield TextField::new('url')
            ->hideOnIndex()
            ->setHelp('Absolute or app-relative (e.g. /reports). Leave empty and add children to make a dropdown.')
            ->setColumns(6);

        yield TextField::new('route')
            ->hideOnIndex()
            ->setHelp('Optional Symfony route name (used if no URL).')
            ->setColumns(6);

        // ⚠️ Bind to TEXT column, not array helper
        yield TextareaField::new('routeParamsText', 'Route Params (JSON)')
            ->hideOnIndex()
            ->setHelp('JSON object, e.g. {"id":123}. Stored in DB as TEXT.')
            ->setFormTypeOptions([
                'attr' => [
                    'rows' => 4,
                    'data-editor' => 'codemirror', // your assets/app.js will turn this into CodeMirror
                    'data-mode' => 'application/json',
                ],
            ])
            ->setColumns(12);

        yield TextField::new('megaGroup', 'Mega Group')
            ->hideOnIndex()
            ->setHelp('If set, parent becomes a mega menu grouped by this label.')
            ->setColumns(6);

        yield TextField::new('icon')
            ->hideOnIndex()
            ->setHelp('Bootstrap icon class, e.g. "bi-table", "bi-graph-up".')
            ->setColumns(6);

        yield TextField::new('badge')
            ->hideOnIndex()
            ->setHelp('Optional small badge text (e.g. "NEW").')
            ->setColumns(6);

        // ⚠️ Bind to TEXT column, not array helper
        yield TextareaField::new('rolesText', 'Roles (JSON array)')
            ->hideOnIndex()
            ->setHelp('Optional. Example: ["ROLE_ADMIN","ROLE_REPORTS"].')
            ->setFormTypeOptions([
                'attr' => [
                    'rows' => 3,
                    'data-editor' => 'codemirror',
                    'data-mode' => 'application/json',
                ],
            ])
            ->setColumns(12);

        // Use a textarea for description so Trumbowyg can attach
        yield TextareaField::new('description')
            ->hideOnIndex()
            ->setHelp('Shown under the item in dropdowns (especially in mega menu).')
            ->setFormTypeOptions([
                'attr' => [
                    'rows' => 2,
                    'data-editor' => 'trumbowyg', // your assets/app.js will turn this into Trumbowyg
                ],
            ])
            ->setColumns(12);

        // Optional compact previews on index to show presence of JSON
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('routeParamsText', 'Params')
                ->formatValue(fn($v) => $v ? mb_strimwidth($v, 0, 40, '…') : '')
                ->onlyOnIndex();

            yield TextField::new('rolesText', 'Roles')
                ->formatValue(fn($v) => $v ? mb_strimwidth($v, 0, 40, '…') : '')
                ->onlyOnIndex();
        }
    }
}