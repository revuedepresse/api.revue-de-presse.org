<?php
declare(strict_types=1);

namespace App\Amqp\Command;

use App\Api\Entity\TokenInterface;
use App\Api\AccessToken\Repository\TokenRepositoryInterface;
use App\Infrastructure\DependencyInjection\LoggerTrait;
use App\Twitter\Api\ApiAccessorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package App\Amqp\Command
 */
abstract class AccessorAwareCommand extends Command
{
    use LoggerTrait;

    private const OPTION_OAUTH_SECRET = 'oauth_secret';
    private const OPTION_OAUTH_TOKEN  = 'oauth_token';

    protected ApiAccessorInterface $accessor;

    /**
     * @var string
     */
    protected string $defaultSecret;

    /**
     * @var string
     */
    protected string $defaultToken;

    protected TokenRepositoryInterface $tokenRepository;

    protected InputInterface $input;

    protected OutputInterface $output;

    public function setAccessor(ApiAccessorInterface $accessor): self
    {
        $this->accessor = $accessor;

        return $this;
    }

    public function setDefaultSecret(string $secret): void
    {
        $this->defaultSecret = $secret;
    }

    public function setDefaultToken(string $token): void
    {
        $this->defaultToken = $token;
    }

    public function setTokenRepository(TokenRepositoryInterface $tokenRepository): self
    {
        $this->tokenRepository = $tokenRepository;

        return $this;
    }

    /**
     * @return bool|string|string[]|null
     */
    protected function getOAuthSecret()
    {
        $secret = $this->defaultSecret;
        if ($this->hasOAuthSecretBeenPassedAsOption()) {
            $secret = $this->input->getOption(self::OPTION_OAUTH_SECRET);
        }

        return $secret;
    }

    /**
     * @return bool|string|string[]|null
     */
    protected function getOAuthToken()
    {
        $token = $this->defaultToken;
        if ($this->hasOAuthTokenBeenPassedAsOption()) {
            $token = $this->input->getOption(self::OPTION_OAUTH_TOKEN);
        }

        return $token;
    }

    protected function getTokensFromInputOrFallback(): array
    {
        return [
            'token'  => $this->getOAuthToken(),
            'secret' => $this->getOAuthSecret(),
        ];
    }

    private function hasOAuthSecretBeenPassedAsOption(): bool
    {
        return $this->input->hasOption(self::OPTION_OAUTH_SECRET)
            && $this->input->getOption(self::OPTION_OAUTH_SECRET) !== null;
    }

    protected function setUpLogger()
    {
        // noop for backward compatibility
        // TODO remove all 5 calls to this method
    }

    private function hasOAuthTokenBeenPassedAsOption(): bool
    {
        return $this->input->hasOption(self::OPTION_OAUTH_TOKEN) &&
            $this->input->getOption(self::OPTION_OAUTH_TOKEN) !== null;
    }
}
