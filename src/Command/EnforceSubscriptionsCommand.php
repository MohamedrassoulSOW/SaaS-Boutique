<?php

namespace App\Command;

use App\Service\SubscriptionEnforcementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:subscriptions:enforce',
    description: 'Applique les règles d\'impayés (avertissement, suspension, résiliation 15/30 j)'
)]
class EnforceSubscriptionsCommand extends Command
{
    public function __construct(private SubscriptionEnforcementService $enforcement)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans écrire en base')
            ->addOption('notify-only', null, InputOption::VALUE_NONE, 'Notifier uniquement (pas de suspension / résiliation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $notifyOnly = (bool) $input->getOption('notify-only');

        $io->title('Enforcement abonnements');
        if ($dryRun) {
            $io->note('Mode dry-run : aucune modification.');
        }
        if ($notifyOnly) {
            $io->note('Mode notify-only : pas de suspension ni résiliation.');
        }

        $stats = $this->enforcement->enforce($dryRun, $notifyOnly);

        $io->table(
            ['Action', 'Nombre'],
            [
                ['Notifiés', (string) $stats['notified']],
                ['Suspendus', (string) $stats['suspended']],
                ['Résiliés', (string) $stats['terminated']],
                ['Ignorés', (string) $stats['skipped']],
            ]
        );

        $io->success('Terminé.');

        return Command::SUCCESS;
    }
}
