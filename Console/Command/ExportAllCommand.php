<?php

namespace MalibuCommerce\MConnect\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Config\Console\Command\EmulatedAdminhtmlAreaProcessor;
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
     * @var EmulatedAdminhtmlAreaProcessor
     */
    protected $emulatedAreaProcessor;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Cron\Queue $queue,
        EmulatedAdminhtmlAreaProcessor $emulatedAreaProcessor
    ) {
        $this->queue = $queue;
        $this->emulatedAreaProcessor = $emulatedAreaProcessor;

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
            $this->emulatedAreaProcessor->process(function () use ($input, $output) {
                $output->writeln(
                    sprintf('<info>%s</info>', $this->queue->processExportsOnly())
                );
            });

            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf('<error>%s</error>', $e->getMessage())
            );

            return Cli::RETURN_FAILURE;
        }
    }
}
