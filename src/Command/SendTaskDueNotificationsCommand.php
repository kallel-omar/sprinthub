<?php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:send-task-due-notifications',
    description: 'Send notifications for tasks due today and overdue tasks.'
)]
class SendTaskDueNotificationsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('today');

        $tasks = $this->entityManager
            ->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.dueDate <= :today')
            ->andWhere('t.status != :done')
            ->andWhere('t.assignee IS NOT NULL')
            ->setParameter('today', $today)
            ->setParameter('done', 'done')
            ->getQuery()
            ->getResult();

        $sentCount = 0;

        foreach ($tasks as $task) {
            $link = $this->urlGenerator->generate('app_task_show', [
                'id' => $task->getId(),
            ]);

            if ($task->getDueDate()->format('Y-m-d') === $today->format('Y-m-d')) {
                $message = 'Task "' . $task->getTitle() . '" is due today.';
            } else {
                $message = 'Task "' . $task->getTitle() . '" is overdue.';
            }

            $existingNotification = $this->entityManager
                ->getRepository(Notification::class)
                ->findOneBy([
                    'user' => $task->getAssignee(),
                    'message' => $message,
                    'link' => $link,
                ]);

            if ($existingNotification) {
                continue;
            }

            $notification = new Notification();
            $notification->setUser($task->getAssignee());
            $notification->setMessage($message);
            $notification->setLink($link);

            $this->entityManager->persist($notification);
            $sentCount++;
        }

        $this->entityManager->flush();

        $output->writeln($sentCount . ' due/overdue notification(s) sent.');

        return Command::SUCCESS;
    }
}