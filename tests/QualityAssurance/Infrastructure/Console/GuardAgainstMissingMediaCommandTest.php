<?php
declare (strict_types=1);

namespace App\Tests\Membership\Infrastructure\Console;

use App\QualityAssurance\Infrastructure\Console\GuardAgainstMissingMediaCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group fetch_missing_media
 */
class GuardAgainstMissingMediaCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private GuardAgainstMissingMediaCommand $command;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $kernel = static::bootKernel();

        $command = static::getContainer()->get('test.'.GuardAgainstMissingMediaCommand::class);

        $application = new Application($kernel);

        $this->command = $application->find(GuardAgainstMissingMediaCommand::COMMAND_NAME);

        $this->commandTester = new CommandTester($command);
    }

    /**
     * @test
     */
    public function it_terminates_successfully()
    {
        // Act
        $this->commandTester->execute([]);

        // Assert
        self::assertEquals(
            $this->commandTester->getStatusCode(),
            $this->command::SUCCESS,
            sprintf(
                'When executing "%s" command, it status code should should be %d (for a successfully executed command)',
                $this->command::COMMAND_NAME,
                $this->command::SUCCESS
            )
        );
    }
}