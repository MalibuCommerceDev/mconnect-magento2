<?php

namespace MalibuCommerce\MConnect\Console\Command;

use MalibuCommerce\MConnect\Model\Queue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;

class ProcessItemCommand extends Command
{
    const ARGUMENT_CODE = 'code';
    const ARGUMENT_ACTION = 'action';
    const ARGUMENT_ENTITY_ID = 'entity_id';
    const ARGUMENT_WEBSITE_ID = 'website_id';
    const OPTION_SYNC = 'sync';

    /**
     * @var Queue
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
    protected $cronModel;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    public function __construct(
        Queue $queue,
        \Magento\Framework\App\State $appState,
        \MalibuCommerce\MConnect\Model\Cron $cronModel,
        \MalibuCommerce\MConnect\Model\Config $config
    ) {
        $this->queue = $queue;
        $this->appState = $appState;
        $this->cronModel = $cronModel;
        $this->config = $config;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('mconnect:processitem')
            ->setDescription('Add and process specific item in MConnect queue')
            ->setDefinition([
                new InputArgument(
                    self::ARGUMENT_CODE,
                    InputArgument::REQUIRED,
                    'Code'
                ),
                new InputArgument(
                    self::ARGUMENT_ACTION,
                    InputArgument::REQUIRED,
                    'Action'
                ),
                new InputArgument(
                    self::ARGUMENT_WEBSITE_ID,
                    InputArgument::OPTIONAL,
                    'Website ID'
                ),
                new InputArgument(
                    self::ARGUMENT_ENTITY_ID,
                    InputArgument::OPTIONAL,
                    'Entity ID'
                ),
                new InputOption(
                    self::OPTION_SYNC,
                    '-s',
                    InputOption::VALUE_NONE,
                    'Sync immediately'
                ),
            ])
        ;
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMIN);

            $code = $input->getArgument(self::ARGUMENT_CODE);
            $action = $input->getArgument(self::ARGUMENT_ACTION);
            $websiteIds = $input->getArgument(self::ARGUMENT_WEBSITE_ID);
            $entityId = $input->getArgument(self::ARGUMENT_ENTITY_ID);
            $sync = $input->getOption(self::OPTION_SYNC);

            if ($websiteIds === null || $websiteIds === '') {
                $websiteIds = $this->cronModel->getMultiCompanyActiveWebsites();
            }
            if (!is_array($websiteIds)) {
                $websiteIds = [$websiteIds];
            }

            foreach ($websiteIds as $websiteId) {
                if ($action == Queue::ACTION_IMPORT && !(bool)$this->config->getWebsiteData($code . '/import_enabled', $websiteId)) {
                    $message = sprintf('Import functionality is disabled for %s at Website ID %s', $code, $websiteId);
                    $output->writeln('<warning>' . $message . ' </warning>');
                    continue;
                }
                if ($action == Queue::ACTION_EXPORT && !(bool)$this->config->getWebsiteData($code . '/export_enabled', $websiteId)) {
                    $message = sprintf('Export functionality is disabled for %s at Website ID %s', $code, $websiteId);
                    $output->writeln('<warning>' . $message . ' </warning>');
                    continue;
                }

                $queue = $this->queue->add($code, $action, $websiteId, 0, $entityId, null, [], null, true);

                if ($queue->getId()) {
                    $message = sprintf('New %s item added to queue for Website ID %s: %s', $code, $websiteId, $queue->getId());
                    $output->writeln('<info>' . $message . ' </info>');
                } else {
                    $message = sprintf('Failed to add new %s item added to queue for Website ID %s', $code, $websiteId);
                    $output->writeln('<error>' . $message . ' </error>');
                }

                if ($sync) {
                    $output->writeln('Syncing...');
                    $queue->process();
                    $output->writeln('Sync Completed!');
                }
            }

            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf('<error>%s</error>', $e->getMessage())
            );

            return Cli::RETURN_FAILURE;
        }
    }
}
