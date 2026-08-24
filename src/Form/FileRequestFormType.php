<?php

namespace App\Form;

use App\Entity\FileRequests;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FileRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('recipientName', TextType::class, [
                'label' => 'Recipient Name',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'Email Address',
                'attr' => [
                    // 'autocomplete' => 'email',
                    'class' => 'form-control',
                    // 'placeholder' => 'Email',
                ],

            ])
            // ->add('type', ChoiceType::class, [
            //     'required' => true,
            //     'label' => 'File Request Type',
            //     'choices' => [
            //         'File Request' => 'uploaded_file',
            //         'Signature Request' => 'signature_request'
            //     ],
            //     'placeholder' => 'Select Request Type',
            //     'attr' => [
            //         'class' => 'form-control select2'
            //     ]
            // ])
        ;
        if (!$options['view_modal']) {
            // ag: only shown on the page form
            $builder->add('isPasscode', CheckboxType::class, [
                'label' => 'Passcode',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
            ]);
        } else {
            // ag: only shown in the view modal
            $builder->add('status', TextType::class, [
                'label' => 'Status',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
                ->add('tokenExpiresAt', DateType::class, [
                    'label' => 'Invitation Expiration Date',
                    'widget' => 'single_text',
                    'format' => 'yyyy-MM-dd',
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ]);
        }
            // ->add('token')
            // ->add('createdAt', null, [
            //     'widget' => 'single_text',
            // ])
            // ->add('updatedAt', null, [
            //     'widget' => 'single_text',
            // ])
            // ->add('firmUserProfile', EntityType::class, [
            //     'class' => FirmUserProfiles::class,
            //     'choice_label' => 'id',
            //     'attr' => [
            //         'class' => 'form-control'
            //     ]
            // ])
            // ->add('file', EntityType::class, [
            //     'class' => Files::class,
            //     'choice_label' => 'id',
            // ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FileRequests::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'fileRequest',
            'view_modal' => false,
        ]);
    }
}
