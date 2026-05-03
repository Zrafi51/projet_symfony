<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\NoBannedWords;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Username: validation handled 100% via Symfony Validator constraints (PHP),
            // NOT via HTML attributes like maxlength/pattern/required.
            ->add('username', TextType::class, [
                'label' => 'Nom d\'utilisateur',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom d\'utilisateur est obligatoire.']),
                    new Length([
                        'min' => 2,
                        'max' => 15,
                        'minMessage' => 'Au moins {{ limit }} caractères.',
                        'maxMessage' => 'Maximum {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^[^\d]+$/',
                        'message' => 'Le nom ne doit contenir aucun chiffre.',
                    ]),
                ],
            ])
            // Profile photo: validation handled 100% via Symfony Validator File constraint,
            // NOT via HTML accept attribute.
            // Bio — 30-char max, optional. Same banned-words policy as post
            // descriptions so slurs / violent language can't leak into the
            // stats-row caption. HTML `maxlength` is intentionally NOT set so
            // we control the UX fully via JS (live counter + red error) — the
            // real gate is still the Symfony Length constraint below.
            ->add('bio', TextareaType::class, [
                'label'    => 'Bio',
                'required' => false,
                'attr'     => [
                    'class' => 'form-control',
                    'rows'  => 2,
                    'placeholder' => 'Une courte description (30 caractères max)',
                ],
                'constraints' => [
                    new Length([
                        'max'        => 30,
                        'maxMessage' => 'La bio ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                    new NoBannedWords(),
                ],
            ])
            ->add('profilePhoto', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '3M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG, GIF, WebP).',
                        'maxSizeMessage' => 'La photo ne doit pas dépasser {{ limit }} {{ suffix }}.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
