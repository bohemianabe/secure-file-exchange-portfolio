<?php

namespace App\Command;

use App\Logging\AuditLogger;
use App\Message\SendChatDigestEmailMessage;
use App\Repository\ConversationParticipantsRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-chat-digest-notifications',
    description: 'Queues digest emails for users with unread chat messages.',
)]
class SendChatDigestNotificationsCommand extends Command
{

    public function __construct(
        private readonly ConversationParticipantsRepository $convoParticipantRepo,
        private readonly MessageBusInterface $messageBus,
        private AuditLogger $audit,

    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $convoParticipants = $this->convoParticipantRepo->findUsersWithUnreadConversationMessages();
            $count = 0;

            if (!empty($convoParticipants)) {
                foreach ($convoParticipants as $participant) {
                    $firm = $participant->getConversation()->getFirm();

                    if (!$firm->getStoragePlan()?->getChat()) {
                        continue;
                    }
                    // ag: check if notifyUnreadConversation is set to true on userProfile
                    if ($participant->getUser()?->getUserProfile()->getNotifyUnreadConversation()) {
                        $count++;
                        $this->messageBus->dispatch(
                            new SendChatDigestEmailMessage($participant->getUser()->getId(), $participant->getConversation()->getId())
                        );
                    }
                }
            }

            $io->success(sprintf('Queued %d chat digest emails.', $count));
        } catch (\Throwable $e) {
            $this->audit->log(
                action: 'conversation.sendChatDigestNotificationsCommand',
                resourceType: 'Conversations',
                // resourceId: $export->getId(),
                success: false,
                metadata: ['error' => $e->getMessage()],
                severity: 'error',
            );

            $io->warning($e->getMessage());
        }

        return Command::SUCCESS;
    }
}
