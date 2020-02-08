<?php

namespace WeavingTheWeb\Bundle\DashboardBundle\DBAL;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\ORM\EntityManager;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use WeavingTheWeb\Bundle\DashboardBundle\Validator\Constraints\Query;

use Psr\Log\LoggerInterface;

use Symfony\Component\Translation\Translator,
    Symfony\Component\Validator\Validator\LegacyValidator as Validator;

/**
 * @author Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class Connection
{
    const QUERY_TYPE_DEFAULT = 0;

    public $connection;

    public $queryCount;

    public $charset;

    public $database;

    public $host;

    public $port;

    public $username;

    public $logger;

    public $password;

    /**
     * @var $entityManager EntityManager
     */
    public $entityManager;

    /**
     * @var $translator Translator
     */
    public $translator;

    /**
     * @var $validator Validator
     */
    public $validator;

    public function __construct(ValidatorInterface $validator, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->validator = $validator;
    }

    public function getWrappedConnection()
    {
        return $this->connection;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function connect()
    {
        $driver = new Driver();
        $connection = $driver->connect(
            [
                'port' => $this->port,
                'dbname' => $this->database,
                'charset' => $this->charset
            ],
            $this->username,
            $this->password
        );

        if (!$connection) {
            throw new \Exception($connection->getWrappedResourceHandle()->connect_error);
        } else {
            $this->setConnection($connection->getWrappedResourceHandle());
        }

        $this->setConnectionCharset();

        return $this;
    }

    public function setTranslator($translator)
    {
        $this->translator = $translator;
    }

    /**
     * @throws \Exception
     */
    protected function setConnectionCharset()
    {
        if (!$this->getWrappedConnection()->set_charset($this->charset)) {
            throw new \Exception(sprintf(
                'Impossible to set charset (%s): %S', $this->charset, \mysqli::$error));
        }
    }

    public function setCharset($charset)
    {
        $this->charset = $charset;
    }

    public function setDatabase($database)
    {
        $this->database = $database;
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setPort($port)
    {
        $this->port = $port;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function execute($query)
    {
        $count = substr_count($query, ';');

        if ($count >= 1) {
            $queries = explode(';', $query);

            if ((count($queries) === $count + 1) ||
                (count($queries) === $count)) {
                $this->queryCount = $count;
            } else {
                throw new \Exception('confusing_query');
            }
        } else {
            $query .= ';';
        }

        if (!$this->connection->multi_query($query)) {
            throw new \Exception($this->connection->error);
        }

        return $this;
    }

    public function setEntityManager($entityManager)
    {
        $this->entityManager = $entityManager;

        return $this;
    }

    /**
     * @param $query
     *
     * @return mixed
     * @throws \Exception
     */
    public function delegateQueryExecution($query)
    {
        $doctrineConnection = $this->entityManager->getConnection();
        $stmt               = $doctrineConnection->prepare($query);
        $stmt->execute();

        try {
            $results = $stmt->fetchAll();
        } catch (\Exception $exception) {
            $results = [$stmt->errorInfo()];
        }

        return $results;
    }

    /**
     * @param $sql
     *
     * @return \stdClass
     */
    public function executeQuery($sql)
    {
        $query       = new \stdClass;
        $query->error   = null;
        $query->records = [];
        $query->sql = $sql;

        if ($this->allowedQuery($query->sql)) {
            try {
                if ($this->pdoSafe($query->sql)) {
                    $query->records = $this->delegateQueryExecution($query->sql);
                } else {
                    $query->records = $this->connect()->execute($query->sql)->fetchAll();
                }
            } catch (\Exception $exception) {
                $query->error = $exception->getMessage();
            }
        } else {
            $query->records = [$this->translator->trans('requirement_valid_query', array(), 'messages')];
        }

        return $query;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function fetchAll()
    {
        $results = [$this->translator->trans('sorry', array(), 'messages'),
            $this->translator->trans('wrong_query_execution', array(), 'messages')];

        do {
            if (!$this->connection->field_count) {
                if (strlen($this->connection->error) > 0) {
                    $error = $this->connection->error;
                    $this->logger->info($error);
                    throw new \Exception($error);
                } else {
                    $results[1] = $this->translator->trans('no_record', array(), 'messages');
                }
            } else {
                $queryResult = $this->connection->use_result();
                unset($results);
                while ($result = $queryResult->fetch_array(MYSQLI_ASSOC)) {
                    $results[] = $result;
                }
                $queryResult->close();
            }

            if ($this->connection->more_results()) {
                $this->connection->next_result();
            }

            $this->queryCount--;
        } while ($this->queryCount > 0);

        $this->queryCount = null;
        $this->connection->close();

        return $results;
    }

    /**
     * @param $sql
     *
     * @return bool
     */
    public function pdoSafe($sql)
    {
        return (false === strpos(strtolower($sql), ':=')) &&
            (false === strpos(strtolower($sql), '@')) &&
            (false === strpos(strtolower($sql), 'update')) &&
            (false === strpos(strtolower($sql), 'drop'));
    }

    /**
     * @param $sql
     *
     * @return bool
     */
    public function allowedQuery($sql)
    {
        $queryConstraint = new Query();
        $errorList = $this->validator->validateValue($sql, $queryConstraint);

        return count($errorList) === 0;
    }
}
