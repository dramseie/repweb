<?php
namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Crud, Actions, Action};

abstract class BaseCrudController extends AbstractCrudController
{
    public function configureCrud(Crud $crud): Crud
    {
        // Force inline actions on index (prevents the "…" dropdown)
        return $crud->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        // Ensure DETAIL exists on index so we can style it
        $actions = $actions->add(Crud::PAGE_INDEX, Action::DETAIL);

        return $actions
            // --- INDEX: icon-only links (with titles for a11y/tooltips) ---
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $a) {
                return $a
                    ->setLabel('') // icon-only
                    ->setIcon('bi bi-eye')
                    ->setCssClass('btn btn-sm btn-outline-secondary me-1') // compact + spacing
                    ->setHtmlAttributes(['title' => 'Show', 'aria-label' => 'Show']);
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $a) {
                return $a
                    ->setLabel('')
                    ->setIcon('bi bi-pencil-square')
                    ->setCssClass('btn btn-sm btn-outline-primary me-1')
                    ->setHtmlAttributes(['title' => 'Edit', 'aria-label' => 'Edit']);
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $a) {
                return $a
                    ->setLabel('')
                    ->setIcon('bi bi-trash3')
                    ->setCssClass('btn btn-sm btn-outline-danger')
                    ->setHtmlAttributes(['title' => 'Delete', 'aria-label' => 'Delete']);
            })
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE])

            // --- DETAIL page: icon-only as well ---
            ->update(Crud::PAGE_DETAIL, Action::EDIT, function (Action $a) {
                return $a
                    ->setLabel('')
                    ->setIcon('bi bi-pencil-square')
                    ->setCssClass('btn btn-sm btn-outline-primary me-1')
                    ->setHtmlAttributes(['title' => 'Edit', 'aria-label' => 'Edit']);
            })
            ->update(Crud::PAGE_DETAIL, Action::DELETE, function (Action $a) {
                return $a
                    ->setLabel('')
                    ->setIcon('bi bi-trash3')
                    ->setCssClass('btn btn-sm btn-outline-danger')
                    ->setHtmlAttributes(['title' => 'Delete', 'aria-label' => 'Delete']);
            });
    }
}
