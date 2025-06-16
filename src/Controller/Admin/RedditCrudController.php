<?php

namespace App\Controller\Admin;

use App\Entity\Reddit;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class RedditCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reddit::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Subreddit')
            ->setEntityLabelInPlural('Subreddits')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name', 'Nom du subreddit')
                ->setHelp('Nom du subreddit sans le préfixe "r/"')
                ->setRequired(true),
        ];
    }
}