<?php

namespace App\Conversation\Amqp\MessageBus;

use App\Twitter\Infrastructure\Amqp\Console\TwitterListAwareCommand;
use App\Twitter\Infrastructure\Exception\SuspendedAccountException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * @author revue-de-presse.org <thierrymarianne@users.noreply.github.com>
 */
class DispatchAmqpMessagesToFetchThreadedTweetsCommand extends TwitterListAwareCommand
{
    /**
     * @var \OldSound\RabbitMqBundle\RabbitMq\Producer
     */
    private $producer;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var Filesystem
     */
    public $filesystem;

    /**
     * @var string
     */
    public $statusDirectory;

    public function configure()
    {
        $this->setName('app:amqp:produce:conversation')
            ->setDescription('Produce an AMQP message to get a conversation')
         ->addOption(
            'screen_name',
            null,
            InputOption::VALUE_REQUIRED,
            'The screen name of a user'
        )
        ->addOption(
            'aggregate_name',
            null,
            InputOption::VALUE_REQUIRED,
            'The name of an aggregate to attache statuses to'
        )
        ->addOption(
            'producer',
            null,
            InputOption::VALUE_OPTIONAL,
            'A producer key',
            'producer.conversation_status'
        );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|mixed|null
     * @throws SuspendedAccountException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->setUpDependencies();
        $onBehalfOf = $this->input->getOption('screen_name');
        $toBeSavedForAggregate = $this->input->getOption('aggregate_name');

        $statusIdsExist = $this->filesystem->exists($this->statusDirectory.'/status-ids');

        $this->producer->setContentType('application/json');

        if ($statusIdsExist) {
            $finder = new Finder();
            $finder->in($this->statusDirectory)
                ->name('status-ids');

            $counter = (object)['total_conversations' => 0];

            foreach ($finder->getIterator() as $file) {
                $contents = $file->getContents();
                $statusIds = explode(PHP_EOL, $contents);

                array_map(
                    function ($statusId) use (
                        $counter,
                        $onBehalfOf,
                        $toBeSavedForAggregate
                    ) {
                        $messageBody = [
                            'status_id' => (int) trim($statusId),
                            'screen_name' => $onBehalfOf,
                            'aggregate_name' => $toBeSavedForAggregate
                        ];
                        $this->producer->publish(serialize(json_encode($messageBody)));

                        $counter->total_conversations++;
                    },
                    $statusIds
                );
            }
        }

        $this->sendMessage(
            $this->translator->trans(
                'amqp.production.conversations.success',
                ['{{ count }}' => $counter->total_conversations],
                'messages'
            ),
            'info'
        );
    }

    /**
     * @param $message
     * @param $level
     * @param \Exception $exception
     */
    private function sendMessage($message, $level, \Exception $exception = null)
    {
        $this->output->writeln($message);

        if ($exception instanceof \Exception) {
            $this->logger->critical($exception->getMessage());

            return;
        }

        $this->logger->$level($message);
    }

    private function setProducer(): void
    {
        $producerKey = $this->input->getOption('producer');

        /** @var \OldSound\RabbitMqBundle\RabbitMq\Producer $producer */
        $this->producer = $this->getContainer()->get(sprintf(
            'old_sound_rabbit_mq.app.amqp.%s_producer', $producerKey
        ));
    }

    private function setUpDependencies()
    {
        $this->setProducer();

        $this->translator = $this->getContainer()->get('translator');
    }
}