<?php

namespace App\Form;

use App\Entity\FirmUserProfiles;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FirmUserProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_signature_field_only']) {
            $builder
                ->add('signature', TextareaType::class, [
                    'label' => 'Signature',
                    'required' => false,
                    'attr' => [
                        'class' => 'hugerte form-control',
                        'rows' => 10,
                    ]
                ])
                ->add('submit', SubmitType::class, [
                    'label' => 'Save Signature',
                    'attr' => [
                        'class' => 'btn btn-primary text-white',
                    ],
                ]);
        } elseif ($options['can_edit_user_type']) {
            $builder
                ->add('userType', ChoiceType::class, [
                    'label' => 'User Type',
                    'required' => true,
                    'choices' => [
                        'Primary' => FirmUserProfiles::USER_TYPE_PRIMARY,
                        'Admin' => FirmUserProfiles::USER_TYPE_ADMIN,
                        'Employee' => FirmUserProfiles::USER_TYPE_EMPLOYEE,
                    ],
                    'attr' => [
                        'class' => 'form-select select2',
                    ],
                ])
                ->add('firstName', TextType::class, [
                    'label' => 'First Name',
                    'required' => true,
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('lastName', TextType::class, [
                    'label' => 'Last Name',
                    'required' => true,
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('title', TextType::class, [
                    'label' => 'Title',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('phone', TextType::class, [
                    'label' => 'Phone',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('notifyFileUploaded', CheckboxType::class, [
                    'label' => 'File Uploaded',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('notifyFileDownloaded', CheckboxType::class, [
                    'label' => 'File Downloaded',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('notifyClientInvitationAccepted', CheckboxType::class, [
                    'label' => 'Accepted Invitation',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('notifyFileExpiringSoon', CheckboxType::class, [
                    'label' => 'Expiring Files',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('notifyFileRequestCompleted', CheckboxType::class, [
                    'label' => 'File Request Completed',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('notifyUnreadConversation', CheckboxType::class, [
                    'label' => 'Unread Conversation',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('contactUser', CheckboxType::class, [
                    'label' => 'Contact User',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
            ;
        } else {
            $builder
                ->add('firstName', TextType::class, [
                    'label' => 'First Name',
                    'required' => true,
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('lastName', TextType::class, [
                    'label' => 'Last Name',
                    'required' => true,
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('title', TextType::class, [
                    'label' => 'Title',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('phone', TextType::class, [
                    'label' => 'Phone',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('notifyFileUploaded', CheckboxType::class, [
                    'label' => 'File Uploaded',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('notifyFileDownloaded', CheckboxType::class, [
                    'label' => 'File Downloaded',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('notifyClientInvitationAccepted', CheckboxType::class, [
                    'Accepted Invitation',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('notifyFileExpiringSoon', CheckboxType::class, [
                    'label' => 'Expiring Files',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('notifyFileRequestCompleted', CheckboxType::class, [
                    'label' => 'File Request Completed',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
                ->add('contactUser', CheckboxType::class, [
                    'label' => 'Contact User',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FirmUserProfiles::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'firm_user_profile',
            'include_signature_field_only' => false,
            'can_edit_user_type' => true,
        ]);
    }
}
