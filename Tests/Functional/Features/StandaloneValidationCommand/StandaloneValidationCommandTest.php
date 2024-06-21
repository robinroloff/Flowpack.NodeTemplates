<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\StandaloneValidationCommand;

use Flowpack\NodeTemplates\Application\Command\NodeTemplateCommandController;
use Flowpack\NodeTemplates\Tests\Functional\FakeNodeTypeManagerTrait;
use Flowpack\NodeTemplates\Tests\Functional\SnapshotTrait;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\ContentDimensionRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\Flow\Cli\Response;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Utility\ObjectAccess;
use Symfony\Component\Console\Output\BufferedOutput;

final class StandaloneValidationCommandTest extends FunctionalTestCase
{
    use SnapshotTrait;
    use FakeNodeTypeManagerTrait;

    protected static $testablePersistenceEnabled = true;

    private ContextFactoryInterface $contextFactory;

    private NodeTypeManager $nodeTypeManager;

    private string $fixturesDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->nodeTypeManager = $this->getObject(NodeTypeManager::class);

        $this->loadFakeNodeTypes();

        $this->setupContentRepository();

        $ref = new \ReflectionClass($this);
        $this->fixturesDir = dirname($ref->getFileName()) . '/Snapshots';
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
        $this->getObject(FeedbackCollection::class)->reset();
        $this->objectManager->forgetInstance(ContentDimensionRepository::class);
        $this->objectManager->forgetInstance(NodeTypeManager::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    final protected function getObject(string $className): object
    {
        return $this->objectManager->get($className);
    }

    private function setupContentRepository(): void
    {
        // Create an environment to create nodes.
        $this->getObject(ContentDimensionRepository::class)->setDimensionsConfiguration([]);

        $liveWorkspace = new Workspace('live');
        $workspaceRepository = $this->getObject(WorkspaceRepository::class);
        $workspaceRepository->add($liveWorkspace);

        $testSite = new Site('test-site');
        $testSite->setSiteResourcesPackageKey('Test.Site');
        $siteRepository = $this->getObject(SiteRepository::class);
        $siteRepository->add($testSite);

        $this->persistenceManager->persistAll();
        $this->contextFactory = $this->getObject(ContextFactoryInterface::class);
        $subgraph = $this->contextFactory->create(['workspaceName' => 'live']);

        $rootNode = $subgraph->getRootNode();

        $sitesRootNode = $rootNode->createNode('sites');
        $testSiteNode = $sitesRootNode->createNode('test-site');
        $testSiteNode->createNode(
            'homepage',
            $this->nodeTypeManager->getNodeType('Flowpack.NodeTemplates:Document.HomePage')
        );
    }

    /** @test */
    public function itMatchesSnapshot()
    {
        $commandController = $this->getObject(NodeTemplateCommandController::class);

        ObjectAccess::setProperty($commandController, 'response', $cliResponse = new Response(), true);
        ObjectAccess::getProperty($commandController, 'output', true)->setOutput($bufferedOutput = new BufferedOutput());

        try {
            $commandController->validateCommand();
        } catch (StopCommandException $e) {
        }

        $contents = $bufferedOutput->fetch();
        self::assertSame(1, $cliResponse->getExitCode());

        $this->assertStringEqualsFileOrCreateSnapshot($this->fixturesDir . '/NodeTemplateValidateOutput.log', $contents);
    }
}
