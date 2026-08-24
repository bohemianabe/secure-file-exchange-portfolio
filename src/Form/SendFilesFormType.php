<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class SendFilesFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $maxSize = match ($options['storage_plan']) {
            'Starter' => '100M',
            'Premium' => '500M',
            'Comprehensive' => '1G',
            default => '100M',
        };

        $maxFiles = match ($options['storage_plan']) {
            'Starter' => 3,
            'Premium' => 10,
            'Comprehensive' => 50,
            default => 3,
        };

        $builder
            ->add('files', FileType::class, [
                'label' => false,
                'multiple' => true,
                'mapped' => false,
                'required' => true,
                'attr' => [
                    'data-max-files' => $maxFiles,
                    'data-max-size' => $maxSize,
                    'class' => 'send_files_form_files',
                ],
                'constraints' => [
                    // ag: since this file form is for multiple upload use the All constraint
                    new All([
                        'constraints' => [
                            new File([
                                'maxSize' => $maxSize,
                                'mimeTypesMessage' => 'Please upload a valid file type.',
                            ]),
                        ],
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'storage_plan' => 'Starter', // default
            'csrf_token_id'   => 'send_files',
            // ensures Symfony doesn’t try to cast the root form data into a string or object.
            'data_class' => null,
            'validation_groups' => false,
        ]);
    }
}
