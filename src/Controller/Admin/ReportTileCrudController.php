<?php

namespace App\Controller\Admin;

use App\Entity\ReportTile;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ReportTileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ReportTile::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Report Tile')
            ->setEntityLabelInPlural('Report Tiles')
            ->setDefaultSort(['title' => 'ASC'])
            ->showEntityActionsInlined()
            // IMPORTANT: don't let EA auto‑include fields we didn’t declare
            ->setPaginatorPageSize(25);
    }

    public function configureFields(string $pageName): iterable
    {
        // ID
        yield IdField::new('id')->onlyOnIndex();

        // Basic
        yield TextField::new('title');
        yield ChoiceField::new('type')->setChoices([
            'Link'            => 'link',
            'Grafana Iframe'  => 'grafana',
            'Generic Iframe'  => 'iframe',
            'Image'           => 'image',
            'Text / HTML'     => 'text',
        ])->renderAsNativeWidget();

        // >>> NEVER render 'config' as Text/Textarea; only as Array (read-only) <<<
        yield ArrayField::new('config')
            ->hideOnForm()
            ->setLabel('Config (JSON)');

        // Edit the VIRTUAL string field (maps to get/setConfigJson)
        yield TextareaField::new('configJson')
            ->onlyOnForms()
            ->setLabel('Config (JSON)')
            ->setHelp('Valid JSON, e.g. {"src":"/reports/capacity"} or {"url":"/path","subtitle":"Open"}')
            ->setNumOfRows(12)
            ->setFormTypeOptions([
                'attr' => [
                    'data-editor' => 'codemirror',
                    'data-mode'   => 'application/json',
                    'spellcheck'  => 'false',
                    'style'       => 'font-family:monospace',
                ],
            ]);

        yield UrlField::new('thumbnailUrl')->setRequired(false)->hideOnIndex();

        // allowedRoles as array on forms (simple JSON array)
        yield ArrayField::new('allowedRoles')
            ->onlyOnForms()
            ->setHelp('e.g. ["ROLE_ADMIN","ROLE_USER"]. Leave empty for everyone.');

        yield BooleanField::new('isActive');

        // Timestamps
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->hideOnForm();
    }
}
