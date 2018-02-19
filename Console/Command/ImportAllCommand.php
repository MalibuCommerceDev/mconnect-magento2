<?php

namespace MalibuCommerce\MConnect\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportAllCommand extends Command
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $queue;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $appState;

    /**
     * @var \MalibuCommerce\MConnect\Model\Cron
     */
    protected $mconnectCron;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Cron\Queue $queue,
        \Magento\Framework\App\State $appState,
        \MalibuCommerce\MConnect\Model\Cron $mconnectCron
    ) {
        parent::__construct();
        $this->queue = $queue;
        $this->appState = $appState;
        $this->mconnectCron = $mconnectCron;
        $appState->setAreaCode('adminhtml');
    }

    protected function configure()
    {
        $this
            ->setName('mconnect:importall')
            ->setDescription('Import all NAV entity type records to Magento');
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->mconnectCron->queueCustomerImport();
        $this->mconnectCron->queueProductImport();
        $this->mconnectCron->queueInventoryImport();
        $this->mconnectCron->queueInvoiceImport();
        $this->mconnectCron->queueShipmentImport();
        $this->mconnectCron->queuePriceRuleImport();

        $results = $this->queue->process();
        $output->writeln($results);
    }
}
