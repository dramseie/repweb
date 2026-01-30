<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected ?User $authenticatedUser = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::setEnv('SMARTSHEET_API_TOKEN', 'test-token');
        self::setEnv('MIGRATION_MANAGER_ENABLED', '0');
        self::setEnv('BLAUE_ELISE_ENABLED', '0');
        self::setEnv('PROGRESS_INDICATORS_ENABLED', '0');

        putenv('MIGRATION_MANAGER_ENABLED=0');
        $_ENV['MIGRATION_MANAGER_ENABLED'] = '0';
        $_SERVER['MIGRATION_MANAGER_ENABLED'] = '0';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->ensureDatabaseExists();

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = array_filter(
            $this->entityManager->getMetadataFactory()->getAllMetadata(),
            static fn ($classMetadata) => $classMetadata->getSchemaName() === null || $classMetadata->getSchemaName() === ''
        );

        $schemaTool->dropDatabase();

        if ($metadata !== []) {
            $schemaTool->createSchema($metadata);
        }

        $this->authenticatedUser = $this->loginAsUser();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        unset($this->entityManager);
        unset($this->client);
    }

    private static function setEnv(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    protected function createUser(string $email, array $roles = ['ROLE_USER']): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPassword('test-password')
            ->setRoles($roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function loginAsUser(?User $user = null): User
    {
        $user ??= $this->createUser(sprintf('tester-%s@example.com', uniqid()));

        $this->client->loginUser($user);

        return $user;
    }

    protected function jsonResponseArray(): array
    {
        $response = $this->client->getResponse();
        $content = $response->getContent();

        if ($content === false) {
            $this->fail('Response body is not readable.');
        }

        $decoded = json_decode($content, true);

        $this->assertIsArray($decoded, 'Response body is not valid JSON array.');

        return $decoded;
    }

    private function ensureDatabaseExists(): void
    {
        $connection = $this->entityManager->getConnection();
        $params = $connection->getParams();
        $databaseName = $params['dbname'] ?? null;

        if ($databaseName === null) {
            return;
        }

        if (!isset($params['driver']) || $params['driver'] !== 'pdo_mysql') {
            return;
        }

        $serverConnection = DriverManager::getConnection(array_merge($params, ['dbname' => null]));

        try {
            $serverConnection->executeStatement(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $databaseName));
        } catch (DBALException $exception) {
            $this->fail(sprintf('Unable to create test database: %s', $exception->getMessage()));
        } finally {
            $serverConnection->close();
        }
    }
}
