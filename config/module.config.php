<?php declare(strict_types=1);

namespace ContributeEnhancements;

return [
    'contribute_enhancements' => [
        'email_templates' => [
            'accepted' => [
                'subject' => 'Your contribution has been accepted: {item_title}', // @translate
                'body' => <<<'MAIL'
Your contribution has been reviewed and accepted on {main_title}.

You can view the item on the following public site pages:
{item_urls}

Thank you for your contribution.
MAIL,
            ],
            'rejected' => [
                'subject' => 'Your contribution was not accepted: {item_title}', // @translate
                'body' => <<<'MAIL'
Thank you for your contribution to {main_title}.

After review, we are unable to accept the proposed changes at this time.

If you have questions, please reply to this message.
MAIL,
            ],
            'needs-changes' => [
                'subject' => 'Changes requested for your contribution: {item_title}', // @translate
                'body' => <<<'MAIL'
Thank you for your contribution to {main_title}.

We reviewed your submission and need additional changes before it can be accepted. Please sign in and update your contribution when you are ready.

If you have questions, please reply to this message.
MAIL,
            ],
            'added-to-omeka' => [
                'subject' => 'Your item has been added to {main_title}', // @translate
                'body' => <<<'MAIL'
Your submitted item has been added to {main_title}.

You can view it on the following public site pages:
{item_urls}

Thank you for your contribution.
MAIL,
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            Service\ProposalDiffService::class => Service\Factory\ProposalDiffServiceFactory::class,
            Service\ProposalNormalizer::class => Service\Factory\ProposalNormalizerFactory::class,
            Service\ProposalRestoreService::class => Service\Factory\ProposalRestoreServiceFactory::class,
            Service\ProposalRemovalApprovalService::class => Service\Factory\ProposalRemovalApprovalServiceFactory::class,
            Service\ProposalRevisionService::class => Service\Factory\ProposalRevisionServiceFactory::class,
            Service\ProposalApplicationService::class => Service\Factory\ProposalApplicationServiceFactory::class,
            Service\ContributionDeletePolicy::class => Service\Factory\ContributionDeletePolicyFactory::class,
            Service\ProposalBaselineService::class => Service\Factory\ProposalBaselineServiceFactory::class,
            Service\ProposalAuditViewService::class => Service\Factory\ProposalAuditViewServiceFactory::class,
            Service\ProposalLanguageService::class => Service\Factory\ProposalLanguageServiceFactory::class,
            Service\ProposalEditViewService::class => Service\Factory\ProposalEditViewServiceFactory::class,
            Service\UndertakingService::class => Service\Factory\UndertakingServiceFactory::class,
            Service\ResourceValueRemovalApplier::class => Service\Factory\ResourceValueRemovalApplierFactory::class,
            Service\ArchiveService::class => Service\Factory\ArchiveServiceFactory::class,
            Service\ContributionEmailTemplateService::class => Service\Factory\ContributionEmailTemplateServiceFactory::class,
            Listener\ContributorDeleteGuardListener::class => Listener\Factory\ContributorDeleteGuardListenerFactory::class,
            Listener\ContributorStatusListener::class => Listener\Factory\ContributorStatusListenerFactory::class,
            Listener\ProposalNormalizeListener::class => Listener\Factory\ProposalNormalizeListenerFactory::class,
            Listener\ProposalSnapshotListener::class => Listener\Factory\ProposalSnapshotListenerFactory::class,
            Listener\ContributionValidatedListener::class => Listener\Factory\ContributionValidatedListenerFactory::class,
            Listener\ArchiveSearchListener::class => Listener\Factory\ArchiveSearchListenerFactory::class,
            Listener\ArchivedContributionGuardListener::class => Listener\Factory\ArchivedContributionGuardListenerFactory::class,
            Listener\AdminViewListener::class => Listener\Factory\AdminViewListenerFactory::class,
            Listener\SiteViewListener::class => Listener\Factory\SiteViewListenerFactory::class,
        ],
        'invokables' => [
            Service\ProposalResubmitService::class => Service\ProposalResubmitService::class,
            Service\ProposalDedupeService::class => Service\ProposalDedupeService::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'ContributeEnhancements\Controller\Admin\Contribution' => Service\Factory\ContributionControllerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'template_map' => [
            'common/admin/contribute-list-part' => dirname(__DIR__) . '/view/common/admin/contribute-list-part.phtml',
            'common/admin/contribute-list' => dirname(__DIR__) . '/view/common/admin/contribute-list.phtml',
            'common/dialog/contribution-send-message' => dirname(__DIR__) . '/view/common/dialog/contribution-send-message.phtml',
            'contribute/site/contribution/show' => dirname(__DIR__) . '/view/contribute/site/contribution/show.phtml',
            'guest/site/guest/contribution-browse' => dirname(__DIR__) . '/view/guest/site/guest/contribution-browse.phtml',
            'contribute/admin/contribution/browse' => dirname(__DIR__) . '/view/contribute/admin/contribution/browse.phtml',
            'contribute-enhancements/guest/contribution-values' => dirname(__DIR__) . '/view/contribute-enhancements/guest/contribution-values.phtml',
            'contribute-enhancements/guest/contribution-values-part' => dirname(__DIR__) . '/view/contribute-enhancements/guest/contribution-values-part.phtml',
            'contribute-enhancements/guest/contribution-change-history' => dirname(__DIR__) . '/view/contribute-enhancements/guest/contribution-change-history.phtml',
            'contribute-enhancements/guest/archive-filter' => dirname(__DIR__) . '/view/contribute-enhancements/guest/archive-filter.phtml',
            'contribute-enhancements/guest/asset-include' => dirname(__DIR__) . '/view/contribute-enhancements/guest/asset-include.phtml',
            'guest/site/guest/contribution-show' => dirname(__DIR__) . '/view/guest/site/guest/contribution-show.phtml',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'contributionAuditValues' => View\Helper\Factory\ContributionAuditValuesFactory::class,
            'contributionCanDelete' => View\Helper\Factory\ContributionCanDeleteFactory::class,
            'contributionFields' => View\Helper\Factory\ContributionFieldsFactory::class,
            'contributionProposalDiff' => View\Helper\Factory\ContributionProposalDiffFactory::class,
            'contributionStatusLabel' => View\Helper\Factory\ContributionStatusLabelFactory::class,
            'contributionArchive' => View\Helper\Factory\ContributionArchiveFactory::class,
            'contributeEnhancementsEmailTemplates' => View\Helper\Factory\ContributeEnhancementsEmailTemplatesFactory::class,
            'sendMessageDialog' => View\Helper\Factory\SendMessageDialogFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'contribute-enhancements' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/contribute-enhancements',
                            'defaults' => [
                                '__NAMESPACE__' => 'ContributeEnhancements\Controller\Admin',
                                'controller' => 'Contribution',
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/contribution/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'controller' => 'Contribution',
                                        'action' => 'restore-value',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
