<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

	public function configureCrud(Crud $crud): Crud
	{
		return $crud
			->showEntityActionsInlined(); // ✅ inline "Edit / Delete" instead of 3-dots dropdown
	}

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // DETAIL is not shown by default on index; adding it is fine
            ->add(Crud::PAGE_INDEX, Action::DETAIL)

            // Do NOT add SAVE_AND_CONTINUE (it already exists on EDIT)
            // If you want to tweak label/icon, update it instead (safe):
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE, fn(Action $a) =>
                $a->setLabel('Save & Stay')->setIcon('fa fa-floppy-disk')
            )

            // On NEW page, SAVE_AND_ADD_ANOTHER usually exists already in recent EA versions.
            // Updating is safe even if present; if your version doesn’t include it by default,
            // comment the update line below and optionally add() instead.
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER, fn(Action $a) =>
                $a->setLabel('Save & Add Another')->setIcon('fa fa-plus')
            )

            // Button order on index
            ->reorder(Crud::PAGE_INDEX, [Action::NEW, Action::EDIT, Action::DELETE, Action::DETAIL]);
    }

    public function configureFields(string $pageName): iterable
    {
        $rolesChoices = [
            'Admin' => 'ROLE_ADMIN',
            'User'  => 'ROLE_USER',
        ];

        return [
            IdField::new('id')->onlyOnIndex(),

            EmailField::new('email'),

            ChoiceField::new('roles')
                ->setChoices($rolesChoices)
                ->allowMultipleChoices()
                ->renderExpanded(false)  // dropdown multiselect; set true for checkboxes
                ->renderAsBadges(),      // pretty badges on index/detail

            // Unmapped field for setting/changing password
            TextField::new('plainPassword', 'New password')
                ->onlyOnForms()
                ->setHelp('Leave blank to keep the current password')
                ->setFormTypeOption('mapped', false)
                ->setRequired(false),

            // Optionally show hashed password only on detail (or remove entirely)
            // TextField::new('password')->onlyOnDetail(),
        ];
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $plain = $this->readPlainPasswordFromRequest();
            if ($plain !== '') {
                $hash = $this->passwordHasher->hashPassword($entityInstance, $plain);
                $entityInstance->setPassword($hash);
            }
        }
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $plain = $this->readPlainPasswordFromRequest();
            if ($plain !== '') {
                $hash = $this->passwordHasher->hashPassword($entityInstance, $plain);
                $entityInstance->setPassword($hash);
            }
        }
        parent::updateEntity($em, $entityInstance);
    }

    private function readPlainPasswordFromRequest(): string
    {
        $req = $this->getContext()?->getRequest();
        if (!$req) return '';

        $data = $req->request->all();
        foreach ($data as $formName => $fields) {
            if (is_array($fields) && array_key_exists('plainPassword', $fields)) {
                return (string) ($fields['plainPassword'] ?? '');
            }
        }
        return '';
    }
}
