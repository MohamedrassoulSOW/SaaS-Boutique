<?php

namespace App\Command;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionBillingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:subscriptions:seed-due',
    description: 'Initialise nextDueAt pour les abonnements qui n\'en ont pas'
)]
class SeedSubscriptionDueCommand extends Command
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private SubscriptionBillingService $billing,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $updated = 0;

        /** @var Subscription[] $list */
        $list = $this->subscriptions->findAll();
        foreach ($list as $subscription) {
            if ($subscription->getNextDueAt() !== null) {
                continue;
            }
            $this->billing->ensureNextDueAt($subscription);
            ++$updated;
        }

        $this->em->flush();
        $io->success(sprintf('%d abonnement(s) mis à jour.', $updated));

        return Command::SUCCESS;
    }
}
