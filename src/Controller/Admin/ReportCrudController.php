<?php

namespace App\Controller\Admin;

use App\Entity\Report;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class ReportCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            // 🔹 Force our custom layout (with React MegaNavbar)
            ->overrideTemplates([
                'layout' => 'admin/layout.html.twig',
            ])
            ->setEntityLabelInSingular('Report')
            ->setEntityLabelInPlural('Reports')
            ->setDefaultSort(['repid' => 'DESC'])
            ->showEntityActionsInlined(); // optional
    }

    public function configureFields(string $pageName): iterable
    {
        $typeChoices = [
            'DataTables'   => 'DataTables',
            'PivotTables'  => 'PivotTables',
            'PlotlyChart'  => 'PlotlyChart',
            'Grafana'      => 'Grafana',
        ];

        yield IdField::new('repid')->onlyOnIndex();

        yield ChoiceField::new('reptype', 'Type')
            ->setChoices($typeChoices)
            ->renderExpanded(false)
            ->renderAsNativeWidget(true);

        yield TextField::new('repshort', 'Short Code')
            ->setHelp('Short identifier used for filenames, etc.');

        yield TextField::new('reptitle', 'Title');

        // WYSIWYG via Trumbowyg
        yield TextareaField::new('repdesc', 'Description')
            ->setFormTypeOptions([
                'attr' => [
                    'data-editor' => 'trumbowyg',
                    'rows' => 8,
                ],
            ]);

        // SQL via CodeMirror
        yield TextareaField::new('repsql', 'SQL')
            ->setFormTypeOptions([
                'attr' => [
                    'data-editor' => 'codemirror',
                    'data-mode'   => 'text/x-sql',
                    'rows'        => 14,
                ],
            ])
            ->hideOnIndex();

        // JSON via CodeMirror
        yield TextareaField::new('repparam', 'Parameters (JSON)')
            ->setFormTypeOptions([
                'attr' => [
                    'data-editor' => 'codemirror',
                    'data-mode'   => 'application/json',
                    'rows'        => 8,
                ],
            ])
            ->hideOnIndex();

        yield TextField::new('repowner', 'Owner')->hideOnIndex();

        yield DateTimeField::new('repts', 'Timestamp')
            ->setHelp('When the report definition was last updated.')
            ->hideOnIndex();
    }
}
