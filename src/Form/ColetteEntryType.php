<?php

namespace App\Form;

use App\Entity\ColetteEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ColetteEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'attr' => ['placeholder' => 'ex: infusion-du-matin'],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'Sous-titre',
                'required' => false,
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Carnet de recettes' => ColetteEntry::CATEGORY_RECIPE,
                    'Savoirs des anciens' => ColetteEntry::CATEGORY_KNOWLEDGE,
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Texte principal',
                'attr' => ['rows' => 12],
            ])
            ->add('sourceNotes', TextareaType::class, [
                'label' => 'Notes et sources',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('ocrExtract', TextareaType::class, [
                'label' => 'Propositions OCR',
                'required' => false,
                'attr' => ['rows' => 6, 'readonly' => true],
            ])
            ->add('imageData', HiddenType::class)
            ->add('imageMimeType', HiddenType::class)
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Publié',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ColetteEntry::class,
        ]);
    }
}
