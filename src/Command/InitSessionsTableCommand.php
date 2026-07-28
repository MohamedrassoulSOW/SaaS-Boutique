<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sessions:init', description: 'Crée la table sessions en base (stockage PDO, pas de fichiers)')]
class InitSessionsTableCommand extends Command
{
    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS sessions (
    sess_id VARBINARY(128) NOT NULL PRIMARY KEY,
    sess_data BLOB NOT NULL,
    sess_lifetime INT UNSIGNED NOT NULL,
    sess_time INT UNSIGNED NOT NULL,
    INDEX sessions_sess_lifetime_idx (sess_lifetime)
) COLLATE utf8mb4_bin, ENGINE = InnoDB
SQL;
        } elseif ($platform instanceof SQLitePlatform) {
            $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS sessions (
    sess_id VARCHAR(128) NOT NULL PRIMARY KEY,
    sess_data BLOB NOT NULL,
    sess_lifetime INTEGER NOT NULL,
    sess_time INTEGER NOT NULL
)
SQL;
        } else {
            $io->error('Plateforme non supportée pour la table sessions.');

            return Command::FAILURE;
        }

        $this->connection->executeStatement($sql);
        $io->success('Table `sessions` prête — les sessions PHP sont stockées en base.');

        return Command::SUCCESS;
    }
}
