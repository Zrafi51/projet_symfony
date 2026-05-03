<?php

namespace App\Form;

use App\Entity\Music;
use App\Entity\Post;
use App\Repository\MusicRepository;
use App\Validator\NoBannedWords;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PostType extends AbstractType
{
    public function __construct(private MusicRepository $musicRepository) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Build the music choice list (Music object => label) from the repo so
        // the form stays in sync with the admin-curated playlist.
        $musicChoices = [];
        foreach ($this->musicRepository->findAllOrdered() as $m) {
            $musicChoices[$m->getDisplayName()] = $m;
        }

        $builder
            // Description: all validation via PHP Symfony constraints — no HTML
            // required / maxlength / minlength attributes.
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Partagez votre expérience de voyage...',
                    'rows' => 4,
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La description est obligatoire.']),
                    new Length([
                        'min' => 5,
                        'max' => 5000,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                    // Rejects violence / profanity in FR + EN.
                    new NoBannedWords(),
                ],
            ])
            // Images: optional. The controller enforces "at least one image OR one video"
            // across both fields at form-submit time (cross-field rule that doesn't fit a
            // single Count constraint).
            ->add('imageFiles', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new Count([
                        'max' => 100,
                        'maxMessage' => 'Vous ne pouvez pas télécharger plus de {{ limit }} images.',
                    ]),
                    new All([
                        new File([
                            'maxSize' => '8M',
                            'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                            'mimeTypesMessage' => 'Chaque fichier doit être une image (JPEG, PNG, GIF, WebP).',
                            'maxSizeMessage' => 'Chaque image ne doit pas dépasser {{ limit }} {{ suffix }}.',
                        ]),
                    ]),
                ],
                'attr' => [
                    'multiple' => 'multiple',
                ],
            ])
            // Videos: optional. Validated via PHP constraints only.
            ->add('videoFiles', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new Count([
                        'max' => 10,
                        'maxMessage' => 'Vous ne pouvez pas télécharger plus de {{ limit }} vidéos.',
                    ]),
                    new All([
                        new File([
                            'maxSize' => '50M',
                            'mimeTypes' => [
                                'video/mp4',
                                'video/webm',
                                'video/ogg',
                                'video/quicktime',
                                'video/x-matroska',
                            ],
                            'mimeTypesMessage' => 'Chaque fichier doit être une vidéo (MP4, WebM, OGG, MOV, MKV).',
                            'maxSizeMessage' => 'Chaque vidéo ne doit pas dépasser {{ limit }} {{ suffix }}.',
                        ]),
                    ]),
                ],
                'attr' => [
                    'multiple' => 'multiple',
                ],
            ])
            // Music: pick one track from the admin-curated playlist (optional).
            ->add('music', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Aucune musique',
                'choices' => $musicChoices,
                'choice_value' => fn (?Music $m) => $m?->getId(),
                'label' => 'Musique de fond',
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'is_new' => true,
        ]);
    }
}
