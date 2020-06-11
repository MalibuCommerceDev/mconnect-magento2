<?php

namespace MalibuCommerce\MConnect\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;

class ExportAllCommand extends Command
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Cron\Queue
     */
    protected $queue;

    /**
     * Emulator adminhtml area for CLI command.
     *
     * @var \Magento\Framework\App\State
     */
    protected $appState;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Cron\Queue $queue,
        \Magento\Framework\App\State $appState
    ) {
        $this->queue = $queue;
        $this->appState = $appState;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('mconnect:exportall')
            ->setDescription('Process all export items in MConnect queue');
        ;
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMIN);
            $output->writeln(
                sprintf('<info>%s</info>', $this->queue->processExportsOnly())
            );

            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf('<error>%s</error>', $e->getMessage())
            );

            return Cli::RETURN_FAILURE;
        }
    }
}
