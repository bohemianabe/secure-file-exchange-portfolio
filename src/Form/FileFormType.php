<?php

namespace App\Form;

use App\Entity\Files;
use App\Form\DataTransformer\BytesToHumanTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // ag: get the file entity for the sizeBytes rendering
        /** @var Files|null $entity */
        $entity = $options['data'] ?? null;

        $folderName = null;

        if ($entity?->getFolder()) {
            $folderName = $entity->getFolder()->getBreadcrumbPath();
        }

        $builder
            // ->add('parentFileId')
            ->add('version', IntegerType::class, [
                'label' => 'Version',
                'mapped' => false,
                'data' => $entity ? $entity->getVersion() : null,
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                    'disabled' => true,
                ]
            ])
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ]
            ])
            ->add('originalName', TextType::class, [
                'label' => 'Original Name',
                'mapped' => false,
                'data' => $entity ? $entity->getOriginalName() : null,
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                    'disabled' => true,
                ]
            ])
            // ->add('storagePath')
            // ->add('mimeType')
            // ->add('checksumSha256')
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                ]
            ])
            ->add('expirationDate', DateType::class, [
                'label' => 'Expiration Date',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('sizeBytes', TextType::class, [
                'label' => 'File Size',
                'mapped' => false,
                // ag: is mapped is set to false pass the data to render since it won't auto generate it anymore
                'data' => $entity ? $entity->getSizeBytes() : null,
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                    'disabled' => true,
                ]
            ])
            ->add('folderDisplay', TextType::class, [
                'label' => 'Folder',
                'mapped' => false,
                'data' => $folderName ?? 'No folder assigned',
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                ],
                'disabled' => true,
            ])
        ;
        // ag: incorporate this to make the bytes into readable human form from raw bytes
        $builder->get('sizeBytes')->addViewTransformer(new BytesToHumanTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Files::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'file',
        ]);
    }
}
