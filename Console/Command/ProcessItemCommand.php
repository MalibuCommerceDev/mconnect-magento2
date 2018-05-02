<?php

namespace MalibuCommerce\MConnect\Console\Command;

use MalibuCommerce\MConnect\Model\Queue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Config\Console\Command\EmulatedAdminhtmlAreaProcessor;
use Magento\Framework\Console\Cli;

class ProcessItemCommand extends Command
{
    const ARGUMENT_CODE = 'code';
    const ARGUMENT_ACTION = 'action';
    const ARGUMENT_ENTITY_ID = 'entity_id';
    const OPTION_SYNC = 'sync';

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * Emulator adminhtml area for CLI command.
     *
     * @var EmulatedAdminhtmlAreaProcessor
     */
    protected $emulatedAreaProcessor;

    /**
     * ProcessItemCommand constructor.
     *
     * @param Queue                          $queue
     * @param EmulatedAdminhtmlAreaProcessor $emulatedAreaProcessor
     */
    public function __construct(
        Queue $queue,
        EmulatedAdminhtmlAreaProcessor $emulatedAreaProcessor
    ) {
        $this->queue = $queue;
        $this->emulatedAreaProcessor = $emulatedAreaProcessor;

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
            $this->emulatedAreaProcessor->process(function () use ($input, $output) {
                $code = $input->getArgument(self::ARGUMENT_CODE);
                $action = $input->getArgument(self::ARGUMENT_ACTION);
                $entityId = $input->getArgument(self::ARGUMENT_ENTITY_ID);
                $sync = $input->getOption(self::OPTION_SYNC);
                $queue = $this->queue->add($code, $action, $entityId, [], null, true);
                if (!$queue->getId()) {
                    $output->writeln('<error>Failed to add item to the queue</error>');
                    return;
                }
                $output->writeln(sprintf('Queue Item ID is "%d"', $queue->getId()));
                if ($sync) {
                    $output->writeln('Syncing...');
                    $queue->process();
                    if ($queue->getStatus() === Queue::STATUS_SUCCESS) {
                        $output->writeln('Success');
                    } else {
                        $message = 'Failed';
                        $message .= ': ' . $queue->getMessage();
                        $output->writeln('<error>' . $message . '</error>');
                    }
                }
            });

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(
                sprintf('<error>%s</error>', $e->getMessage())
            );

            return Cli::RETURN_FAILURE;
        }
    }
}
