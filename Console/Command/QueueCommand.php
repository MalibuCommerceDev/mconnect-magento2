<?php

namespace MalibuCommerce\MConnect\Console\Command;

use MalibuCommerce\MConnect\Model\Queue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueueCommand extends Command
{
    const ARGUMENT_CODE = 'code';
    const ARGUMENT_ACTION = 'action';
    const ARGUMENT_ENTITY_ID = 'entity_id';
    const OPTION_SYNC = 'sync';

    private $queue;

    public function __construct(
        Queue $queue
    ) {
        parent::__construct();
        $this->queue = $queue;
    }

    protected function configure()
    {
        $this
            ->setName('mconnect:queue')
            ->setDescription('Process items in MConnect queue')
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
                    'Action'
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
        $code = $input->getArgument(self::ARGUMENT_CODE);
        $action = $input->getArgument(self::ARGUMENT_ACTION);
        $entityId = $input->getArgument(self::ARGUMENT_ENTITY_ID);
        $sync = $input->getOption(self::OPTION_SYNC);
        $queue = $this->queue->add($code, $action, $entityId);
        if (!$queue->getId()) {
            $output->writeln('<error>Failed to add item to queue.  Perhaps it already exists?</error>');
            return;
        }
        $output->writeln(sprintf('Added item to queue. ID: %d', $queue->getId()));
        if ($sync) {
            $output->writeln('Syncing...');
            $queue->process();
            if ($queue->getStatus() === Queue::STATUS_SUCCESS) {
                $output->writeln('Success');
            } else {
                $output->writeln('<error>Failed</error>');
            }
            $message = $queue->getMessage();
            if ($message) {
                $output->writeln($message);
            }
        }
    }
}
