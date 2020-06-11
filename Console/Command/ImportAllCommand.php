<?php

namespace MalibuCommerce\MConnect\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;

class ImportAllCommand extends Command
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

    /**
     * @var \MalibuCommerce\MConnect\Model\Cron
     */
    protected $mconnectCron;

    /**
     * ImportAllCommand constructor
     *
     * @param \MalibuCommerce\MConnect\Model\Cron\Queue $queue
     * @param \Magento\Framework\App\State              $appState
     * @param \MalibuCommerce\MConnect\Model\Cron       $mconnectCron
     */
    public function __construct(
        \MalibuCommerce\MConnect\Model\Cron\Queue $queue,
        \Magento\Framework\App\State $appState,
        \MalibuCommerce\MConnect\Model\Cron $mconnectCron
    ) {
        $this->queue = $queue;
        $this->appState = $appState;
        $this->mconnectCron = $mconnectCron;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('mconnect:importall')
            ->setDescription('Import all NAV entity type records to Magento');
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMIN);

            $this->mconnectCron->queueCustomerImport();
            $this->mconnectCron->queueProductImport();
            $this->mconnectCron->queueInventoryImport();
            $this->mconnectCron->queueInvoiceImport();
            $this->mconnectCron->queueShipmentImport();
            $this->mconnectCron->queuePriceRuleImport();
            $this->mconnectCron->queueRmaImport();
            $this->queue->process(true);

            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf('<error>%s</error>', $e->getMessage() . "\n\n" . $e->getTraceAsString())
            );

            return Cli::RETURN_FAILURE;
        }
    }
}
