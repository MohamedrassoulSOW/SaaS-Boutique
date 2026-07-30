<?php

namespace App\Command;

use App\Repository\ProductRepository;
use App\Service\ProductPhotoGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-product-photos',
    description: 'Génère des images pour les produits sans photo',
)]
class GenerateProductPhotosCommand extends Command
{
    public function __construct(
        private ProductRepository $products,
        private ProductPhotoGenerator $generator,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Régénère aussi les produits déjà illustrés');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $count = 0;

        foreach ($this->products->findAll() as $product) {
            if (!$force && $product->hasPhoto()) {
                continue;
            }
            if ($this->generator->applyPlaceholder($product)) {
                ++$count;
            }
        }

        $this->em->flush();
        $io->success(sprintf('%d image(s) produit générée(s).', $count));

        return Command::SUCCESS;
    }
}
